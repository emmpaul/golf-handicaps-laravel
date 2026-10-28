<?php

namespace App\Http\Requests;

use App\Models\Player;

class StorePlayerRequest extends PlayerNameRequest
{
    protected function renamedPlayer(): ?Player
    {
        return null;
    }
}
