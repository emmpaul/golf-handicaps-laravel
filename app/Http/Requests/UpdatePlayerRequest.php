<?php

namespace App\Http\Requests;

use App\Models\Player;

class UpdatePlayerRequest extends PlayerNameRequest
{
    protected function renamedPlayer(): Player
    {
        /** @var Player */
        return $this->route('player');
    }
}
