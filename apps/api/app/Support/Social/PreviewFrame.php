<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\Domain\Tools\Data\ToolResult;
use App\Support\Text\TextWidth;

/**
 * One platform mock-up, described in data.
 *
 * Preview tools answer a visual question — "is my post cut off, and does the cut
 * hurt?" — and a table of fold positions answers it badly. A frame carries enough
 * structure for the frontend to draw the post the way the platform draws it, while
 * keeping every platform fact (folds, limits, card shapes) here in PHP where it is
 * testable and cacheable.
 *
 * Frames are consumed by {@see ToolResult::socialPreview()} and rendered by the
 * shared `preview.social` renderer, so a new preview tool needs no frontend code.
 */
final class PreviewFrame
{
    /** @var array<string, mixed> */
    private array $frame;

    private function __construct(string $platform, string $surface, string $kind)
    {
        $this->frame = ['platform' => $platform, 'surface' => $surface, 'kind' => $kind];
    }

    /**
     * @param  string  $platform  Styling token: facebook, instagram, linkedin, x, threads, pinterest, generic.
     * @param  string  $surface  What is being drawn, e.g. "Mobile feed".
     * @param  string  $kind  post | profile | channel | link-card | pin | safe-zone | serp | inbox
     */
    public static function make(string $platform, string $surface, string $kind = 'post'): self
    {
        return new self($platform, $surface, $kind);
    }

    public function author(string $name, ?string $meta = null, ?string $handle = null): self
    {
        $this->frame['author'] = array_filter([
            'name' => $name,
            'handle' => $handle,
            'meta' => $meta,
            'initials' => self::initials($name),
        ], fn ($value) => $value !== null && $value !== '');

        return $this;
    }

    /**
     * Split the text at the platform's fold.
     *
     * `$fold` is a grapheme count, not a byte or codepoint count, so an emoji costs
     * one character here exactly as it does in the app.
     */
    public function body(string $text, ?int $fold = null, string $moreLabel = '… more'): self
    {
        $count = PostLength::graphemeCount($text);
        $cut = $fold !== null && $count > $fold;

        $this->frame['body'] = [
            'visible' => $cut ? mb_substr($text, 0, $fold) : $text,
            'hidden' => $cut ? mb_substr($text, $fold) : '',
            'full' => $text,
            'more_label' => $moreLabel,
            'characters' => $count,
        ];

        return $this;
    }

    /**
     * The image or video slot.
     *
     * With no `$url` this is a placeholder drawn at the platform's aspect ratio,
     * which is all a preview of *your unpublished draft* can honestly show. With
     * one it is that picture — the thumbnail on the page under test, the artwork a
     * podcast publishes — because once a real image exists, drawing a grey box
     * beside a verdict about it is withholding the evidence.
     */
    public function media(string $aspect, ?string $label = null, ?string $url = null): self
    {
        $this->frame['media'] = array_filter([
            'aspect' => $aspect,
            'label' => $label,
            'url' => $url,
        ], fn ($value) => $value !== null && $value !== '');

        return $this;
    }

    /**
     * The real width, in CSS pixels, of the surface being drawn.
     *
     * A fold is a width, so a frame that models one is only honest at the width it
     * was measured at: Google's desktop result column is 600 px and its phone
     * column is not, and a preview that draws both in whatever space the grid
     * happens to give it has quietly discarded the fact it exists to show. The
     * renderer lays the frame out at this width and scales the whole thing down to
     * fit its column, so the proportions survive a narrow screen.
     */
    public function device(int $width, ?string $label = null): self
    {
        $this->frame['device'] = array_filter([
            'width' => $width,
            'label' => $label,
        ], fn ($value) => $value !== null && $value !== '');

        return $this;
    }

    /**
     * The frame's own headline — a search result's blue link, a subject line — split
     * at its fold the way {@see self::body()} splits the text under it.
     *
     * Pre-split rather than measured here, because these surfaces cut on pixel
     * width rather than on a character count and the measuring belongs to whoever
     * knows the font size ({@see TextWidth}).
     */
    public function headline(string $visible, string $hidden = '', string $moreLabel = '…'): self
    {
        $this->frame['heading'] = self::split($visible, $hidden, $moreLabel);

        return $this;
    }

    /** {@see self::body()}, for a body that was already split on width. */
    public function bodyParts(string $visible, string $hidden = '', string $moreLabel = '…'): self
    {
        $this->frame['body'] = self::split($visible, $hidden, $moreLabel);

        return $this;
    }

    /**
     * A layout variant within a kind.
     *
     * An inbox row is stacked on a phone and run on one line in a desktop client,
     * and that difference is a fact about the client rather than about the width it
     * happens to be drawn at — so it is named here instead of being inferred from
     * the pixel count downstream.
     */
    public function variant(string $variant): self
    {
        $this->frame['variant'] = $variant;

        return $this;
    }

    /**
     * The identity line above a search result: site name, favicon and the crumb
     * trail Google draws in place of the raw URL.
     */
    public function search(string $site, string $url, ?string $favicon = null): self
    {
        $this->frame['search'] = array_filter([
            'site' => $site,
            'url' => $url,
            'favicon' => $favicon,
        ], fn ($value) => $value !== null && $value !== '');

        return $this;
    }

    /**
     * The attached link card.
     *
     * @param  string  $style  large (1.91:1 image above the text) or small (square thumbnail beside it)
     */
    public function link(
        string $domain,
        ?string $title = null,
        ?string $description = null,
        string $style = 'large',
        ?string $image = null,
    ): self {
        $this->frame['link'] = array_filter([
            'domain' => $domain,
            'title' => $title,
            'description' => $description,
            'style' => $style,
            'image' => $image,
        ], fn ($value) => $value !== null && $value !== '');

        return $this;
    }

    /**
     * Real artwork for a `channel` frame: the banner behind the header and the
     * avatar over it.
     *
     * Unlike {@see self::media()}, which draws a placeholder at an aspect ratio,
     * these are the channel's own images loaded from Google's CDN — a monetization
     * or profile card is only recognisable as *that* channel if it looks like it.
     */
    public function artwork(?string $banner = null, ?string $avatar = null): self
    {
        $this->frame['artwork'] = array_filter([
            'banner' => $banner,
            'avatar' => $avatar,
        ], fn ($value) => $value !== null && $value !== '');

        return $this;
    }

    /** The frame's one outbound link, drawn as a button. */
    public function cta(string $label, string $url): self
    {
        $this->frame['cta'] = ['label' => $label, 'url' => $url];

        return $this;
    }

    /** The interaction row under a post, e.g. "Like · Comment · Share". */
    public function actions(string ...$actions): self
    {
        $this->frame['actions'] = array_values($actions);

        return $this;
    }

    /**
     * The verdict badge on the frame.
     *
     * @param  string  $tone  ok | warn | danger
     */
    public function status(string $tone, string $label): self
    {
        $this->frame['status'] = ['tone' => $tone, 'label' => $label];

        return $this;
    }

    /** One line under the frame explaining what the reader is looking at. */
    public function note(string $note): self
    {
        $this->frame['note'] = $note;

        return $this;
    }

    /** Extra label/value pairs drawn beneath the mock-up. */
    public function detail(string $label, string $value): self
    {
        $this->frame['details'][] = ['label' => $label, 'value' => $value];

        return $this;
    }

    /** Margins the app's own chrome covers, in pixels on the given canvas. */
    public function safeZone(int $width, int $height, int $top, int $bottom, int $left, int $right): self
    {
        $this->frame['canvas'] = [
            'width' => $width,
            'height' => $height,
            'top' => $top,
            'bottom' => $bottom,
            'left' => $left,
            'right' => $right,
        ];

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->frame;
    }

    /**
     * @return array<string, mixed>
     */
    private static function split(string $visible, string $hidden, string $moreLabel): array
    {
        return [
            'visible' => $visible,
            'hidden' => $hidden,
            'full' => $visible.$hidden,
            'more_label' => $moreLabel,
            'characters' => PostLength::graphemeCount($visible.$hidden),
        ];
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '·';
        }

        $first = mb_strtoupper(mb_substr($words[0], 0, 1));

        return count($words) === 1 ? $first : $first.mb_strtoupper(mb_substr(end($words), 0, 1));
    }
}
