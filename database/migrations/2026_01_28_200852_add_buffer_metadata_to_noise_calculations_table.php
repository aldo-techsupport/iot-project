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
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->integer('total_collected')->default(0)->after('data_count')
                ->comment('Total data points collected in buffer range (±3 min)');
            $table->integer('from_official_period')->default(0)->after('total_collected')
                ->comment('Data points from official period (09:00-09:10, etc)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->dropColumn(['total_collected', 'from_official_period']);
        });
    }
};
