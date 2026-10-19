<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_health_readings', function (Blueprint $table) {
            $table->unsignedSmallInteger('systolic')->nullable()->change();
            $table->unsignedSmallInteger('diastolic')->nullable()->change();
            $table->unsignedSmallInteger('resting_heart_rate')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep nullable readings: making them mandatory would discard partial
        // client history or require inventing measurements during a rollback.
    }
};
