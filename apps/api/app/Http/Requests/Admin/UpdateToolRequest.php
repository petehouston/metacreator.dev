<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateToolRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:220'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tier' => ['sometimes', 'in:free,account,premium'],
            'status' => ['sometimes', 'in:draft,published,hidden,deprecated'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'category_id' => ['sometimes', 'integer', 'exists:tool_categories,id'],
            'platforms' => ['sometimes', 'array', 'max:12'],
            'platforms.*' => ['string', 'max:40'],
            'config' => ['sometimes', 'array'],
            'config.runs_per_day' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],

            // `key` binds the row to its runner and `version` retires the result
            // cache — neither is a thing to change from a form. Rejecting them here
            // gives a clear 422 rather than a broken tool nobody notices until a run.
            'key' => ['prohibited'],
            'slug' => ['prohibited'],
            'version' => ['prohibited'],
            'input_schema' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'key.prohibited' => 'A tool key binds the catalog row to its runner and cannot be changed.',
            'slug.prohibited' => 'Changing a slug breaks every link and search result pointing at it. Ship a redirect instead.',
            'version.prohibited' => 'Bump the version from a deploy — it retires the cached results of every run.',
            'input_schema.prohibited' => 'The input schema is owned by the runner, which generates the form from it.',
        ];
    }
}
