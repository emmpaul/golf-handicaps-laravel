<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

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
}
