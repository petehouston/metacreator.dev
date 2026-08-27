<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Blog\Actions\SavePostAction;
use App\Domain\Blog\Blocks\BlockSanitizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One request object for create and update.
 *
 * The block document is validated for *shape* only — every block's payload is
 * sanitised by {@see BlockSanitizer}, which is the thing
 * that actually keeps a custom HTML block from becoming an XSS. Validating block
 * data here as well would be a second, weaker copy of that rule.
 */
final class SavePostRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],

            'blocks' => ['sometimes', 'array'],
            'blocks.version' => ['sometimes', 'integer'],
            'blocks.blocks' => ['sometimes', 'array', 'max:500'],
            'blocks.blocks.*.type' => ['required_with:blocks.blocks', 'string', 'max:40'],

            'status' => ['sometimes', 'in:draft,scheduled,published,unpublished,archived'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_for' => ['sometimes', 'nullable', 'date', 'after:now'],

            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:post_categories,id'],
            'featured_media_id' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['integer', 'exists:tags,id'],

            'is_featured' => ['sometimes', 'boolean'],
            'allow_comments' => ['sometimes', 'boolean'],
            'is_autosave' => ['sometimes', 'boolean'],

            // Optimistic concurrency token, read by the controller.
            'version' => ['sometimes', 'integer'],

            'seo' => ['sometimes', 'array'],
            'seo.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo.canonical_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'seo.robots' => ['sometimes', 'nullable', 'string', 'max:60'],
            'seo.focus_keyword' => ['sometimes', 'nullable', 'string', 'max:120'],
            'seo.og_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.og_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo.og_media_id' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'seo.twitter_card' => ['sometimes', 'nullable', 'in:summary,summary_large_image'],
            'seo.schema_type' => ['sometimes', 'nullable', 'string', 'max:60'],

            // Authorship is set from the session on create and never reassigned by a
            // form field — an audit trail that can be edited is not an audit trail.
            'author_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scheduled_for.after' => 'A scheduled post has to be scheduled for the future.',
            'author_id.prohibited' => 'Authorship follows the account that created the post.',
        ];
    }

    /**
     * Everything {@see SavePostAction} accepts, with the transport-only fields
     * (`version`) left behind.
     *
     * The block document is taken from the raw input rather than from
     * `validated()`. This is the sharp edge of validating a nested structure
     * partially: `validated()` returns only the keys a rule names, so with a rule
     * on `blocks.blocks.*.type` and none on `blocks.blocks.*.data`, every block
     * reaches the action as `{type: "paragraph"}` — the writing deleted on save,
     * with a 200 and nothing in the logs.
     *
     * Taking the raw value back is safe because the block payload has a real
     * gatekeeper: {@see BlockSanitizer} decides which keys survive per block type,
     * and it runs on every write. Validation here is for shape and size only.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = array_diff_key($this->validated(), array_flip(['version']));

        if ($this->has('blocks')) {
            $payload['blocks'] = (array) $this->input('blocks');
        }

        return $payload;
    }
}
