<?php

use App\Support\QuranPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed reference data: the page range of each of the 30 juz.
     * It is inserted here (not in a seeder) because juz completion
     * detection cannot work without it.
     */
    public function up(): void
    {
        Schema::create('quran_juz', function (Blueprint $table) {
            $table->unsignedTinyInteger('juz_no')->primary();
            $table->unsignedSmallInteger('from_page');
            $table->unsignedSmallInteger('to_page');
        });

        DB::table('quran_juz')->insert(QuranPages::juzRanges());
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_juz');
    }
};
