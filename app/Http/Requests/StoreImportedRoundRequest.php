<?php

namespace App\Http\Requests;

/**
 * The card of an import sent to be saved: the card of the round editor with the totals and the
 * names read on the photo.
 */
class StoreImportedRoundRequest extends RoundCardRequest
{
    protected function withReading(): bool
    {
        return true;
    }
}
