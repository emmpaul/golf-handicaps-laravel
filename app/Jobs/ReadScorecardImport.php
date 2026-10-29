<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads the photo of an import with the AI, then writes the result through the Actions of the
 * domain (design D4).
 */
class ReadScorecardImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $importId) {}

    public function handle(): void
    {
        // The reading comes with task 5.4.
    }
}
