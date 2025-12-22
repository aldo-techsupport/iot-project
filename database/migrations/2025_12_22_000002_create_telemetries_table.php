<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->decimal('temperature', 5, 2);
            $table->decimal('humidity', 5, 2);
            $table->decimal('noise_db', 5, 2);
            $table->timestamp('measured_at');
            $table->timestamps();

            $table->index(['device_id', 'measured_at']);
            $table->index('measured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetries');
    }
};
