<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('trainer_id')->nullable();
            $table->string('workout_name', 150);
            $table->date('workout_date');
            $table->text('notes')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->foreign('client_id', 'fk_workouts_client')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('trainer_id', 'fk_workouts_trainer')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
