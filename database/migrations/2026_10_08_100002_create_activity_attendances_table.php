<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who attended an activity. Like everything a student does, it belongs
     * to the enrollment (the student in a course), so the points it earns
     * are known to belong to that course.
     */
    public function up(): void
    {
        Schema::create('activity_attendances', function (Blueprint $table) {
            $table->id();
            // The list of those who attended has no meaning without its activity.
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['activity_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_attendances');
    }
};
