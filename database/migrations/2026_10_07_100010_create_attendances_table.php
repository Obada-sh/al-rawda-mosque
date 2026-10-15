<?php

use App\Enums\AttendanceMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per student per session. Absence is not stored: a student is
     * absent when a held session has no row for them.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->dateTime('scanned_at')->nullable()->comment('Card scan time; null for manual attendance');
            $table->enum('method', AttendanceMethod::values())->default(AttendanceMethod::Qr->value);
            $table->timestamps();

            $table->unique(['course_session_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
