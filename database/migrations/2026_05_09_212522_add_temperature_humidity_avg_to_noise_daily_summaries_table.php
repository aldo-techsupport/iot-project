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
            $table->decimal('temperature_avg_daily', 8, 2)->nullable()->after('thi_avg_daily')->comment('Rata-rata suhu harian (°C) dari jam kerja L1-L8');
            $table->decimal('humidity_avg_daily', 8, 2)->nullable()->after('temperature_avg_daily')->comment('Rata-rata kelembapan harian (%) dari jam kerja L1-L8');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->dropColumn(['temperature_avg_daily', 'humidity_avg_daily']);
        });
    }
};
