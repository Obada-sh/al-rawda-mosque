<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A circle may have a logo of its own, printed on the cards of its
     * students. The picture is a file in the private folder "circle-logos";
     * the table holds its name.
     */
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('is_active')
                ->comment('File of the optional logo: circle-logos/<file>');
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
