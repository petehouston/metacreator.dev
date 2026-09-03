# Vendored fonts

Used by `App\Support\Media\SocialCardCanvas` to draw the tool social cards
(`php artisan tools:social-cards`). GD needs a font file on disk, so these are
committed rather than fetched: generation then works identically on a laptop, in
CI and in the Docker image, and a card cannot silently change typeface because a
CDN moved a file.

| File | Family | Licence |
| --- | --- | --- |
| `DMSans-Regular.ttf`, `DMSans-Bold.ttf` | DM Sans — the site's own typeface (`apps/web/src/app/layout.tsx`) | SIL OFL 1.1 (`OFL.txt`) |
| `JetBrainsMono-Regular.ttf`, `JetBrainsMono-Bold.ttf` | JetBrains Mono — the URL bar and figures | SIL OFL 1.1 |

Static instances, not the variable files: GD renders a variable font at its
default instance only, which would silently drop every bold weight on the card.

Replacing a face means redrawing every card — `php artisan tools:social-cards
--all --force` — since the artwork carries the type.
