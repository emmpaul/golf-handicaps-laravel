<?php

/*
 * An unknown budget time zone or a model without a price stops every process at boot, web,
 * worker and migrate alike (design D4): the request boot and `php artisan about` in its own
 * process both fail with a message that names the variable.
 *
 * Mutations that turn these tests red:
 * - the checks run only for HTTP requests (skipped when running in console): `artisan about`
 *   boots with Mars/Olympus and claude-unknown-1;
 * - the price checked only when an API key is configured: claude-unknown-1 without key boots;
 * - a message that does not name the variable: « AI_BUDGET_TIME_ZONE » or « AI_MODEL » is
 *   missing from the failure.
 *
 * Course-rating-sgt, design D10: SGT_LOOKUP other than on or off stops every process the same
 * way. Mutation that turns these tests red: the SGT_LOOKUP check run only for HTTP requests
 * (skipped when running in console): `artisan about` boots with SGT_LOOKUP=yes. Spec
 * « Variable absente »: without SGT_LOOKUP the lookup is on. Mutation that turns that test red:
 * the default of golf.sgt.lookup at off.
 */

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Process;

/**
 * Runs the callback under these environment values (null: variable absent), then restores them.
 *
 * @param  array<string, string|null>  $environment
 */
function underEnvironment(array $environment, Closure $callback): mixed
{
    $saved = [];
    foreach ($environment as $key => $value) {
        $saved[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    try {
        return $callback();
    } finally {
        foreach ($saved as $key => [$getenv, $env, $server]) {
            putenv($getenv === false ? $key : "{$key}={$getenv}");
            if ($env === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
        }
    }
}

/**
 * Boots a fresh application as a web request does under these environment values and asks it
 * for /up: the status, or the message of the exception that stopped the boot.
 *
 * @param  array<string, string|null>  $environment
 */
function bootForRequest(array $environment): int|string
{
    $testApp = Container::getInstance();

    try {
        return underEnvironment([...$environment, 'APP_RUNNING_IN_CONSOLE' => 'false'], function (): int|string {
            /** @var Application $app */
            $app = require base_path('bootstrap/app.php');
            $kernel = $app->make(HttpKernel::class);

            try {
                $kernel->bootstrap();
            } catch (Throwable $exception) {
                return $exception->getMessage();
            }

            return $kernel->handle(Request::create('/up'))->getStatusCode();
        });
    } finally {
        Container::setInstance($testApp);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($testApp);
    }
}

/**
 * `php artisan about` in its own process under these environment values (false: absent).
 *
 * @param  array<string, string|false>  $environment
 * @return array{int|null, string}
 */
function artisanAbout(array $environment): array
{
    $result = Process::path(base_path())->env($environment)->run(['php', 'artisan', 'about']);

    return [$result->exitCode(), $result->output().$result->errorOutput()];
}

dataset('broken configurations', [
    'unknown time zone' => [['AI_BUDGET_TIME_ZONE' => 'Mars/Olympus'], 'AI_BUDGET_TIME_ZONE'],
    'model without price, no key' => [['AI_MODEL' => 'claude-unknown-1', 'ANTHROPIC_API_KEY' => ''], 'AI_MODEL'],
    'model without price, with a key' => [['AI_MODEL' => 'claude-unknown-1', 'ANTHROPIC_API_KEY' => 'test-key-not-used'], 'AI_MODEL'],
]);

dataset('valid configurations', [
    'America/Montreal' => [['AI_BUDGET_TIME_ZONE' => 'America/Montreal']],
    'claude-opus-5-5' => [['AI_MODEL' => 'claude-opus-5-5']],
    'no AI_MODEL' => [['AI_MODEL' => null]],
]);

test('a request does not boot under a broken AI configuration', function (array $environment, string $variable) {
    expect(bootForRequest($environment))->toBeString()->toContain($variable);
})->with('broken configurations');

test('a request boots under a valid AI configuration', function (array $environment) {
    expect(bootForRequest($environment))->toBe(200);
})->with('valid configurations');

test('artisan does not boot under a broken AI configuration', function (array $environment, string $variable) {
    [$exitCode, $output] = artisanAbout($environment);

    expect($exitCode)->not->toBe(0)
        ->and($output)->toContain($variable);
})->with('broken configurations');

test('artisan boots under a valid AI configuration', function (array $environment) {
    [$exitCode] = artisanAbout(array_map(fn (?string $value): string|false => $value ?? false, $environment));

    expect($exitCode)->toBe(0);
})->with('valid configurations');

dataset('valid SGT_LOOKUP values', [
    'on' => ['on'],
    'off' => ['off'],
    'absent' => [null],
]);

test('a request does not boot with an unknown SGT_LOOKUP', function () {
    expect(bootForRequest(['SGT_LOOKUP' => 'yes']))->toBeString()->toContain('SGT_LOOKUP');
});

test('a request boots with SGT_LOOKUP on, off or absent', function (?string $value) {
    expect(bootForRequest(['SGT_LOOKUP' => $value]))->toBe(200);
})->with('valid SGT_LOOKUP values');

test('artisan does not boot with an unknown SGT_LOOKUP', function () {
    [$exitCode, $output] = artisanAbout(['SGT_LOOKUP' => 'yes']);

    expect($exitCode)->not->toBe(0)
        ->and($output)->toContain('SGT_LOOKUP');
});

test('artisan boots with SGT_LOOKUP on, off or absent', function (?string $value) {
    [$exitCode] = artisanAbout(['SGT_LOOKUP' => $value ?? false]);

    expect($exitCode)->toBe(0);
})->with('valid SGT_LOOKUP values');

test('without SGT_LOOKUP a fresh boot reads the lookup on', function () {
    $testApp = Container::getInstance();

    try {
        $lookup = underEnvironment(['SGT_LOOKUP' => null], function (): mixed {
            /** @var Application $app */
            $app = require base_path('bootstrap/app.php');
            $app->make(ConsoleKernel::class)->bootstrap();

            return $app->make('config')->get('golf.sgt.lookup');
        });
    } finally {
        Container::setInstance($testApp);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($testApp);
    }

    expect($lookup)->toBe('on');
});
