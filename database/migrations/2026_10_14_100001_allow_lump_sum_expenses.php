<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An expense can be only what was paid, with a description: several
     * things bought at once whose items and quantities nobody kept. Such an
     * expense has no quantity and no unit price; its total is the amount
     * paid. Every amount is in Syrian pounds.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->nullable()->default(null)
                ->comment('Null for an amount paid as a whole (a lump sum)')
                ->change();
            $table->decimal('unit_price', 14, 2)->nullable()
                ->comment('Syrian pounds. Null for an amount paid as a whole (a lump sum)')
                ->change();
            $table->decimal('total', 16, 2)
                ->comment('Syrian pounds: quantity x unit_price computed by the system, or the amount paid as a whole')
                ->change();
        });
    }

    public function down(): void
    {
        // An amount paid as a whole becomes one item at that price.
        DB::table('expenses')->whereNull('quantity')->update([
            'quantity' => 1,
            'unit_price' => DB::raw('total'),
        ]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->nullable(false)->default(1)->comment('')->change();
            $table->decimal('unit_price', 14, 2)->nullable(false)->comment('')->change();
            $table->decimal('total', 16, 2)->comment('quantity x unit_price, computed by the system')->change();
        });
    }
};
