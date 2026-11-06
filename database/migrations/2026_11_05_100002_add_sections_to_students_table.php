<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students are shared by the mosque and the English section, and each
     * section lists its own: in_mosque and in_english say where a student
     * belongs (both, for a student of both). Every student already in the
     * system is the mosque's.
     *
     * The English section asks for the student's English level instead of
     * the tajweed level, so it keeps its own "profile complete" flag.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('english_level_id')->nullable()->after('tajweed_level_id')
                ->constrained('english_levels')->restrictOnDelete()->cascadeOnUpdate();
            $table->boolean('in_mosque')->default(true)->after('is_profile_complete')
                ->comment('Listed in the mosque section');
            $table->boolean('in_english')->default(false)->after('in_mosque')
                ->comment('Listed in the English section');
            $table->boolean('english_profile_complete')->default(false)->after('in_english')
                ->comment('Every field the English section asks for has a value');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('english_level_id');
            $table->dropColumn(['in_mosque', 'in_english', 'english_profile_complete']);
        });
    }
};
