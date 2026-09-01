<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Changelog\Enums\ChangeType;
use App\Domain\Changelog\Enums\ReleaseStatus;
use App\Domain\Changelog\Models\ChangelogRelease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for creating and updating a release.
 *
 * One request class for both, with `sometimes` doing the work on update — the same
 * shape the tool and taxonomy writers use. Authorisation is the route's permission
 * middleware, so `authorize()` stays true here rather than re-deciding it.
 */
final class SaveChangelogReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $release = $this->route('release');
        $creating = ! $release instanceof ChangelogRelease;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'version' => ['sometimes', 'nullable', 'string', 'max:60'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:160',
                Rule::unique('changelog_releases', 'slug')->ignore($release),
            ],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(ReleaseStatus::class)],
            'released_at' => ['sometimes', 'nullable', 'date'],
            'is_major' => ['sometimes', 'boolean'],

            'items' => [$creating ? 'required' : 'sometimes', 'array', 'max:100'],
            'items.*.type' => ['required', Rule::enum(ChangeType::class)],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Two rules that need to see more than one field at a time.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->input('items');

                // A release with no entries is a date with nothing under it. The
                // action already drops blank rows, so this catches the case where
                // every row was blank — which would otherwise save silently.
                if (is_array($items) && $this->filled('items')) {
                    $filled = array_filter(
                        $items,
                        fn ($item) => is_array($item) && trim((string) ($item['title'] ?? '')) !== '',
                    );

                    if ($filled === []) {
                        $validator->errors()->add(
                            'items',
                            'Add at least one change — a release with no entries has nothing to say.',
                        );
                    }
                }

                // Scheduling is a promise about a future date; without one it is
                // just a draft wearing a different label.
                if ($this->input('status') === ReleaseStatus::Scheduled->value) {
                    $date = $this->date('released_at');

                    if ($date === null || $date->isPast()) {
                        $validator->errors()->add(
                            'released_at',
                            'A scheduled release needs a date in the future. Publish it instead to go live now.',
                        );
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'items' => 'changes',
            'items.*.title' => 'change title',
            'items.*.type' => 'change type',
        ];
    }
}
