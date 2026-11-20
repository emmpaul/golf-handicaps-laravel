<?php

use App\Integrations\Sentry\SentryEventFilter;

/*
 * Design D3 of error-alerts: only the DSN reads the environment, so no variable can reopen what
 * this file closes. The package merges its own config/sentry.php under this one and its values
 * read the environment: every such key stays here, even when its value is the default.
 */
return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'spotlight' => false,

    'release' => null,

    // Null: the SDK takes the Laravel environment.
    'environment' => null,

    'org_id' => null,

    'sample_rate' => 1.0,

    'traces_sample_rate' => null,

    'profiles_sample_rate' => null,

    'strict_trace_continuation' => false,

    'enable_logs' => false,

    'enable_metrics' => false,

    'log_flush_threshold' => null,

    'logs_channel_level' => 'debug',

    'send_default_pii' => false,

    // Design D2: an array, as a closure would break the configuration cache.
    'before_send' => [SentryEventFilter::class, 'beforeSend'],

    // Three seconds at most per event on a request that has already failed.
    'http_connect_timeout' => 1,

    'http_timeout' => 2,

    'breadcrumbs' => [
        'logs' => false,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => false,
        'command_info' => false,
        'http_client_requests' => false,
        'notifications' => false,
    ],

    // A key left out counts as true in the package.
    'tracing' => [
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'sql_origin' => false,
        'views' => false,
        'livewire' => false,
        'http_client_requests' => false,
        'http_client_requests_origin' => false,
        'cache' => false,
        'redis_commands' => false,
        'redis_origin' => false,
        'notifications' => false,
        'missing_routes' => false,
        'continue_after_response' => false,
        'gen_ai' => false,
        'gen_ai_invoke_agent' => false,
        'gen_ai_chat' => false,
        'gen_ai_execute_tool' => false,
        'gen_ai_embeddings' => false,
        'gen_ai_evaluate' => false,
        'default_integrations' => false,
    ],

];
