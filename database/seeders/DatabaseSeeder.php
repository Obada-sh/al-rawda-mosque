<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data the system starts with. Safe to run more than once.
     *
     * The admin account is not seeded: create it with
     *   php artisan rawda:admin
     */
    public function run(): void
    {
        $this->call([
            TajweedLevelSeeder::class,
            DepartmentSeeder::class,
        ]);
    }
}
