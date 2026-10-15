<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student in a course. Every attendance, recitation and point belongs
     * to an enrollment, so it is always known in which course it happened.
     *
     * circle_id is the student's CURRENT circle: it is chosen by the admin on
     * enrollment and replaced on transfer.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('course_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('circle_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('card_code', 20)->unique()->comment('QR content of the attendance card, digits only');
            $table->date('enrolled_on');
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
