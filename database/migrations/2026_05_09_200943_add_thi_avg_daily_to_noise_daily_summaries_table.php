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
            $table->decimal('thi_avg_daily', 8, 2)->nullable()->after('allowable_time')->comment('THI Average keseluruhan harian (rata-rata L1-L8)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->dropColumn('thi_avg_daily');
        });
    }
};
