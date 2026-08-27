<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'in:active,suspended,pending'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'timezone', 'max:64'],
            'marketing_opt_in' => ['sometimes', 'boolean'],

            // Email is the account identity and is immutable — for staff too. A
            // transfer is a separate, audited action, not a field edit (docs/06).
            'email' => ['prohibited'],
            // Roles carry `roles.manage`, which an admin deliberately does not hold.
            // Accepting them here would route around that separation.
            'roles' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.prohibited' => 'Email addresses are immutable. Use an audited account transfer instead.',
            'roles.prohibited' => 'Roles are assigned through the roles endpoint, which requires roles.manage.',
            'password.prohibited' => 'Staff cannot set a password on behalf of a user. Send a reset instead.',
        ];
    }
}
