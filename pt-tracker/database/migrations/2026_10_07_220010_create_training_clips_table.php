<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_clips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('exercise_set_id')->nullable();
            $table->string('file_path', 500);
            $table->text('client_notes')->nullable();
            $table->dateTime('uploaded_at');
            $table->timestamps();
            $table->foreign('client_id', 'fk_training_clips_client')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_set_id', 'fk_training_clips_set')->references('id')->on('exercise_sets')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_clips');
    }
};
