<?php

declare(strict_types=1);

namespace App\Domain\Tools\Data;

use App\Domain\Tools\Enums\ResultView;
use App\Support\Social\PreviewFrame;

/**
 * The normalised output of every tool.
 *
 * `view` tells the frontend which shared renderer to use; `data` is shaped to that
 * renderer's contract. Together they are why most tools need no frontend code.
 */
final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<ResultArtifact>  $artifacts
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public ResultView $view,
        public array $data,
        public array $artifacts = [],
        public array $warnings = [],
        public array $meta = [],
        public ?string $summary = null,
    ) {}

    /**
     * @param  list<array{label: string, value: string|int|float, hint?: string, tone?: string}>  $pairs
     */
    public static function keyValue(array $pairs, ?string $summary = null): self
    {
        return new self(ResultView::KeyValue, ['pairs' => $pairs], summary: $summary);
    }

    /**
     * @param  list<array{key: string, label: string, align?: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public static function table(array $columns, array $rows, ?string $summary = null): self
    {
        return new self(ResultView::Table, ['columns' => $columns, 'rows' => $rows], summary: $summary);
    }

    /**
     * @param  list<array{title?: string, body: string, meta?: array<string, mixed>}>  $items
     */
    public static function cards(array $items, ?string $summary = null): self
    {
        return new self(ResultView::ListCards, ['items' => $items], summary: $summary);
    }

    /**
     * @param  list<array{label: string, text: string}>  $blocks
     */
    public static function textBlocks(array $blocks, ?string $summary = null): self
    {
        return new self(ResultView::TextBlocks, ['blocks' => $blocks], summary: $summary);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  list<array{severity: string, title: string, detail: string}>  $fixes
     */
    public static function score(int $overall, array $sections, array $fixes = [], ?string $summary = null): self
    {
        return new self(
            ResultView::ScoreReport,
            ['overall' => $overall, 'sections' => $sections, 'fixes' => $fixes],
            summary: $summary,
        );
    }

    /**
     * @param  list<string>  $variants
     * @param  list<array{label: string, value?: mixed, variants?: array<string, mixed>}>  $rows
     */
    public static function compare(array $variants, array $rows, ?string $summary = null): self
    {
        return new self(ResultView::DiffCompare, ['variants' => $variants, 'rows' => $rows], summary: $summary);
    }

    /**
     * Platform-accurate mock-ups, built with {@see PreviewFrame}.
     *
     * `$table` is optional supporting evidence — the tags, limits or margins behind
     * the picture — drawn under the frames in the same table renderer.
     *
     * @param  list<array<string, mixed>>  $frames
     * @param  array{columns: list<array{key: string, label: string, align?: string}>, rows: list<array<string, mixed>>}|null  $table
     */
    public static function socialPreview(array $frames, ?string $summary = null, ?array $table = null): self
    {
        return new self(
            ResultView::SocialPreview,
            array_filter(['frames' => $frames, 'table' => $table], fn ($value) => $value !== null),
            summary: $summary,
        );
    }

    /** @param  list<ResultArtifact>  $artifacts */
    public static function media(array $artifacts, ?string $summary = null): self
    {
        return new self(ResultView::MediaGallery, [], $artifacts, summary: $summary);
    }

    /** @param  list<string>  $warnings */
    public function withWarnings(array $warnings): self
    {
        return new self(
            $this->view,
            $this->data,
            $this->artifacts,
            [...$this->warnings, ...$warnings],
            $this->meta,
            $this->summary,
        );
    }

    /** @param  array<string, mixed>  $meta */
    public function withMeta(array $meta): self
    {
        return new self(
            $this->view,
            $this->data,
            $this->artifacts,
            $this->warnings,
            [...$this->meta, ...$meta],
            $this->summary,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'view' => $this->view->value,
            'summary' => $this->summary,
            'data' => $this->data,
            'artifacts' => array_map(fn (ResultArtifact $a) => $a->toArray(), $this->artifacts),
            'warnings' => $this->warnings,
            'meta' => $this->meta,
        ];
    }
}
