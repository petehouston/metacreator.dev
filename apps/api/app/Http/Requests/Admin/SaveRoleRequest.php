<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Access\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveRoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'prohibited',
                'string', 'max:60', 'regex:/^[a-z][a-z0-9-]*$/',
                Rule::unique('roles', 'name'),
            ],
            'permissions' => ['present', 'array'],
            // Against the catalog rather than the table: a permission row that
            // survived a rename would otherwise be assignable and check nothing.
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use lowercase words separated by hyphens, e.g. editor-restricted.',
            'name.prohibited' => 'A role cannot be renamed — code and audit history reference it by name.',
            'permissions.*.in' => 'That permission is not declared in the catalog.',
        ];
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_values(array_unique($this->validated()['permissions'] ?? []));
    }
}
