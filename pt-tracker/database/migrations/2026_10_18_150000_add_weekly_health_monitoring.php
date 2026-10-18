<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_clients', function (Blueprint $table) {
            $table->timestamp('health_monitoring_enabled_at')->nullable();
        });

        Schema::create('weekly_health_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->date('reading_date');
            $table->unsignedSmallInteger('systolic');
            $table->unsignedSmallInteger('diastolic');
            $table->unsignedSmallInteger('resting_heart_rate');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'reading_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_health_readings');
        Schema::table('trainer_clients', function (Blueprint $table) {
            $table->dropColumn('health_monitoring_enabled_at');
        });
    }
};
