<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAvatarRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `image` plus an explicit mime list: the extension is a claim, the
            // decoded content is the fact. SVG is excluded on purpose — it is a script
            // vector, not an image format, when served from your own origin.
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=64,min_height=64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avatar.max' => 'Avatars must be 4 MB or smaller.',
            'avatar.dimensions' => 'Avatars must be at least 64×64 pixels.',
        ];
    }
}
