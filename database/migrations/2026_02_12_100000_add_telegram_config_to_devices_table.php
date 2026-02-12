<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('last_seen_at');
            $table->string('telegram_chat_id')->nullable()->after('telegram_bot_token');
            $table->boolean('telegram_enabled')->default(false)->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['telegram_bot_token', 'telegram_chat_id', 'telegram_enabled']);
        });
    }
};
