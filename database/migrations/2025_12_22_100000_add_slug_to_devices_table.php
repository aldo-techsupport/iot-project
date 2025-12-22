<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Device;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Generate slugs for existing devices
        Device::all()->each(function ($device) {
            $device->slug = Device::generateSlug($device->name);
            $device->saveQuietly();
        });

        // Make slug required after populating
        Schema::table('devices', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
