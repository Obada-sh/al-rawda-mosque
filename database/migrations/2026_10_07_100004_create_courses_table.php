<?php

use App\Enums\CourseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('points_per_page')->default(0);
            $table->unsignedSmallInteger('points_per_attendance')->default(0);
            $table->enum('status', CourseStatus::values())->default(CourseStatus::Active->value);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
