<?php

namespace Database\Seeders;

use App\Models\TajweedLevel;
use Illuminate\Database\Seeder;

class TajweedLevelSeeder extends Seeder
{
    /**
     * The four tajweed levels. The names can be changed from the panel.
     */
    public function run(): void
    {
        if (TajweedLevel::query()->exists()) {
            return;
        }

        $levels = ['المستوى الأول', 'المستوى الثاني', 'المستوى الثالث', 'المستوى الرابع'];

        foreach ($levels as $index => $name) {
            TajweedLevel::query()->create(['name' => $name, 'sort_order' => $index + 1]);
        }
    }
}
