<?php

declare(strict_types=1);

namespace App\Http\Requests\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `source` and `source_url` are how docs/14 measures which capture placements are
 * worth keeping, so they are accepted but never trusted: both are attacker-supplied
 * and both are stored, hence the length caps matching the columns.
 */
final class SubscribeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:60'],
            'source_url' => ['nullable', 'string', 'max:500'],

            // The form's honeypot. A real person cannot see the field, so anything
            // in it is a bot — rejected as a validation error rather than silently,
            // which keeps the rule visible in one place.
            'company' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
