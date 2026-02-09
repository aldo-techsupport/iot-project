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
            $table->decimal('allowable_time', 10, 2)->nullable()->after('dnd_value')
                ->comment('Allowable exposure time in hours (T = 8 / 2^((L-85)/3))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noise_daily_summaries', function (Blueprint $table) {
            $table->dropColumn('allowable_time');
        });
    }
};
