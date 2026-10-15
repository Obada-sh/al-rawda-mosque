<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The card is now the mosque's own design, built into the system; the
     * design that could be drawn on the "card design" page is no longer
     * read, so its setting goes. (Its picture, if one was uploaded, stays
     * in storage/app/private/card-design and can be deleted by hand.)
     */
    public function up(): void
    {
        DB::table('settings')->where('key', 'card_design')->delete();
    }

    public function down(): void
    {
        // A design that was removed cannot be brought back.
    }
};
