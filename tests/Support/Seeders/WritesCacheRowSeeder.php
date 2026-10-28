<?php

namespace Tests\Support\Seeders;

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

/**
 * A demonstration seeder that writes one row and declares no guard of its own: it proves the
 * production guard of DemoSeeder covers every subclass, not only DatabaseSeeder.
 */
class WritesCacheRowSeeder extends DemoSeeder
{
    public function run(): void
    {
        DB::table('cache')->insert(['key' => 'demo-row', 'value' => 'written', 'expiration' => 0]);
    }
}
