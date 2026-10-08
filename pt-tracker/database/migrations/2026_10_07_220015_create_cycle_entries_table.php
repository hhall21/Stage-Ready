<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cycle_id');
            $table->string('substance', 150);
            $table->dateTime('taken_at');
            $table->decimal('amount', 8, 2);
            $table->string('unit', 30);
            $table->string('route', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('cycle_id', 'fk_cycle_entries_cycle')->references('id')->on('phases')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_entries');
    }
};
