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
        Schema::create('noise_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->enum('period', ['L1', 'L2', 'L3', 'L4']);
            $table->date('calculation_date');
            $table->integer('data_count')->default(0)->comment('Should be 120 for complete calculation');
            
            // Basic statistics
            $table->decimal('min_value', 8, 2)->nullable();
            $table->decimal('max_value', 8, 2)->nullable();
            $table->decimal('average_value', 8, 2)->nullable();
            
            // Frequency distribution parameters
            $table->decimal('range_value', 8, 2)->nullable()->comment('r = max - min');
            $table->decimal('class_count', 8, 2)->nullable()->comment('k from Sturges formula');
            $table->decimal('class_interval', 8, 2)->nullable()->comment('i = r/k');
            
            // Main result
            $table->decimal('leq_value', 8, 2)->nullable()->comment('Equivalent Continuous Sound Level in dB');
            
            // Additional data
            $table->json('frequency_distribution')->nullable()->comment('Array of intervals with frequencies');
            $table->decimal('thi_average', 8, 2)->nullable()->comment('Temperature Humidity Index');
            
            $table->timestamps();

            // Unique constraint: one calculation per device/period/date
            $table->unique(['device_id', 'period', 'calculation_date']);
            $table->index(['device_id', 'calculation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('noise_calculations');
    }
};
