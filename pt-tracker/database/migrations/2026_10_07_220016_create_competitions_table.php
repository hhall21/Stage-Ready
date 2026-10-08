<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('competition_name', 200);
            $table->date('competition_date');
            $table->string('location', 200)->nullable();
            $table->string('category', 150)->nullable();
            $table->decimal('target_weight', 5, 2)->nullable();
            $table->string('placing', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('client_id', 'fk_competitions_client')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
