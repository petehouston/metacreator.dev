<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Changelog\Enums\ChangeType;
use App\Domain\Changelog\Models\ChangelogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a release.
 *
 * The label and tone travel with the value so the type's presentation is decided in
 * {@see ChangeType} and nowhere else — the public
 * timeline and the admin list then cannot disagree about what colour "fixed" is.
 *
 * @mixin ChangelogItem
 */
final class ChangelogItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'tone' => $this->type->tone(),
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
