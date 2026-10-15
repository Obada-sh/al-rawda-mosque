<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The points market (سوق النقاط): a course holds markets; a market
     * offers items priced in points; a student buys with the points of
     * that course.
     *
     * A sale never writes to point_transactions: what a student earned
     * stays what the rankings count, and what they can still spend is what
     * they earned minus what they bought (see Services\Market\PointsBalance).
     */
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('name', 150);
            $table->boolean('is_open')->default(true)->comment('Only an open market sells');
            $table->timestamps();

            $table->unique(['course_id', 'name']);
        });

        Schema::create('market_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->comment('In points');
            $table->unsignedInteger('stock')->nullable()->comment('What is left to sell; null for no limit');
            $table->string('image_path')->nullable()->comment('File of the optional picture: market-items/<file>');
            $table->boolean('is_available')->default(true)->comment('Shown at the sales desk');
            $table->timestamps();

            $table->unique(['market_id', 'name']);
        });

        Schema::create('market_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('total_points')->comment('The points the sale took from the student\'s balance');
            $table->dateTime('sold_at');
            $table->timestamps();

            $table->index(['market_id', 'sold_at']);
            $table->index('sold_at');
        });

        Schema::create('market_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_sale_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('market_item_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('item_name', 150)->comment('The item as it was sold');
            $table->unsignedInteger('price')->comment('Points for one, as it was sold');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('points')->comment('price x quantity');
            $table->timestamps();

            $table->unique(['market_sale_id', 'market_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_sale_lines');
        Schema::dropIfExists('market_sales');
        Schema::dropIfExists('market_items');
        Schema::dropIfExists('markets');
    }
};
