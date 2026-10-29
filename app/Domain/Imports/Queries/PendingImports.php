<?php

namespace App\Domain\Imports\Queries;

use App\Domain\Imports\ImportStatus;
use App\Models\Import;
use Illuminate\Database\Query\Builder;

/**
 * The imports to check (spec home-dashboard, « Imports à vérifier »): read, ready to check and
 * not saved as a round yet, all of them and not only the latest of the import screen; their
 * number, shared with every page (design D7) and read by the home page. One query.
 */
final class PendingImports
{
    /**
     * Their number, for the badge of the Importer button and the row of the home page (spec
     * app-shell, design D7).
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    private function query(): Builder
    {
        return Import::query()
            ->toBase()
            ->where('status', ImportStatus::Ready->value)
            ->whereNull('round_id');
    }
}
