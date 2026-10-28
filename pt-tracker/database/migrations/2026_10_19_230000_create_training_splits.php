<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->date('start_date');
            $table->json('days');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'start_date']);
        });
        Schema::create('split_day_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_split_id')->constrained()->cascadeOnDelete();
            $table->date('calendar_date');
            // Zero means an extra rest day; 1–15 identifies a day in the split.
            $table->unsignedTinyInteger('day_number');
            $table->timestamps();
            $table->unique(['training_split_id', 'calendar_date']);
        });
        Schema::table('workouts', function (Blueprint $table) {
            $table->foreignId('training_split_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('split_day_number')->nullable();
            $table->unique(['training_split_id', 'workout_date']);
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropForeign(['training_split_id']);
            $table->dropUnique(['training_split_id', 'workout_date']);
            $table->dropColumn(['training_split_id', 'split_day_number']);
        });
        Schema::dropIfExists('split_day_selections');
        Schema::dropIfExists('training_splits');
    }
};
