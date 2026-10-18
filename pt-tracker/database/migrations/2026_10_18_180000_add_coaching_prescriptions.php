<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_from');
            $table->json('training_meals');
            $table->json('rest_meals');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'effective_from']);
        });
        Schema::create('practice_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_from');
            $table->date('show_date')->nullable();
            $table->json('days');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'effective_from']);
        });
        Schema::table('nutrition_plans', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::table('trainer_clients', function (Blueprint $table) {
            $table->string('review_phase', 20)->default('offseason');
            $table->date('review_started_on')->nullable();
        });
        Schema::create('coaching_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phase', 20);
            $table->date('review_date');
            $table->text('feedback');
            $table->text('next_steps')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'coach_id', 'review_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_reviews');
        Schema::dropIfExists('practice_plans');
        Schema::dropIfExists('nutrition_plans');
        Schema::table('trainer_clients', function (Blueprint $table) {
            $table->dropColumn(['review_phase', 'review_started_on']);
        });
    }
};
