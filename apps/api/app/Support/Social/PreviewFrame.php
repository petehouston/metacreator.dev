<?php

declare(strict_types=1);

namespace App\Support\Social;

use App\Domain\Tools\Data\ToolResult;

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
     * @param  string  $kind  post | profile | channel | link-card | pin | safe-zone
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

    /** A placeholder for the image or video, drawn at the aspect ratio the platform uses. */
    public function media(string $aspect, ?string $label = null): self
    {
        $this->frame['media'] = array_filter(['aspect' => $aspect, 'label' => $label]);

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
