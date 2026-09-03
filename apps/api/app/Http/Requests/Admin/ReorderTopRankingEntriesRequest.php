<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\TopRanking\Actions\ReorderRankingEntries;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The page's rows, in the order they should now appear.
 *
 * The whole arrangement, not a move instruction — see
 * {@see ReorderRankingEntries} for why.
 */
final class ReorderTopRankingEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // 250 matches the highest `row_limit` a page may be given, so a full
            // reorder of the largest possible page fits in one request.
            'ids' => ['required', 'array', 'min:1', 'max:250'],
            'ids.*' => ['integer'],
        ];
    }
}
