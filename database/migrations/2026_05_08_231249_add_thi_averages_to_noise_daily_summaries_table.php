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
        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->decimal('l1_thi_avg', 8, 2)->nullable()->after('l1_leq')->comment('THI Average periode L1');
            $table->decimal('l2_thi_avg', 8, 2)->nullable()->after('l2_leq')->comment('THI Average periode L2');
            $table->decimal('l3_thi_avg', 8, 2)->nullable()->after('l3_leq')->comment('THI Average periode L3');
            $table->decimal('l4_thi_avg', 8, 2)->nullable()->after('l4_leq')->comment('THI Average periode L4');
            $table->decimal('l5_thi_avg', 8, 2)->nullable()->after('l5_leq')->comment('THI Average periode L5');
            $table->decimal('l6_thi_avg', 8, 2)->nullable()->after('l6_leq')->comment('THI Average periode L6');
            $table->decimal('l7_thi_avg', 8, 2)->nullable()->after('l7_leq')->comment('THI Average periode L7');
            $table->decimal('l8_thi_avg', 8, 2)->nullable()->after('l8_leq')->comment('THI Average periode L8');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'l1_thi_avg',
                'l2_thi_avg',
                'l3_thi_avg',
                'l4_thi_avg',
                'l5_thi_avg',
                'l6_thi_avg',
                'l7_thi_avg',
                'l8_thi_avg',
            ]);
        });
    }
};
