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
            $table->decimal('avg_temperature', 8, 2)->nullable()->after('thi_average')->comment('Average temperature in °C');
            $table->decimal('avg_humidity', 8, 2)->nullable()->after('avg_temperature')->comment('Average relative humidity in %');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->dropColumn(['avg_temperature', 'avg_humidity']);
        });
    }
};
