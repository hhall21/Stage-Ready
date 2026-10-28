<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('coach_viewed_at')->nullable();
            $table->foreignId('retained_by_coach_id')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::table('progress_photos')->orderBy('id')->chunkById(500, function ($photos): void {
            foreach ($photos as $photo) {
                DB::table('progress_photos')->where('id', $photo->id)->update([
                    'expires_at' => CarbonImmutable::parse($photo->updated_at ?? $photo->created_at ?? now())->addDays(7),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropConstrainedForeignId('retained_by_coach_id');
            $table->dropColumn(['expires_at', 'coach_viewed_at']);
        });
    }
};
