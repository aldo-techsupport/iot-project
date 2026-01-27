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
        Schema::create('noise_raw_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->enum('period', ['L1', 'L2', 'L3', 'L4']);
            $table->decimal('noise_level', 8, 2)->comment('Noise level in dB');
            $table->decimal('temperature', 5, 2)->nullable()->comment('Temperature in Celsius');
            $table->decimal('humidity', 5, 2)->nullable()->comment('Humidity percentage');
            $table->timestamp('measured_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['device_id', 'period', 'measured_at']);
            $table->index('measured_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('noise_raw_data');
    }
};
