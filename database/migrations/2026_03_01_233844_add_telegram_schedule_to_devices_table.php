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
        Schema::table('devices', function (Blueprint $table) {
            $table->string('telegram_schedule_type')->default('working_hours')->after('telegram_enabled');
            // working_hours, 24_hours, custom
            $table->json('telegram_schedule_hours')->nullable()->after('telegram_schedule_type');
            // For custom: array of hours [8, 9, 10, 14, 15, 16]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['telegram_schedule_type', 'telegram_schedule_hours']);
        });
    }
};
