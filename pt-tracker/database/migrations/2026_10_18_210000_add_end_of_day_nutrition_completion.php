<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_check_ins', function (Blueprint $table) {
            $table->timestamp('nutrition_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_check_ins', function (Blueprint $table) {
            $table->dropColumn('nutrition_completed_at');
        });
    }
};
