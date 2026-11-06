<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The levels of the English section (A1, A2, ...). An English course is
     * of one level, and the cards of its students are drawn on the level's
     * background picture (a file in the private folder "english-cards").
     * A student's own English level is also one of them.
     *
     * The six common levels are there from the start; the admin renames,
     * adds or removes them.
     */
    public function up(): void
    {
        Schema::create('english_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('card_background_path')->nullable()
                ->comment('File of the card background of the level: english-cards/<file>');
            $table->timestamps();
        });

        $now = now();

        DB::table('english_levels')->insert(array_map(
            fn (string $name, int $position): array => ['name' => $name, 'sort_order' => $position + 1, 'created_at' => $now, 'updated_at' => $now],
            ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
            array_keys(['A1', 'A2', 'B1', 'B2', 'C1', 'C2']),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('english_levels');
    }
};
