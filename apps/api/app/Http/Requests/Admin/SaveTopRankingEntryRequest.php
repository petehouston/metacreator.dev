<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\TopRanking\Models\TopRankingEntry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a hand-edited row.
 *
 * Deliberately permissive about which fields are present: an editor fixing a
 * misspelled owner should not have to resend a country and a category to do it.
 */
final class SaveTopRankingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Strip the @ an editor will always type.
     *
     * Done here rather than in the controller because it has to hold for creating
     * *and* updating: the handle is what the reconciliation key is built from and
     * what a profile URL is built from, so "@byhand" and "byhand" being two
     * different accounts would produce a duplicate row on the next sync and a link
     * to instagram.com/@byhand that 404s.
     */
    protected function prepareForValidation(): void
    {
        $handle = $this->input('handle');

        if (is_string($handle)) {
            $trimmed = ltrim(trim($handle), '@');

            $this->merge(['handle' => $trimmed === '' ? null : $trimmed]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = ! $this->route('entry') instanceof TopRankingEntry;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'handle' => ['sometimes', 'nullable', 'string', 'max:120'],
            'owner' => ['sometimes', 'nullable', 'string', 'max:200'],

            // `active_url` is deliberately not used: it would make saving a row
            // depend on a network round trip to a platform that may be rate-limiting
            // us, and fail an edit for a reason that has nothing to do with the edit.
            'profile_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],

            'metric_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'secondary_metric_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999'],

            'country' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'language' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:400'],

            // Pasted by hand when a platform will not tell us. Accepted only over
            // https: an http image on an https page is blocked by the browser, so
            // storing one would produce a row that silently shows nothing.
            'avatar_url' => ['sometimes', 'nullable', 'url:https', 'max:1000'],

            'is_pinned' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avatar_url.url' => 'The avatar must be an https:// image address.',
        ];
    }
}
