---
{
 "id": "LK-07",
 "slug": "social-media-deep-links",
 "title": "Social Media Deep Links: Open a Link in the App, Not the Browser",
 "excerpt": "A universal link opens the app when it is installed and the website when it is not. A scheme URI opens the app or does nothing at all — silently.",
 "category": "growth",
 "categories": [],
 "tags": ["short-links", "link-previews", "how-to"],
 "primary_keyword": "social media deep link",
 "status": "draft",
 "is_featured": false,
 "allow_comments": true,
 "seo": {
  "title": "Social Media Deep Links: Open a Profile in the App, Not the Browser",
  "description": "How to deep link to a social media profile or post: universal links versus scheme URIs, which to use where, and the silent failure that catches everyone.",
  "focus_keyword": "social media deep link",
  "canonical_url": null,
  "robots": "index,follow",
  "og_title": "Two kinds of deep link, one silent failure",
  "og_description": "instagram:// opens the app or does nothing at all. https:// always works. Use the right one.",
  "twitter_card": "summary_large_image",
  "schema_type": "BlogPosting"
 }
}
---
A social media deep link — one that opens a profile, a video or a Pin inside the app —
comes in two forms that behave nothing alike. A **universal link** is the ordinary `https://` URL and it opens
the app when it is installed and the website when it is not. A **scheme URI** like
`instagram://user?username=nasa` opens the app or does absolutely nothing.

[[tool:app-deep-link-builder]]

## The two kinds of social media deep link

Both iOS and Android let an app claim its own domain. When it does, tapping
`https://instagram.com/nasa` on a phone with Instagram installed opens Instagram at that
profile, and on a phone without it opens the website.

It cannot fail. There is no case where a universal link leaves the user staring at a tap
that did nothing, which is why it belongs in a bio, a newsletter, a QR code, a printed
flyer and every button on a web page. Apple's
[universal links documentation](https://developer.apple.com/ios/universal-links/)
describes the mechanism from the app's side; from yours it is simply the normal link,
working properly.

The common mistake is assuming the ordinary link is the *weak* option and that a
"proper" deep link needs a custom scheme. It is the other way round.

## Scheme URIs: what they are for, and how they fail

A scheme URI addresses the app directly, skipping the browser hand-off. That makes it
marginally faster and, more importantly, it is what you use **inside your own app**,
where you already know what is installed.

Its failure mode is the problem. With the app missing, a scheme URI produces no error,
no fallback and no page — just a tap that appears broken. On a desktop browser it fails
for the same reason: there is no app to hand it to.

So the rule is straightforward. Public page, bio, email, QR code, anything a stranger
might tap: universal link. Inside a native app you control, or a link-in-bio tool that
tests for the app first: scheme URI is available to you.

## The schemes worth knowing

The ones that have been stable long enough to rely on:

| Platform | Scheme |
| --- | --- |
| Instagram profile | `instagram://user?username=NAME` |
| X profile | `twitter://user?screen_name=NAME` |
| X post | `twitter://status?id=ID` |
| YouTube video | `vnd.youtube://VIDEO_ID` |
| Facebook (anything) | `fb://facewebmodal/f?href=ENCODED_URL` |
| Pinterest profile | `pinterest://user/NAME` |
| Twitch channel | `twitch://stream/NAME` |
| Telegram | `tg://resolve?domain=NAME` |

Two notes on that table. **X is still `twitter://`** — the app kept its original scheme
through the rename, and hand-written X deep links fail on this more than on anything
else. And Facebook's route takes the full web URL rather than an identifier, which is
the only Facebook deep link that does not require a numeric page id nobody has to hand.

Where a platform has no long-established scheme for a given object, the
[app deep link builder](/tools/app-deep-link-builder) says so rather than inventing one.
A URI that silently opens nothing is worse than no URI at all, and it is precisely the
kind of thing you cannot test by looking at it.

## Build one

1. Paste the ordinary web link to the profile, post, video or Pin.
2. Take the **universal link** row for anything public.
3. Take the **scheme URI** row only if you control the environment it runs in.
4. The HTML snippet is the universal link, ready to paste into a page.

The builder strips tracking before it constructs anything, because a deep link carrying
somebody's `igshid` is a deep link that identifies them — see
[removing tracking from links](/blog/remove-tracking-parameters-from-url) for what each of those
parameters is.

## Testing one, and why you have to

A deep link is the rare piece of markup you cannot verify by reading it. A scheme URI
that is subtly wrong looks identical to one that works, and it fails without saying
anything.

So test on a real device, in both states:

1. **With the app installed.** Does it land on the right screen, or does it open the app
   at its home tab? The second is a common half-failure — the scheme is right, the
   identifier is not.
2. **With the app not installed.** A universal link should show the website. A scheme URI
   will do nothing, which is the behaviour you are checking you can live with.
3. **On a desktop browser**, if the link will ever appear on a page somebody might open
   on a laptop.

Simulators and desktop browsers cannot answer the first two questions honestly, because
the app is not really there. This is a phone-in-hand test.

## The places this actually comes up

**Link-in-bio pages.** The whole point is a tap that lands in the right app. Universal
links do this correctly; a page full of scheme URIs breaks for every visitor on a
desktop.

**QR codes on print.** Scanned by a phone camera, which hands the URL to the browser
first. A scheme URI here fails on a large share of scans. Generate the code from the
universal link with the [QR code generator](/tools/qr-code-generator).

**Email.** Mail clients handle `https://` and are unpredictable with custom schemes.
Universal link, every time.

**Your own app.** The one place a scheme URI is the better answer.

:::faq
Q: What is a deep link on social media?
A: A link that opens a specific profile, post or video inside the app rather than at the platform's home screen. The ordinary https URL usually is one.
Q: Why does my instagram:// link do nothing?
A: Because there is no Instagram app on that device to hand it to. A scheme URI fails silently everywhere the app is missing, including every desktop browser.
Q: Is the X deep link scheme twitter:// or x://?
A: twitter://. The app kept its original scheme through the rename, which is the most common reason a hand-written X deep link fails.
Q: Which link should go in my bio?
A: The universal link — the ordinary https URL. It opens the app for people who have it and the website for everyone else, which is what a public link has to do.
:::

Build both forms from any profile or post link with the
[app deep link builder](/tools/app-deep-link-builder), and turn the universal one into a
scannable code with the [QR code generator](/tools/qr-code-generator).
