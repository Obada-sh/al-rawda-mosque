<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An activity outside the lessons: a trip, a competition, a meeting.
     * It is for one circle, or for the whole mosque when circle_id is null.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('activity_date');
            $table->foreignId('circle_id')->nullable()
                ->comment('Null for an activity of the whole mosque')
                ->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedSmallInteger('points')->default(0)
                ->comment('What each student who attended gains');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
