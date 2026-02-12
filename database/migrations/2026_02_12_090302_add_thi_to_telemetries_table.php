<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetries', function (Blueprint $table) {
            $table->decimal('thi', 5, 2)->nullable()->after('humidity');
        });
    }

    public function down(): void
    {
        Schema::table('telemetries', function (Blueprint $table) {
            $table->dropColumn('thi');
        });
    }
};
