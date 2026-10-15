<?php

use App\Enums\SessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A day the course actually met. It is the denominator of every
     * attendance rate.
     */
    public function up(): void
    {
        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('session_date');
            $table->enum('status', SessionStatus::values())->default(SessionStatus::Held->value);
            $table->timestamps();

            $table->unique(['course_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sessions');
    }
};
