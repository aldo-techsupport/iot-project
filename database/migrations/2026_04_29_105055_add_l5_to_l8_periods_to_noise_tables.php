<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update noise_calculations table - modify enum to add L5-L8
        DB::statement("ALTER TABLE noise_calculations MODIFY COLUMN period ENUM('L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8') NOT NULL");
        
        // Update data_count comment
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->integer('data_count')->default(0)->comment('Should be 720 for complete 1-hour calculation')->change();
        });
        
        // Update noise_raw_data table - modify enum to add L5-L8
        DB::statement("ALTER TABLE noise_raw_data MODIFY COLUMN period ENUM('L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8') NOT NULL");
        
        // Add L5-L8 columns to noise_daily_summaries table
        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->decimal('l5_leq', 8, 2)->nullable()->comment('Leq periode L5')->after('l4_leq');
            $table->decimal('l6_leq', 8, 2)->nullable()->comment('Leq periode L6')->after('l5_leq');
            $table->decimal('l7_leq', 8, 2)->nullable()->comment('Leq periode L7')->after('l6_leq');
            $table->decimal('l8_leq', 8, 2)->nullable()->comment('Leq periode L8')->after('l7_leq');
            
            // Update comment for ls_value
            $table->decimal('ls_value', 8, 2)->nullable()->comment('Leq Siang (8 jam kerja, skip jam istirahat 12-13)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert noise_calculations table
        DB::statement("ALTER TABLE noise_calculations MODIFY COLUMN period ENUM('L1', 'L2', 'L3', 'L4') NOT NULL");
        
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->integer('data_count')->default(0)->comment('Should be 120 for complete calculation')->change();
        });
        
        // Revert noise_raw_data table
        DB::statement("ALTER TABLE noise_raw_data MODIFY COLUMN period ENUM('L1', 'L2', 'L3', 'L4') NOT NULL");
        
        // Remove L5-L8 columns from noise_daily_summaries
        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->dropColumn(['l5_leq', 'l6_leq', 'l7_leq', 'l8_leq']);
            $table->decimal('ls_value', 8, 2)->nullable()->comment('Leq Siang (16 jam)')->change();
        });
    }
};
