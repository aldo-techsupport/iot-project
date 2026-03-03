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
            $table->integer('telegram_alert_cooldown')->default(5)->after('telegram_schedule_hours');
            // Cooldown in minutes: 5, 10, 15
            $table->timestamp('telegram_last_alert_at')->nullable()->after('telegram_alert_cooldown');
            // Track when last alert was sent
            $table->integer('telegram_last_alert_type')->nullable()->after('telegram_last_alert_at');
            // Track last alert type (1-5) to detect changes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['telegram_alert_cooldown', 'telegram_last_alert_at', 'telegram_last_alert_type']);
        });
    }
};
