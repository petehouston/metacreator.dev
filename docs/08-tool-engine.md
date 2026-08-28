# 08 — Tool Engine

The engine exists so that adding the 60th tool is as cheap as adding the 6th. A tool is *data* (a
catalog row with a JSON Schema) plus *one class* (a runner). Everything else — routing, validation,
form UI, access control, quotas, caching, telemetry, result rendering — is shared.

## The contract

```php
namespace App\Domain\Tools\Contracts;

interface ToolRunner
{
    /** Registry key, matches tools.key */
    public static function key(): string;

    /** JSON Schema for the input; drives the generated form AND server validation */
    public function inputSchema(): array;

    /** Execute. Must be pure w.r.t. its input: same input + version ⇒ same output */
    public function run(ToolInput $input, RunContext $context): ToolResult;
}
```

Optional capability interfaces, each opting into extra behaviour:

| Interface | Effect |
| --- | --- |
| `Cacheable` | Result cached in Redis under `tool:{key}:v{version}:{sha256(input)}`, TTL from `cacheTtl()` |
| `Queueable` | Never runs inline; dispatched to the `tools` queue and polled |
| `AcceptsFiles` | Declares accepted mimes/size; input arrives as a `UploadedAsset` |
| `ProducesArtifacts` | Result files are written to Spaces and returned as signed, expiring URLs |
| `UsesProvider` | Declares external providers so the circuit breaker and quota accounting apply |
| `Streamable` | Emits partial results over SSE for long-running tools |

`ToolResult` is a small value object: `status`, `data` (renderer-agnostic), `artifacts[]`,
`warnings[]`, `meta` (duration, cache hit, provider calls).

## Result shapes

Rather than a bespoke React component per tool, results declare a **view type**. The frontend has
one renderer per view type, so most tools need no frontend code at all:

| View type | Used by |
| --- | --- |
| `keyvalue` | Calculators, counters |
| `table` | Extractors, bulk checks |
| `list.cards` | Idea/hashtag/hook generators |
| `text.blocks` | Descriptions, captions, scripts (with per-block copy buttons) |
| `media.gallery` | Downloaders, resizers, croppers |
| `score.report` | Audits and scores (headline gauge + weighted sub-scores + fixes) |
| `chart.timeseries` | Growth trackers |
| `diff.compare` | A/B testers |
| `download.bundle` | Zip exports |
| `preview.social` | Post, profile, channel, link card, Pin and safe-zone mock-ups |

### `preview.social`

The preview view is the one worth spelling out, because it is the one that could easily have become
sixty bespoke components. A result carries a list of **frames**, each describing one mock-up in
data: the platform it is styled as, the surface it represents ("Mobile app", "Pin closeup"), its
kind (`post`, `profile`, `channel`, `link-card`, `pin`, `safe-zone`), and then whichever parts apply
— author row, body split at the fold, media placeholder at the right aspect, link card, action row,
a status badge, and the margins an app's chrome covers.

A `channel` frame is the exception to the placeholder rule: it carries `artwork` (real banner and
avatar URLs from the platform's own CDN) and a `cta` button out to the channel. A verdict about a
channel is only trustworthy once you can see, above it, that the tool looked at the channel you
meant.

Frames are built with `App\Support\Social\PreviewFrame`, so every platform fact — where the fold
falls, which card shape a `twitter:card` value produces, how wide TikTok's right rail is — is
decided in PHP where it is testable and cacheable. The renderer only draws what it is told, which is
why a new preview tool still needs no frontend code. A frame may also carry a supporting `table`:
the tags, limits or margins behind the picture, drawn underneath it.

The body arrives pre-split into `visible` and `hidden` at a **grapheme** count, not a byte or
codepoint count, so an emoji costs one character exactly as it does in the app. The renderer greys
the hidden half in place rather than dropping it: seeing the sentence that gets cut is the point.

A tool only needs custom UI when it is genuinely interactive (grid planner, carousel splitter,
thumbnail A/B tester). Those register a component in `apps/web/src/tools/custom/<key>.tsx` and the
generic path picks it up automatically.

## Execution pipeline

```
RunToolAction::handle(ToolRunRequest)
 ├─ 1. resolve tool (published + visible, else 404)
 ├─ 2. ToolAccessService::authorize → AccessDecision{allowed, reason}   ← [06]
 ├─ 3. QuotaService::consume(actor, tool)                              ← Redis token bucket
 ├─ 4. validate input against inputSchema (Opis JSON Schema)
 ├─ 5. normalise + hash input
 ├─ 6. cache lookup (if Cacheable)          ── hit ─▶ record run, return
 ├─ 7. runner->run() inline, or dispatch RunToolJob (if Queueable / slow)
 ├─ 8. wrap provider calls in circuit breaker + timeout
 ├─ 9. persist artifacts to Spaces
 ├─ 10. record ToolRun (queued write, never blocks the response)
 └─ 11. return ToolResult
```

Steps 2, 3 and 10 are non-negotiable and live in the action, not in runners. A runner that forgets
to check access is impossible, because a runner has no way to be invoked except through the action.

## Quotas and rate limits

Two independent mechanisms:

| Mechanism | Window | Keyed by | Purpose |
| --- | --- | --- | --- |
| **Rate limit** | 60 s sliding | IP + tool (guests), user + tool (members) | Abuse and accidental loops |
| **Quota** | Rolling 24 h token bucket | visitor hash or user id | Cost control, tier differentiation |

Defaults, overridable per tool:

| Actor | Runs/day | Concurrent runs | Burst/min |
| --- | --- | --- | --- |
| Anonymous | 10 | 1 | 5 |
| Free account | 50 | 2 | 15 |
| 7-day pass | 300 | 4 | 30 |
| Pro monthly/yearly | 1,000 | 8 | 60 |
| Granted tool | Uses the actor's tier bucket | | |

Exceeding a quota returns `tool.quota_exceeded` with `details.resets_at` and, for free actors, an
upgrade CTA payload the frontend renders inline — the single most important conversion surface in
the product.

## Caching

Cache key: `tool:{key}:v{tool.version}:{sha256(canonical_json(input))}`. Bumping `tools.version`
invalidates every cached result for that tool without touching Redis. Cache hits are recorded on the
run so we can measure real vs. served compute, and free-tier cache hits do **not** consume quota.

## External providers

Every provider (YouTube Data API, Instagram Graph, etc.) is wrapped:

```php
$this->provider('youtube')->call(
    fn () => $this->client->videos($id),
    timeout: 5.0,
    retries: 2,
    fallback: fn () => throw new ToolUnavailable('youtube'),
);
```

The wrapper enforces a per-provider concurrency limit, an hourly quota budget shared across workers
(Redis counter), a circuit breaker that opens after 5 failures in 60 s and half-opens after 30 s,
and structured logging of every call for cost attribution. Provider credentials live in settings
(encrypted), not in code.

**Compliance:** tools only use official APIs or publicly available page metadata. A tool that would
require violating a platform's terms is not built — the catalog entry is dropped, not hidden.

## Instructions and examples

Instructions and the worked example are stored as **block JSON**, the same format as blog posts, and
rendered by the same renderer. Consequences that matter:

- Editors write tool documentation in the exact editor they already use for posts.
- Documentation supports embeds, code blocks, callouts and images with no extra work.
- Tool pages get real, indexable prose — which is what makes them rank.

Each tool also stores `example.input`, so the page can offer **"Try it with sample data"** — a single
click that fills the form and runs it. This is the highest-converting element on a tool page.

## Related tools

`related_tools` is computed nightly and stored, blending three signals:

1. Same category and shared platforms (structural).
2. Co-run within a session by other users (behavioural, the strongest signal).
3. Manual pins by an admin (editorial override, always ranked first).

## Admin controls

| Control | Effect |
| --- | --- |
| Show/hide | `is_visible=false` → 404 on the public site, still runnable by staff for testing |
| Tier change | Takes effect immediately; in-flight runs finish |
| Deprecate | Page stays with a banner and a `Deprecation` header, points to a successor |
| Feature | Pins to the catalog hero |
| Grant | Give a specific user access to a specific tool, optionally time-boxed |
| Kill switch | Per-tool and per-provider; disables execution instantly without a deploy |
