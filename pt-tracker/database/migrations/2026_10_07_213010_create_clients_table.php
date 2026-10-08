<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clients are users with role = 'client'; no separate table is needed.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration does not create a table.
    }
};
