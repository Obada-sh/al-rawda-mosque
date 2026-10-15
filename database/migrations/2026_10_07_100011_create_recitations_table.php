<?php

use App\Support\QuranPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recitation is a page range (from - to) heard by a teacher on a date.
     *
     * new_pages_count is how many of those pages the student had never
     * recited before; only new pages earn points. points_per_page is the
     * course rate at the time of entry, so later rate changes never rewrite
     * the points of old recitations.
     */
    public function up(): void
    {
        Schema::create('recitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('recited_on');
            $table->unsignedSmallInteger('from_page');
            $table->unsignedSmallInteger('to_page');
            $table->unsignedSmallInteger('pages_count');
            $table->unsignedSmallInteger('new_pages_count')->default(0);
            $table->unsignedSmallInteger('points_per_page')->default(0)
                ->comment('Course rate when the recitation was entered');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'recited_on']);
            $table->index('recited_on');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(sprintf(
                'ALTER TABLE recitations ADD CONSTRAINT recitations_pages_range_chk '
                .'CHECK (from_page >= %d AND to_page >= from_page AND to_page <= %d)',
                QuranPages::FIRST,
                QuranPages::LAST,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recitations');
    }
};
