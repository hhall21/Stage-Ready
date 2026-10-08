<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedInteger('exercise_order');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('workout_id', 'fk_workout_exercises_workout')->references('id')->on('workouts')->onDelete('cascade');
            $table->foreign('exercise_id', 'fk_workout_exercises_exercise')->references('id')->on('exercises')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
