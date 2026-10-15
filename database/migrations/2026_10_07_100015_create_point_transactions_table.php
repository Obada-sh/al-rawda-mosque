<?php

use App\Enums\PointSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The points ledger: every award or deduction is its own row and a
     * total is always the sum of rows. There is no stored balance.
     */
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('source', PointSource::values());
            $table->foreignId('attendance_id')->nullable()->unique()
                ->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('recitation_id')->nullable()->unique()
                ->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('points')->comment('Negative for a penalty');
            $table->string('reason')->nullable()->comment('Required for manual points');
            $table->date('occurred_on');
            $table->timestamps();

            $table->index(['enrollment_id', 'occurred_on']);
            $table->index('occurred_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
