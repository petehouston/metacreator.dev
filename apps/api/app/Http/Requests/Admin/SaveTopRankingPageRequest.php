<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\TopRanking\Enums\RankingPlatform;
use App\Domain\TopRanking\Models\TopRankingPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating and updating a ranking page.
 *
 * One class for both, `sometimes` doing the work on update — the shape the rest of
 * the admin writers use. Authorisation is the route's permission middleware.
 */
final class SaveTopRankingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $page = $this->route('page');
        $creating = ! $page instanceof TopRankingPage;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('top_ranking_pages', 'slug')->ignore($page),
            ],
            'platform' => [$creating ? 'required' : 'sometimes', Rule::enum(RankingPlatform::class)],

            'metric_label' => ['sometimes', 'string', 'max:40'],
            // The unit interprets the number the article publishes. `exact` is for
            // the lists that print a full count; the other two for the far more
            // common "515" under a header reading "(millions)".
            'metric_unit' => ['sometimes', Rule::in(['exact', 'thousands', 'millions', 'billions'])],
            'secondary_metric_label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'secondary_metric_unit' => ['sometimes', 'nullable', Rule::in(['exact', 'thousands', 'millions', 'billions'])],

            'intro' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // The same block the tool editor sends. Every field optional: the
            // public page falls back to the page's own title and intro, so blank
            // is the right answer for a ranking nobody has hand-tuned.
            'seo' => ['sometimes', 'array'],
            'seo.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo.canonical_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'seo.robots' => ['sometimes', 'nullable', Rule::in([
                'index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow',
            ])],
            'seo.focus_keyword' => ['sometimes', 'nullable', 'string', 'max:120'],
            'seo.og_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.og_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo.og_media_id' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'seo.twitter_card' => ['sometimes', 'nullable', Rule::in(['summary', 'summary_large_image'])],
            'seo.schema_type' => ['sometimes', 'nullable', 'string', 'max:60'],

            'source_page' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'source_table' => ['sometimes', 'integer', 'min:0', 'max:20'],
            // Capped at 250 because this is a table a human reads, and because a
            // higher number would not find more rows — no article here publishes one.
            'row_limit' => ['sometimes', 'integer', 'min:1', 'max:250'],

            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'source_page.required' => 'Name the Wikipedia article this page reads, e.g. “List of most-followed TikTok accounts”.',
        ];
    }
}
