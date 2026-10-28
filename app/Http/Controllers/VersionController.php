<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    /**
     * Report the running version and the commit it was built from.
     *
     * Served from the api group: no session, no cookie, no database read.
     */
    public function __invoke(): JsonResponse
    {
        $commit = config('app.commit');

        return response()->json([
            'version' => config('app.version'),
            'commit' => is_string($commit) && preg_match('/^[0-9a-f]{40}$/', $commit) === 1 ? $commit : null,
        ])->header('Cache-Control', 'no-store');
    }
}
