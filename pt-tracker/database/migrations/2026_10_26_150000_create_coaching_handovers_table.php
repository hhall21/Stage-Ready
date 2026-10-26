<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('coach_id')->constrained('users');
            $table->date('started_on');
            $table->timestamp('ended_at');
            $table->json('snapshot');
            $table->longText('pdf_base64');
            $table->timestamps();
            $table->index(['client_id', 'ended_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_handovers');
    }
};
