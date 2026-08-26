<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `rfc` only: a DNS check here would put a network round trip in the
            // signup path, and the verification email already proves deliverability.
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],

            // Policy lives in AppServiceProvider so every password entry point shares it.
            'password' => ['required', 'string', Password::defaults(), 'max:255'],

            'display_name' => ['nullable', 'string', 'max:60'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'string', 'timezone', 'max:64'],
            'locale' => ['sometimes', 'string', 'max:10'],

            // Honeypot: a real browser leaves this empty (docs/06). Named innocuously
            // so a naive bot fills it in.
            'website' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'An account already exists for that email. Try signing in instead.',
            'website.prohibited' => 'That submission looked automated. Please try again.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
