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
        Schema::table('telemetries', function (Blueprint $table) {
            $table->boolean('is_filled')->default(false)->after('measured_at');
            $table->enum('fill_method', ['actual', 'copied', 'zero'])->default('actual')->after('is_filled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemetries', function (Blueprint $table) {
            $table->dropColumn(['is_filled', 'fill_method']);
        });
    }
};
