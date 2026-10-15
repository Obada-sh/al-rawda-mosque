<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A circle belongs to exactly one course and holds its current
     * supervisor and assistant (no history is kept for teachers).
     */
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('name', 150);
            $table->foreignId('supervisor_teacher_id')->nullable()
                ->constrained('teachers')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('assistant_teacher_id')->nullable()
                ->constrained('teachers')->restrictOnDelete()->cascadeOnUpdate();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circles');
    }
};
