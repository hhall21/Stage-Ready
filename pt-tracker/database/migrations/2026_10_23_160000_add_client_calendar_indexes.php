<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', fn (Blueprint $table) => $table->index(['client_id', 'workout_date']));
        Schema::table('meals', fn (Blueprint $table) => $table->index(['client_id', 'meal_date']));
        Schema::table('mesocycles', fn (Blueprint $table) => $table->index(['client_id', 'start_date']));
    }

    public function down(): void
    {
        Schema::table('workouts', fn (Blueprint $table) => $table->dropIndex(['client_id', 'workout_date']));
        Schema::table('meals', fn (Blueprint $table) => $table->dropIndex(['client_id', 'meal_date']));
        Schema::table('mesocycles', fn (Blueprint $table) => $table->dropIndex(['client_id', 'start_date']));
    }
};
