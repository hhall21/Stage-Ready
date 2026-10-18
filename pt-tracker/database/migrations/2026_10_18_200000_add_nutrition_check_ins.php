<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_check_ins', function (Blueprint $table) {
            $table->unsignedTinyInteger('hunger_level')->nullable();
            $table->unsignedTinyInteger('appetite_level')->nullable();
            $table->unsignedTinyInteger('digestive_discomfort')->nullable();
            $table->text('digestion_notes')->nullable();
            $table->text('dietary_difficulties')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_check_ins', function (Blueprint $table) {
            $table->dropColumn(['hunger_level', 'appetite_level', 'digestive_discomfort', 'digestion_notes', 'dietary_difficulties']);
        });
    }
};
