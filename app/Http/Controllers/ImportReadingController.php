<?php

namespace App\Http\Controllers;

use App\Domain\Imports\Actions\RetryImportReading;
use App\Domain\Imports\AiReading;
use App\Models\Import;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ImportReadingController extends Controller
{
    /**
     * Read a failed import again with the same photo; refused without an Anthropic key.
     */
    public function __invoke(Import $import, RetryImportReading $retryReading): RedirectResponse
    {
        AiReading::ensureEnabled();

        $retryReading->handle($import, CarbonImmutable::now());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lecture relancée.']);

        return to_route('import.index');
    }
}
