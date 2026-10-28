<?php

namespace Tests;

use App\Support\Passcode;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the database, then refresh PostgreSQL statistics.
     *
     * Seeding runs inside the RefreshDatabase transaction: if autovacuum
     * records an empty table meanwhile, the planner never corrects it.
     *
     * @param  array<int, class-string>|class-string  $class
     */
    public function seed($class = 'Database\\Seeders\\DatabaseSeeder'): static
    {
        parent::seed($class);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ANALYZE');
        }

        return $this;
    }

    /**
     * Open the session a browser gets once it has entered the shared passcode.
     */
    public function withPasscodeSession(): static
    {
        return $this->withSession(['access.passcode' => Passcode::fingerprint()]);
    }

    /**
     * Enter the shared passcode on /login, as a new browser without any cookie, and return the
     * identifier of the session it opens.
     */
    public function logInWithPasscode(): string
    {
        $this->flushSession();
        $this->defaultCookies = [];
        $this->post('/login', ['passcode' => 'birdie-2026'])->assertRedirect('/');

        return $this->app['session']->getId();
    }

    /**
     * Send a request as the browser holding this session cookie, with nothing kept in memory
     * from earlier requests: the session is read back from the session store.
     */
    public function getWithSessionCookie(string $uri, string $sessionId): TestResponse
    {
        return $this->withSessionCookie($sessionId)->get($uri);
    }

    /**
     * Post as the browser holding this session cookie; see getWithSessionCookie().
     */
    public function postWithSessionCookie(string $uri, string $sessionId): TestResponse
    {
        return $this->withSessionCookie($sessionId)->post($uri);
    }

    private function withSessionCookie(string $sessionId): static
    {
        $this->flushSession();

        return $this->withCookie(config('session.cookie'), $sessionId);
    }
}
