<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * The three starting departments. More can be added from the panel.
     */
    public function run(): void
    {
        if (Department::query()->exists()) {
            return;
        }

        foreach (['مشتريات', 'إطعام', 'لوجستي'] as $name) {
            Department::query()->create(['name' => $name]);
        }
    }
}
