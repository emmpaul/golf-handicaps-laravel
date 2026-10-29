<?php

namespace App\Domain\Imports\Queries;

use App\Domain\Imports\ImportStatus;
use App\Models\Import;

/**
 * The imports to check (spec home-dashboard, « Imports à vérifier »): read, ready to check and
 * not saved as a round yet, all of them and not only the latest of the import screen; their
 * number and the latest by date of sending. One query.
 */
final class PendingImports
{
    /**
     * @return array{count: int, latestId: string|null}
     */
    public function get(): array
    {
        // The window counts every matching row before the limit keeps the latest.
        $latest = Import::query()
            ->toBase()
            ->where('status', ImportStatus::Ready->value)
            ->whereNull('round_id')
            ->select('id')
            ->selectRaw('count(*) over () as pending')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return [
            'count' => $latest === null ? 0 : (int) $latest->pending,
            'latestId' => $latest === null ? null : (string) $latest->id,
        ];
    }
}
