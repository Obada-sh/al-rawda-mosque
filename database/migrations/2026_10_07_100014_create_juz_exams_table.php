<?php

use App\Enums\ExamGrade;
use App\Enums\ExamOrigin;
use App\Enums\ExamStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The exam a student sits after completing a juz.
     */
    public function up(): void
    {
        Schema::create('juz_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('juz_no');
            $table->foreignId('enrollment_id')->nullable()
                ->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('status', ExamStatus::values())->default(ExamStatus::Pending->value);
            $table->enum('origin', ExamOrigin::values())->default(ExamOrigin::Auto->value);
            $table->date('triggered_on');
            $table->date('exam_date')->nullable();
            $table->foreignId('examiner_teacher_id')->nullable()
                ->constrained('teachers')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('grade', ExamGrade::values())->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('juz_no')->references('juz_no')->on('quran_juz')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['student_id', 'juz_no']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('juz_exams');
    }
};
