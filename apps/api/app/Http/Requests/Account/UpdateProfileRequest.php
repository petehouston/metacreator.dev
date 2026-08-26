<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'timezone' => ['sometimes', 'string', 'timezone', 'max:64'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'marketing_opt_in' => ['sometimes', 'boolean'],

            // Email is the account identity and is immutable (docs/06). Rejecting it
            // here gives a clear 422 instead of the model's LogicException 500.
            'email' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.prohibited' => 'Your email address cannot be changed. Contact support to transfer your account.',
        ];
    }
}
