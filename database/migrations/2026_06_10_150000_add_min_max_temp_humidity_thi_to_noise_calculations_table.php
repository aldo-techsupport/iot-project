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
            $table->decimal('min_temperature', 8, 2)->nullable()->after('avg_humidity')->comment('Min temperature in °C during period');
            $table->decimal('max_temperature', 8, 2)->nullable()->after('min_temperature')->comment('Max temperature in °C during period');
            $table->decimal('min_humidity', 8, 2)->nullable()->after('max_temperature')->comment('Min relative humidity in % during period');
            $table->decimal('max_humidity', 8, 2)->nullable()->after('min_humidity')->comment('Max relative humidity in % during period');
            $table->decimal('min_thi', 8, 2)->nullable()->after('max_humidity')->comment('Min THI value during period');
            $table->decimal('max_thi', 8, 2)->nullable()->after('min_thi')->comment('Max THI value during period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->dropColumn([
                'min_temperature',
                'max_temperature',
                'min_humidity',
                'max_humidity',
                'min_thi',
                'max_thi',
            ]);
        });
    }
};
