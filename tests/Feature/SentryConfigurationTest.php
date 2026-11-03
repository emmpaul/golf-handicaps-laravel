<?php

/*
 * Mutations that turn these tests red (design D3 of error-alerts):
 * - put env('SENTRY_SEND_DEFAULT_PII', false) back in config/sentry.php: the first test lists a
 *   second variable.
 * - set enable_metrics to true, http_timeout to 5, or one key of breadcrumbs or tracing to true:
 *   the second test fails on that value.
 * - delete the tracing key from config/sentry.php: the package merges its own, whose values read
 *   the environment, and the third test lists tracing among the keys left to the package.
 * - delete SENTRY_LARAVEL_DSN from phpunit.xml: the DSN test reads null instead of ''.
 * - give before_send as a closure, which config:cache cannot write: the last test reads a Closure.
 */

test('the DSN is the only value of the Sentry configuration read from the environment', function () {
    preg_match_all('/\benv\(\s*[\'"](\w+)[\'"]/', (string) file_get_contents(config_path('sentry.php')), $variables);

    expect($variables[1])->toBe(['SENTRY_LARAVEL_DSN']);
});

test('the Sentry configuration collects nothing beyond errors and bounds its requests', function () {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.traces_sample_rate'))->toBeNull()
        ->and(config('sentry.profiles_sample_rate'))->toBeNull()
        ->and(config('sentry.enable_logs'))->toBeFalse()
        ->and(config('sentry.enable_metrics'))->toBeFalse()
        ->and(config('sentry.http_connect_timeout'))->toBe(1)
        ->and(config('sentry.http_timeout'))->toBe(2)
        ->and(config('sentry.breadcrumbs'))->toBe([
            'logs' => false,
            'cache' => false,
            'livewire' => false,
            'sql_queries' => false,
            'sql_bindings' => false,
            'queue_info' => false,
            'command_info' => false,
            'http_client_requests' => false,
            'notifications' => false,
        ])
        ->and(array_unique(array_values(config('sentry.tracing'))))->toBe([false]);
});

test('the package configuration fills in no key that reads the environment', function () {
    $package = require base_path('vendor/sentry/sentry-laravel/config/sentry.php');
    $project = require config_path('sentry.php');

    expect(array_keys(array_diff_key($package, $project)))->toBe(['data_collection', 'ignore_transactions'])
        ->and(array_keys(array_diff_key($package['tracing'], $project['tracing'])))
        ->toBe(['sql_origin_threshold_ms', 'http_client_requests_origin_threshold_ms']);
});

test('no test inherits a Sentry DSN from the environment', function () {
    expect(config('sentry.dsn'))->toBe('');
});

test('before_send names the filter by class and method, as the configuration cache requires', function () {
    expect(config('sentry.before_send'))->toBe(['App\\Integrations\\Sentry\\SentryEventFilter', 'beforeSend']);
});
