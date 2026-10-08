<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workout_exercise_id');
            $table->unsignedInteger('set_number');
            $table->unsignedInteger('reps')->nullable();
            $table->decimal('weight', 7, 2)->nullable();
            $table->decimal('rir', 3, 1)->nullable();
            $table->boolean('completed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('workout_exercise_id', 'fk_exercise_sets_workout_exercise')->references('id')->on('workout_exercises')->onDelete('cascade');
            $table->unique(['workout_exercise_id', 'set_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_sets');
    }
};
