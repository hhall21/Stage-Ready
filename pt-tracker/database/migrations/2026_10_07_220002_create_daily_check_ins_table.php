<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_check_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('check_in_date');
            $table->decimal('bodyweight', 5, 2)->nullable();
            $table->decimal('sleep_hours', 4, 2)->nullable();
            $table->unsignedTinyInteger('sleep_quality')->nullable();
            $table->unsignedTinyInteger('energy_level')->nullable();
            $table->unsignedTinyInteger('stress_level')->nullable();
            $table->unsignedInteger('steps')->nullable();
            $table->decimal('water_litres', 4, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('client_id', 'fk_checkins_client')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['client_id', 'check_in_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_check_ins');
    }
};
