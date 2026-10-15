<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teachers get an attendance card like the students, and an optional
     * photo for it.
     *
     * The code of a teacher's card is drawn from the same 10-digit space as
     * the codes of the students' cards and never equals one of them: a
     * scanned card is a student's or a teacher's, never both. Teachers who
     * exist already get their code here.
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('is_active')
                ->comment('File of the optional photo: teacher-photos/<file>');
            $table->string('card_code', 20)->nullable()->unique()->after('phone')
                ->comment('QR content of the attendance card, digits only');
        });

        $taken = DB::table('enrollments')->pluck('card_code')->flip()->all();

        foreach (DB::table('teachers')->whereNull('card_code')->orderBy('id')->pluck('id') as $teacherId) {
            do {
                $code = (string) random_int(1_000_000_000, 9_999_999_999);
            } while (isset($taken[$code]));

            $taken[$code] = true;
            DB::table('teachers')->where('id', $teacherId)->update(['card_code' => $code]);
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->string('card_code', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // The card code may be gone already: teacher_cards replaced it.
        if (Schema::hasColumn('teachers', 'card_code')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropUnique(['card_code']);
                $table->dropColumn('card_code');
            });
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
