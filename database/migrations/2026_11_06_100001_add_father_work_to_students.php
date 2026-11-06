<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The father's work, beside his name. It is optional, and every
     * student who is already in the system starts without one: nothing
     * they had changes.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('father_work')->nullable()->after('father_name')
                ->comment("Optional: the father's work");
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('father_work');
        });
    }
};
