<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The father's name tells apart students who carry the same name. It
     * is optional, and every student who is already in the system starts
     * without one: nothing they had changes.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('father_name')->nullable()->after('full_name')
                ->comment('Optional: tells apart students with the same name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('father_name');
        });
    }
};
