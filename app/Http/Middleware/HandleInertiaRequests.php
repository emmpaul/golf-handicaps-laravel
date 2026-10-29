<?php

namespace App\Http\Middleware;

use App\Domain\Imports\Queries\PendingImports;
use App\Support\AppBuild;
use App\Support\Passcode;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'app' => [
                'version' => AppBuild::version(),
                'commit' => AppBuild::commit(),
            ],
            // The badge of the Importer button (design D7, DO35): read with the page, one count, so
            // the badge and the home page do not shift when it arrives; only behind the passcode,
            // so /login tells nothing about the imports.
            ...$this->opened($request) ? [
                'pendingImports' => fn (): int => app(PendingImports::class)->count(),
            ] : [],
        ];
    }

    private function opened(Request $request): bool
    {
        return Passcode::isFingerprint($request->session()->get('access.passcode'));
    }
}
