<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A teacher's attendance card belongs to a course, like a student's:
     * one card per teacher in every course in which they supervise or
     * assist a circle, and a scan of it counts in that course only. The
     * code that was the teacher's for every course goes (no card with it
     * was ever printed for real).
     */
    public function up(): void
    {
        Schema::create('teacher_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('card_code', 20)->unique()->comment('QR content of the card, digits only; never equal to an enrollment\'s');
            $table->timestamps();

            $table->unique(['course_id', 'teacher_id']);
        });

        if (Schema::hasColumn('teachers', 'card_code')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropUnique(['card_code']);
                $table->dropColumn('card_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_cards');
    }
};
