# 01 — Product Overview

## The problem

Creators run their presence across five or six networks at once. The tooling they use is scattered
across dozens of ad-choked single-purpose sites with inconsistent quality, no history, and no way to
compare results over time. Professional suites exist but start at $99/month and are built for
agencies, not individuals.

## The product

**MetaCreator.Dev is one clean home for the small tools creators actually use every day**, with a
free tier generous enough to be someone's default bookmark, and a premium tier that unlocks the
tools that cost us real money to run (API quota, compute, storage).

### Positioning statement

> For independent creators and small social teams who juggle multiple platforms, MetaCreator.Dev is
> a creator toolkit that replaces a folder of sketchy single-use sites with one fast, private,
> professional workspace — free to start, no account needed to try.

## Audience

| Segment | Needs | Where they enter |
| --- | --- | --- |
| **Aspiring creator** (0–1k followers) | Free utilities, learning, ideas | Organic search on a tool keyword |
| **Growing creator** (1k–100k) | Analytics, scheduling helpers, thumbnail/hook testing | Blog content, tool cross-links |
| **Professional creator** (100k+) | Bulk operations, exports, history, API quota | Referral, comparison content |
| **Small social team / agency** | Multi-account workflows, shared exports | Pricing page, sales enquiry |

## Access model

Three tiers, enforced identically on the server and mirrored in the UI:

| Tier | Requirement | Intent |
| --- | --- | --- |
| `free` | Nothing — usable by anonymous visitors | Acquisition. Ranks in search, proves quality, costs us little |
| `account` | A free registered account | Conversion to a known user. Adds history, saved results, higher limits |
| `premium` | An active paid subscription | Monetisation. Tools with real marginal cost or high professional value |

Access is a **server-side decision** made by a single `ToolAccessService`; the frontend never gates
anything it hasn't been told about. Admins can override the tier for an individual user with a
**grant** (time-boxed, audited) — used for support, trials, partnerships and influencer seeding.

See [08 — Tool engine](08-tool-engine.md) for enforcement details.

## Monetisation

| Plan | Price (launch) | Notes |
| --- | --- | --- |
| Free | $0 | Free + account tools, 20 runs/day, 7-day history |
| **Pro — 7-day pass** | $9 one-off | Non-renewing. Removes the "monthly commitment" objection; converts strongly from tool paywalls |
| **Pro — Monthly** | $19/mo | All tools, 1,000 runs/day, unlimited history, exports |
| **Pro — Yearly** | $180/yr (2 months free) | Best margin, best retention. Default-highlighted on pricing |

The 7-day pass is deliberately the wedge: a creator hits a premium tool mid-task, and $9 to finish
the job right now is an easier decision than a subscription. Post-expiry we run a targeted upgrade
sequence — see [14 — Newsletter & marketing](14-newsletter-marketing.md).

## Funnel and conversion strategy

```
Search ──▶ Tool page (free) ──▶ Result ──▶ "Save this / raise your limit" ──▶ Account
                                    │
                                    └──▶ Premium tool attempt ──▶ Paywall ──▶ 7-day pass ──▶ Monthly
```

Deliberate design choices that serve this funnel:

1. **No signup wall on free tools.** The value is delivered before anything is asked.
2. **Result-anchored upsell.** The upgrade prompt appears *next to a real result the user just
   produced*, referencing what they'd additionally get — never as an interstitial.
3. **Every tool page is a landing page.** Instructions, a worked example, FAQ, JSON-LD, and related
   tools — each page must be able to rank and convert on its own.
4. **Cross-linking density.** Related tools + relevant blog posts on every tool; relevant tools on
   every post. This is the whole internal-linking strategy ([16 — SEO](16-seo.md)).

## Success metrics

| Metric | Definition | Launch target |
| --- | --- | --- |
| Activation | % of first-time visitors who complete ≥1 tool run | 45% |
| Account conversion | % of tool-runners who create an account within 7 days | 8% |
| Paid conversion | % of accounts that start any paid plan within 30 days | 4% |
| Pass→sub upgrade | % of 7-day passes converting to monthly/yearly | 25% |
| Retention | % of paying users active at day 90 | 65% |
| Tool health | p95 tool run latency | < 2.5 s |

Every one of these is derived from the internal telemetry described in
[15 — Analytics & tracking](15-analytics.md), not from a third-party script we don't control.

## Non-goals (v1)

- Posting/publishing on the user's behalf (OAuth write scopes, review burden, platform risk).
- A mobile app. The web app is responsive and installable; native comes only with demand.
- Team seats and shared workspaces. Designed for, but not built in v1 — see [23 — Roadmap](23-roadmap.md).
- Anything that requires scraping a network in violation of its terms. See [21 — Security](21-security.md).
