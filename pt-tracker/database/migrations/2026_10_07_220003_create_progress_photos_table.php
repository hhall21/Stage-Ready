<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('check_in_id');
            $table->enum('photo_type', ['front', 'side', 'back', 'other']);
            $table->string('file_path', 500);
            $table->timestamps();
            $table->foreign('check_in_id', 'fk_progress_photos_checkin')->references('id')->on('daily_check_ins')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
    }
};
