<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_enabled', 'whatsapp_number', 'whatsapp_session']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false)->after('telegram_chat_id');
            $table->string('whatsapp_number')->nullable()->after('whatsapp_enabled');
            $table->string('whatsapp_session')->nullable()->after('whatsapp_number');
        });
    }
};
