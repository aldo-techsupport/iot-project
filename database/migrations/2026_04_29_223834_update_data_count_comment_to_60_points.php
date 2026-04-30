<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update data_count comment from 720 to 60 points
     * Reason: Changed from 5-second intervals (720 points/hour) to 1-minute intervals (60 points/hour)
     */
    public function up(): void
    {
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->integer('data_count')->default(0)->comment('Should be 60 for complete 1-hour calculation (1 data per minute)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->integer('data_count')->default(0)->comment('Should be 720 for complete 1-hour calculation')->change();
        });
    }
};
