<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_id');
            $table->string('food_name', 150);
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->decimal('protein', 7, 2)->nullable();
            $table->decimal('carbohydrates', 7, 2)->nullable();
            $table->decimal('fat', 7, 2)->nullable();
            $table->timestamps();
            $table->foreign('meal_id', 'fk_meal_items_meal')->references('id')->on('meals')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_items');
    }
};
