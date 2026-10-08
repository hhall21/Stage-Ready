<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('meal_date');
            $table->enum('meal_type', [ 'breakfast', 'lunch', 'dinner', 'snack', 'pre_workout', 'post_workout', 'other' ]);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('client_id', 'fk_meals_client')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
