<?php

namespace App\Domain\Players\Actions;

use App\Models\PlayerAlias;
use Illuminate\Support\Facades\DB;

/**
 * Removes an alias from its player; the rounds already saved do not change.
 */
final class RemoveAlias
{
    public function handle(PlayerAlias $alias): void
    {
        DB::transaction(fn () => $alias->delete());
    }
}
