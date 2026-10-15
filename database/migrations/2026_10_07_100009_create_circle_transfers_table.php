<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log of circle transfers. Display only: no statistic reads this table.
     */
    public function up(): void
    {
        Schema::create('circle_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('from_circle_id')->constrained('circles')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('to_circle_id')->constrained('circles')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('transferred_on')->comment('Entered by the admin; never defaulted');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_transfers');
    }
};
