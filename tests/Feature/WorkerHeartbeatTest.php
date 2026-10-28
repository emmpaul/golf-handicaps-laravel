<?php

/*
 * Mutations that turn these tests red:
 * - remove the Queue::looping subscription from AppServiceProvider: a worker on an empty
 *   queue never writes the heartbeat;
 * - remove the JobProcessed subscription: the heartbeat is missing after a successful job;
 * - remove the JobFailed subscription: the heartbeat is missing after a failed job;
 * - compare with <= instead of < in isFresh(): a heartbeat exactly 300 seconds old is fresh.
 */

use App\Support\WorkerHeartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\Wormhole;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * A job that lasts two minutes on the frozen clock, then succeeds.
 */
class HeartbeatSucceedingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        (new Wormhole(120))->seconds();
    }
}

/**
 * A job that lasts two minutes on the frozen clock, then throws.
 */
class HeartbeatFailingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        (new Wormhole(120))->seconds();

        throw new RuntimeException('The heartbeat test job fails.');
    }
}

beforeEach(function () {
    $this->heartbeatDirectory = sys_get_temp_dir().'/worker-heartbeat-test-'.getmypid().'-'.bin2hex(random_bytes(4));
    mkdir($this->heartbeatDirectory);
    $this->heartbeatPath = $this->heartbeatDirectory.'/worker-heartbeat';

    config([
        'queue.default' => 'database',
        'queue.heartbeat_path' => $this->heartbeatPath,
    ]);

    // 2026-10-28 12:00:00 UTC, 1793188800 in Unix seconds.
    $this->travelTo('2026-10-28 12:00:00');
});

afterEach(function () {
    @unlink($this->heartbeatPath);
    @rmdir($this->heartbeatDirectory);
});

test('the heartbeat is not fresh when no worker wrote it', function () {
    expect(WorkerHeartbeat::isFresh(300))->toBeFalse();
});

test('a worker on an empty queue writes the current time on its loop', function () {
    $exitCode = Artisan::call('queue:work', [
        'connection' => 'database',
        '--stop-when-empty' => true,
        '--sleep' => 0,
    ]);

    expect($exitCode)->toBe(0)
        ->and(file_get_contents($this->heartbeatPath))->toBe('1793188800');
});

test('a worker writes the heartbeat at the end of a successful job', function () {
    HeartbeatSucceedingJob::dispatch()->onConnection('database');

    // --once runs a single job without the loop, so only the job event writes the heartbeat.
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);

    // 2026-10-28 12:02:00 UTC: the job moved the clock by two minutes.
    expect(file_get_contents($this->heartbeatPath))->toBe('1793188920');
    $this->assertDatabaseCount('jobs', 0);
});

test('a worker writes the heartbeat at the end of a failed job', function () {
    HeartbeatFailingJob::dispatch()->onConnection('database');

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

    expect(file_get_contents($this->heartbeatPath))->toBe('1793188920');
    $this->assertDatabaseCount('failed_jobs', 1);
});

test('a web request writes no heartbeat', function () {
    $this->get('/up')->assertOk();

    expect(file_exists($this->heartbeatPath))->toBeFalse();
});

test('the heartbeat is fresh below 300 seconds and stale from 300 seconds', function () {
    WorkerHeartbeat::beat();

    $this->travel(299)->seconds();
    expect(WorkerHeartbeat::isFresh(300))->toBeTrue();

    $this->travel(1)->seconds();
    expect(WorkerHeartbeat::isFresh(300))->toBeFalse();

    $this->travel(1)->seconds();
    expect(WorkerHeartbeat::isFresh(300))->toBeFalse();
});
