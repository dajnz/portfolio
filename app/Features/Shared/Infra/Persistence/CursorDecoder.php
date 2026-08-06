<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

use Illuminate\Support\Facades\Log;

/**
 * Parses and validates the structured admin cursor params
 * (`?cursor[id]=&cursor[sort_value]=`) against the active sort.
 *
 * A cursor whose shape does not match the sort — per the cursor-reset
 * rule (admin-section-ui.md:196) — is rejected gracefully: a warning is
 * logged and null is returned, which the paginator treats as a page-1
 * request. Note that a composite cursor is opaque: only id-sort vs
 * composite-shape mismatches are detectable statelessly.
 */
final class CursorDecoder
{
    /**
     * @param  array<string, mixed>|null  $params
     */
    public function decode(?array $params, SortSpec $sorting): ?Cursor
    {
        if ($params === null) {
            return null;
        }

        $id = $params['id'] ?? null;
        $sortValue = isset($params['sort_value']) && $params['sort_value'] !== ''
            ? $params['sort_value']
            : null;

        $validId = $id !== null
            && filter_var($id, FILTER_VALIDATE_INT) !== false
            && (int) $id > 0;

        if (! $validId
            || ($sorting->isIdSort() && $sortValue !== null)
            || (! $sorting->isIdSort() && $sortValue === null)
        ) {
            Log::warning('Admin cursor request ignored: cursor does not match the active sort; falling back to page 1.', [
                'sort_column' => $sorting->sortColumn,
                'cursor_id' => $id,
                'cursor_sort_value' => $sortValue,
            ]);

            return null;
        }

        return new Cursor(id: (int) $id, sortValue: $sortValue);
    }
}
