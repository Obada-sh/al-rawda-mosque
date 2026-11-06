<?php

use App\Enums\AttendanceMethod;
use App\Enums\CourseStatus;
use App\Enums\English\TestKind;
use App\Enums\SessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The English section: tables of its own, apart from the mosque's
     * courses, so that nothing of one ever counts in the other. Only the
     * students are shared.
     *
     * - english_teachers: the section's own teachers (not the mosque's).
     * - english_courses (+ english_course_schedules): a course of one level,
     *   at a price in Syrian pounds, with its weekly class days.
     * - english_halls: the rooms of a course (the circles of the mosque),
     *   each with a teacher and an assistant.
     * - english_enrollments: a student in a course, in a hall, with an
     *   attendance card; and, for some, a price of their own (a discount).
     * - english_sessions / english_attendances: class days and who came.
     * - english_payments: what each student paid for the course.
     * - english_tests / english_test_results: exams and recitations, and the
     *   mark of each student out of the test's full mark.
     * - english_papers: files of a course (sheets, pictures).
     * - english_departments / english_expenses: the section's spending.
     */
    public function up(): void
    {
        Schema::create('english_teachers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('english_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('english_level_id')->constrained('english_levels')->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('price')->default(0)->comment('Syrian pounds, for the whole course');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', CourseStatus::values())->default(CourseStatus::Active->value);
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('english_course_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_course_id')->constrained('english_courses')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('weekday')->comment('0 = Sunday ... 6 = Saturday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['english_course_id', 'weekday']);
        });

        Schema::create('english_halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_course_id')->constrained('english_courses')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('name', 150);
            $table->foreignId('teacher_id')->nullable()
                ->constrained('english_teachers')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('assistant_id')->nullable()
                ->constrained('english_teachers')->restrictOnDelete()->cascadeOnUpdate();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['english_course_id', 'name']);
        });

        Schema::create('english_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('english_course_id')->constrained('english_courses')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('english_hall_id')->constrained('english_halls')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('card_code', 20)->unique()->comment('QR content of the attendance card, digits only; never one of the mosque\'s');
            $table->date('enrolled_on');
            $table->date('left_on')->nullable();
            $table->unsignedInteger('price')->nullable()->comment('Syrian pounds: what this student pays for the course; null = the course\'s price');
            $table->string('price_note')->nullable()->comment('Why the student pays another price');
            $table->timestamps();

            $table->unique(['student_id', 'english_course_id']);
        });

        Schema::create('english_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_course_id')->constrained('english_courses')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('session_date');
            $table->enum('status', SessionStatus::values())->default(SessionStatus::Held->value);
            $table->timestamps();

            $table->unique(['english_course_id', 'session_date']);
        });

        Schema::create('english_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_session_id')->constrained('english_sessions')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('english_enrollment_id')->constrained('english_enrollments')->restrictOnDelete()->cascadeOnUpdate();
            $table->dateTime('scanned_at')->nullable()->comment('Card scan time; null for manual attendance');
            $table->enum('method', AttendanceMethod::values())->default(AttendanceMethod::Qr->value);
            $table->timestamps();

            // The default name is longer than MySQL allows.
            $table->unique(['english_session_id', 'english_enrollment_id'], 'english_attendances_session_enrollment_unique');
        });

        Schema::create('english_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_enrollment_id')->constrained('english_enrollments')->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('amount')->comment('Syrian pounds');
            $table->date('paid_on');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('paid_on');
        });

        Schema::create('english_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_course_id')->constrained('english_courses')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->enum('kind', TestKind::values());
            $table->date('held_on');
            $table->unsignedSmallInteger('max_mark')->comment('The full mark of the test');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['english_course_id', 'held_on']);
        });

        Schema::create('english_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_test_id')->constrained('english_tests')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('english_enrollment_id')->constrained('english_enrollments')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('mark', 6, 2)->nullable()->comment('Null when the student was absent');
            $table->boolean('is_absent')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['english_test_id', 'english_enrollment_id'], 'english_test_results_test_enrollment_unique');
        });

        Schema::create('english_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_course_id')->constrained('english_courses')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->string('file_path')->comment('english-papers/<file>');
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedInteger('size')->comment('Bytes');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('english_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('english_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_department_id')->constrained('english_departments')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('item_name');
            $table->decimal('quantity', 12, 3)->nullable()->comment('Null for an amount paid as a whole (a lump sum)');
            $table->decimal('unit_price', 14, 2)->nullable()->comment('Syrian pounds. Null for a lump sum');
            $table->decimal('total', 16, 2)->comment('Syrian pounds: quantity x unit_price computed by the system, or the amount paid as a whole');
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['english_department_id', 'expense_date']);
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_expenses');
        Schema::dropIfExists('english_departments');
        Schema::dropIfExists('english_papers');
        Schema::dropIfExists('english_test_results');
        Schema::dropIfExists('english_tests');
        Schema::dropIfExists('english_payments');
        Schema::dropIfExists('english_attendances');
        Schema::dropIfExists('english_sessions');
        Schema::dropIfExists('english_enrollments');
        Schema::dropIfExists('english_halls');
        Schema::dropIfExists('english_course_schedules');
        Schema::dropIfExists('english_courses');
        Schema::dropIfExists('english_teachers');
    }
};
