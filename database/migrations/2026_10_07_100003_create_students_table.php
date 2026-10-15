<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only the name is required so a student can be registered quickly and
     * completed later; is_profile_complete flags the ones still missing data.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('tajweed_level_id')->nullable()
                ->constrained('tajweed_levels')->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedSmallInteger('prior_memorized_pages')->nullable()
                ->comment('Pages memorized before joining the system');
            $table->unsignedSmallInteger('recited_pages_count')->default(0)
                ->comment('Counter of distinct pages recited across all courses; maintained by the system');
            $table->text('notes')->nullable();
            $table->boolean('is_profile_complete')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('full_name');
            $table->index('is_profile_complete');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
