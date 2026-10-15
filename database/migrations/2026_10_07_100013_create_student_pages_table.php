<?php

use App\Support\QuranPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per page a student has memorized (at most 604 per student).
     * The primary key guarantees a page is counted once; recitation_id is
     * the recitation that owns the page, which gives the course, the
     * teacher and the date.
     */
    public function up(): void
    {
        Schema::create('student_pages', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedSmallInteger('page_no');
            $table->foreignId('recitation_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamp('created_at')->nullable();

            $table->primary(['student_id', 'page_no']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(sprintf(
                'ALTER TABLE student_pages ADD CONSTRAINT student_pages_page_no_chk '
                .'CHECK (page_no >= %d AND page_no <= %d)',
                QuranPages::FIRST,
                QuranPages::LAST,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_pages');
    }
};
