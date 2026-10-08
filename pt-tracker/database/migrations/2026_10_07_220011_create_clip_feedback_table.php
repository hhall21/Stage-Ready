<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_clip_id');
            $table->unsignedBigInteger('trainer_id');
            $table->text('feedback');
            $table->unsignedInteger('video_timestamp_seconds')->nullable();
            $table->enum('review_status', ['pending', 'reviewed'])->default('pending');
            $table->dateTime('feedback_date');
            $table->timestamps();
            $table->foreign('training_clip_id', 'fk_clip_feedback_clip')->references('id')->on('training_clips')->onDelete('cascade');
            $table->foreign('trainer_id', 'fk_clip_feedback_trainer')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_feedback');
    }
};
