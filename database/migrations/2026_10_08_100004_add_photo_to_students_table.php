<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The photo of a student is optional. The picture itself is a file in
     * the private folder "student-photos"; the table holds its name.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('notes')
                ->comment('File of the optional photo: student-photos/<file>');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
