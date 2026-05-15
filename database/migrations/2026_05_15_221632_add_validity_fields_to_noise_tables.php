<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->boolean('is_valid')->default(true)->after('data_count');
            $table->string('invalid_reason')->nullable()->after('is_valid');
        });

        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->boolean('is_valid')->default(true)->after('humidity_avg_daily');
            $table->string('invalid_reason')->nullable()->after('is_valid');
            $table->json('invalid_periods')->nullable()->after('invalid_reason');
        });
    }

    public function down(): void
    {
        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->dropColumn(['is_valid', 'invalid_reason']);
        });

        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->dropColumn(['is_valid', 'invalid_reason', 'invalid_periods']);
        });
    }
};
