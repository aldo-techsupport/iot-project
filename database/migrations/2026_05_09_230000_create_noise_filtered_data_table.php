<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel untuk menyimpan 60 data hasil seleksi 1-menit-per-data per period per hari.
     * Data ini adalah snapshot yang digunakan untuk kalkulasi Leq.
     */
    public function up(): void
    {
        Schema::create('noise_filtered_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->enum('period', ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8']);
            $table->date('calculation_date');

            // Data sensor
            $table->decimal('noise_level', 8, 2)->comment('Noise level in dB(A)');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();

            // Timestamp asli dari telemetry
            $table->timestamp('measured_at');

            // Metadata seleksi
            $table->boolean('is_filled')->default(false)->comment('Apakah data ini filled/interpolated');
            $table->enum('fill_method', ['actual', 'copied', 'zero'])->default('actual');

            // Slot menit ke berapa (0-59) dalam period ini
            $table->tinyInteger('slot_index')->unsigned()->comment('Slot menit ke-0 s/d 59');

            $table->timestamps();

            // Unique: satu slot per device/period/tanggal
            $table->unique(['device_id', 'period', 'calculation_date', 'slot_index'], 'unique_filtered_slot');

            // Index untuk query cepat
            $table->index(['device_id', 'period', 'calculation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noise_filtered_data');
    }
};
