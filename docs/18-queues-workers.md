# 18 — Queues & Workers

## Topology

Redis-backed, supervised by Horizon. Queues are separated by *latency expectation*, not by feature,
so a slow media job can never delay a password-reset email.

| Queue | Priority | Workers (prod) | Timeout | Tries | Typical jobs |
| --- | --- | --- | --- | --- | --- |
| `realtime` | highest | 4 | 15 s | 3 | Cache warms, ISR revalidation pings, notification fan-out |
| `mail` | high | 3 | 30 s | 5 | Transactional email |
| `tools` | high | 6 | 120 s | 2 | Async tool runs |
| `media` | normal | 2 | 600 s | 3 | Variants, transcodes, thumbnails |
| `default` | normal | 3 | 60 s | 3 | Everything else |
| `analytics` | low | 2 | 300 s | 3 | Run recording, rollups |
| `maintenance` | lowest | 1 | 900 s | 1 | Pruning, reconciliation, sitemaps |

Horizon `balance: auto` with `minProcesses`/`maxProcesses` per queue, and `memory: 256` so a leaky
job cannot take the box down.

## Job rules

Every job in this codebase obeys all six:

1. **Idempotent.** Re-running must be harmless. Use `ShouldBeUnique` with a meaningful key, or make
   the write itself idempotent (upsert, conditional update).
2. **Small payloads.** Pass IDs, never models or blobs. `SerializesModels` re-fetches — and if the
   row is gone, the job exits cleanly rather than throwing.
3. **Explicit `$timeout`, `$tries`, `$backoff`.** Defaults are never good enough for a job that
   calls an external API.
4. **Failure is handled.** `failed(Throwable $e)` marks domain state (`tool_runs.status = failed`),
   notifies where a human must act, and reports to Sentry with context.
5. **Observable.** Structured log lines with the job name, subject id and duration; slow jobs
   (>p95×2) trigger a warning.
6. **Cancellable.** Long jobs check `$this->batch()?->cancelled()` and a per-tool kill switch between
   steps.

```php
final class RunToolJob implements ShouldQueue, ShouldBeUnique
{
    public int $timeout = 120;
    public int $tries = 2;
    public function backoff(): array { return [5, 30]; }
    public function uniqueId(): string { return "tool-run:{$this->runUlid}"; }
    public string $queue = 'tools';
}
```

## Batching

Bulk operations (bulk post edits, bulk link checks, media reprocessing) use job batches so progress
is reportable and a partial failure is visible:

```php
Bus::batch($chunks)
    ->name("bulk-post-update:{$actor->id}")
    ->allowFailures()
    ->onQueue('default')
    ->progress(fn (Batch $b) => BulkProgress::update($b))
    ->finally(fn (Batch $b) => $actor->notify(new BulkOperationFinished($b)))
    ->dispatch();
```

The admin UI polls the batch and shows per-row results — never a bare "done".

## Rate-limited and concurrency-limited work

External providers are protected at the job level as well as the call level:

```php
Redis::throttle('provider:youtube')->allow(60)->every(60)->then($work, $release);
Redis::funnel('provider:openai')->limit(4)->then($work, $release);
```

Budgets are shared across all workers because the counter lives in Redis, so scaling workers cannot
accidentally blow a third-party quota.

## Scheduled work

| Schedule | Command | Purpose |
| --- | --- | --- |
| every minute | `posts:publish-scheduled` | Move `scheduled` → `published`, revalidate, notify |
| every minute | `horizon:snapshot` | Queue metrics |
| every 5 min | `tools:health-check` | Provider probes, open/close circuit breakers |
| hourly | `entitlements:expire` | Expire passes and time-boxed grants |
| hourly | `newsletter:resync` | Replay failed provider syncs |
| daily 02:00 | `analytics:rollup` | Yesterday's stats into rollup tables |
| daily 02:30 | `billing:snapshot` | MRR/ARR/churn |
| daily 03:00 | `tools:compute-related` | Related-tool graph |
| daily 03:30 | `sitemap:generate` | Sitemaps + ping |
| daily 04:00 | `db:backup` | Dump → Spaces, 30-day retention, restore-tested weekly |
| daily 04:30 | `media:prune-orphans` | Unreferenced media report |
| daily 05:00 | `stripe:reconcile` | Replay 30 days of events, report drift |
| weekly | `seo:health-report` | Impression drops, duplicate meta, orphan pages |
| weekly | `tool-runs:prune` | Drop raw runs older than 90 days (rollups kept) |
| monthly | `users:purge-deleted` | Hard-delete accounts past their recovery window |

The scheduler runs on one node only (`onOneServer()`), guarded by a Redis lock, and every task uses
`withoutOverlapping()` and `runInBackground()` where it is safe.

## Failure handling

Failed jobs land in `failed_jobs` with full context. Horizon alerts to Slack when the failure rate
over 5 minutes exceeds 2%, or when any queue's wait time exceeds its threshold (`tools` 30 s, `mail`
60 s, `media` 300 s). A weekly review triages anything that has failed more than three times — those
are bugs, not transients.

## Local development

Horizon runs in the `worker` container. `make queue-restart` reloads code after changes.
`QUEUE_CONNECTION=sync` in `.env.testing` so tests execute jobs inline unless they explicitly assert
dispatching.
