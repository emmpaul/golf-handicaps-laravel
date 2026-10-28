<?php

/*
 * Mutation that turns this test red:
 * - remove mask_bindings_in_exception_messages from the pgsql connection in
 *   config/database.php: the logged message ends with where "id" = 9f3c2a7e-session-literal,
 *   and the identifier that reopens the session reaches the logs.
 */

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;

test('a reported query exception is logged with its query and without its bound values', function () {
    // Port 1 refuses the connection at once, as a stopped pgsql service does in production.
    config([
        'database.connections.unreachable' => [...config('database.connections.pgsql'), 'port' => 1],
        'logging.default' => 'capture',
        'logging.channels.capture' => ['driver' => 'monolog', 'handler' => TestHandler::class],
    ]);
    try {
        DB::connection('unreachable')->table('sessions')->where('id', '9f3c2a7e-session-literal')->first();
        $this->fail('The query on the unreachable connection should throw.');
    } catch (QueryException $exception) {
    }

    report($exception);

    /** @var TestHandler $handler */
    $handler = Log::channel('capture')->getLogger()->getHandlers()[0];
    $messages = array_map(fn (LogRecord $record): string => $record->message, $handler->getRecords());
    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('select * from "sessions" where "id" = ?')
        ->and($messages[0])->not->toContain('9f3c2a7e-session-literal');
});
