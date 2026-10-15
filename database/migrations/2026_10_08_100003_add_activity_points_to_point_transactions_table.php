<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Points earned by attending an activity: a fourth source of points,
     * tied one-to-one to the row that earned them, like the points of an
     * attendance or of a recitation.
     *
     * The sources are written out here: a migration must do the same
     * thing whatever the code becomes later.
     */
    public function up(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->enum('source', ['attendance', 'recitation', 'manual', 'activity'])->change();

            $table->foreignId('activity_attendance_id')->nullable()->unique()->after('recitation_id')
                ->constrained()->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        DB::table('point_transactions')->where('source', 'activity')->delete();

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_attendance_id');
            $table->enum('source', ['attendance', 'recitation', 'manual'])->change();
        });
    }
};
