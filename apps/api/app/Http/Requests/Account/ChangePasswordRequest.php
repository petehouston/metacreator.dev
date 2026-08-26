<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user();

        return [
            // Accounts created by magic link or Google have no password yet, so there
            // is nothing to confirm — requiring it would lock them out of ever setting one.
            'current_password' => [
                $user instanceof User && $user->password !== null ? 'required' : 'nullable',
                'string',
                'current_password',
            ],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults(), 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'That is not your current password.',
            'password.different' => 'Your new password must be different from your current one.',
        ];
    }
}
