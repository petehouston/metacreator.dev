# 14 — Newsletter & Marketing

## Provider abstraction

The admin picks a provider in settings; nothing else in the codebase knows which one is active.

```php
interface NewsletterProvider
{
    public function subscribe(SubscriberData $data): SubscriptionResult;
    public function unsubscribe(string $email): void;
    public function tag(string $email, array $tags): void;
    public function lists(): array;          // for the admin picker
    public function healthCheck(): ProviderHealth;
}
```

Adapters shipped: **Mailchimp**, **MailerLite**, **Moosend**, **Sendy**, **Brevo**, plus `Local`
(stores in `newsletter_subscribers` only) and `Null` (dev). Adding one is a single class plus a
settings schema entry.

Every subscribe writes to the **local** `newsletter_subscribers` table first, then syncs to the
provider on a queue with retries. If the provider is down or the credentials are wrong, no signup is
ever lost, and a `newsletter:resync` command replays failures.

## Compliance

- **Double opt-in by default** (togglable per provider capability): a confirmation email with a
  signed link, and no data leaves for the provider until confirmation.
- Consent record stored: timestamp, IP, source page, form variant, and the exact consent text shown.
- Unsubscribe honoured locally *and* pushed to the provider; a suppressed email cannot be re-added
  by a form submission.
- GDPR export and erasure cover newsletter data ([21](21-security.md)).

## Capture placements

| Placement | Form | Rationale |
| --- | --- | --- |
| Blog post, after ~60% scroll | Inline, contextual to the post's category | Highest-intent moment |
| Blog index, after the first row | Card in the grid | Non-intrusive, visually native |
| Tool result panel | "Get new tools + tips weekly" | The user just got value |
| Footer, sitewide | Single field | Baseline coverage |
| Exit-intent, desktop, ≥2 pages, once per 30 days | Modal | Used sparingly and never on mobile |
| Dedicated `/newsletter` page | Full page with archive | Linkable, indexable |

All placements are toggleable in settings; each carries a `source` so we can measure which ones
actually work and kill the rest.

## Lifecycle campaigns

| Trigger | Sequence |
| --- | --- |
| New account | Day 0 welcome + top 5 free tools · Day 2 "the one tool people miss" · Day 5 upgrade case |
| Free user hits a paywall | Immediate: what Pro unlocks, with the specific tool they wanted |
| 7-day pass purchased | Day 0 onboarding · Day 5 "your pass ends in 2 days" · Day 8 upgrade offer |
| Pass expired, no upgrade | Day 3 win-back · Day 14 discount |
| Dormant 30 days | "What's new" digest |
| Cancelled | Exit survey + a resume link |

Sequences live in the newsletter provider where possible; anything that needs product state
(entitlements, run counts) runs as a scheduled Laravel job through the transactional channel.

## Content marketing

The blog is the acquisition engine, not a company diary. Each post targets a keyword cluster and
links to the tools that serve that intent via `toolCard` blocks; each tool page links back to the
posts that explain its use. That reciprocal linking is what compounds
([16 — SEO](16-seo.md)).
