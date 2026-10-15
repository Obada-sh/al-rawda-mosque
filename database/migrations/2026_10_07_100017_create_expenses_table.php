<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expenses only: there is no income and no wallet.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('item_name');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('total', 16, 2)->comment('quantity x unit_price, computed by the system');
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'expense_date']);
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
