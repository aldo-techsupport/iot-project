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
        // Update period enum to include L5-L8
        DB::statement("ALTER TABLE noise_timeout_logs MODIFY COLUMN period ENUM('L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original L1-L4
        DB::statement("ALTER TABLE noise_timeout_logs MODIFY COLUMN period ENUM('L1', 'L2', 'L3', 'L4') NOT NULL");
    }
};
