<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Parent of every seeder: development and demonstration data never reach production.
 *
 * The guard sits in __invoke(), the entry point of db:seed, migrate --seed and Seeder::call(),
 * and is final so that no subclass can bypass it; the architecture test makes every seeder
 * extend this class.
 */
abstract class DemoSeeder extends Seeder
{
    /**
     * @param  array<array-key, mixed>  $parameters
     */
    final public function __invoke(array $parameters = []): mixed
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Les données de démonstration ne peuvent pas être écrites en production.');
        }

        return parent::__invoke($parameters);
    }
}
