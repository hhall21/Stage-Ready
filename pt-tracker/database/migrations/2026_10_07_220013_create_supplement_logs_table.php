<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplement_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('supplement_id');
            $table->dateTime('taken_at');
            $table->decimal('amount', 8, 2);
            $table->string('unit', 30);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('client_id', 'fk_supplement_logs_client')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('supplement_id', 'fk_supplement_logs_supplement')->references('id')->on('supplements')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplement_logs');
    }
};
