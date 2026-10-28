<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesocycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->json('templates');
            $table->timestamps();
        });
        Schema::table('workouts', function (Blueprint $table) {
            $table->foreignId('mesocycle_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('template_slot')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->unique(['mesocycle_id', 'workout_date', 'template_slot'], 'workout_mesocycle_session_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropForeign(['mesocycle_id']);
            $table->dropUnique('workout_mesocycle_session_unique');
            $table->dropColumn(['mesocycle_id', 'template_slot', 'logged_at']);
        });
        Schema::dropIfExists('mesocycles');
    }
};
