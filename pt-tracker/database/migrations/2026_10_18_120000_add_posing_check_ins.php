<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_check_ins', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('photo_version')->default(0);
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->string('pose_key', 40)->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->unique(['check_in_id', 'pose_key']);
        });

        Schema::create('check_in_posing_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('check_in_id')->constrained('daily_check_ins')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users');
            $table->unsignedInteger('photo_version');
            $table->unsignedTinyInteger('posing_rating');
            $table->text('feedback');
            $table->boolean('is_competition_prep')->default(false);
            $table->unsignedTinyInteger('flatness_rating')->nullable();
            $table->unsignedTinyInteger('fullness_rating')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique(['check_in_id', 'coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_in_posing_reviews');
        // MySQL may have replaced its automatic foreign-key index with this unique index.
        if (! Schema::hasIndex('progress_photos', ['check_in_id'])) {
            Schema::table('progress_photos', function (Blueprint $table): void {
                $table->index('check_in_id');
            });
        }
        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->dropUnique(['check_in_id', 'pose_key']);
            $table->dropColumn(['pose_key', 'mime_type']);
        });
        Schema::table('daily_check_ins', function (Blueprint $table): void {
            $table->dropColumn(['submitted_at', 'photo_version']);
        });
    }
};
