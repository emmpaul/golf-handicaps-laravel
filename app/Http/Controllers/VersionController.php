<?php

namespace App\Http\Controllers;

use App\Support\AppBuild;
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
        return response()->json([
            'version' => AppBuild::version(),
            'commit' => AppBuild::commit(),
        ])->header('Cache-Control', 'no-store');
    }
}
