<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The weekly timetable of a course: one row per attendance day.
     */
    public function up(): void
    {
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('weekday')->comment('0 = Sunday ... 6 = Saturday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['course_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_schedules');
    }
};
