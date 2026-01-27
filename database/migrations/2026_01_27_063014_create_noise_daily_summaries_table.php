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
        Schema::create('noise_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->date('calculation_date');
            $table->decimal('ls_value', 8, 2)->nullable()->comment('Leq Siang (16 jam)');
            $table->decimal('twa_value', 8, 2)->nullable()->comment('Time Weighted Average');
            $table->decimal('dnd_value', 8, 2)->nullable()->comment('Dosis harian (%)');
            $table->decimal('l1_leq', 8, 2)->nullable()->comment('Leq periode L1');
            $table->decimal('l2_leq', 8, 2)->nullable()->comment('Leq periode L2');
            $table->decimal('l3_leq', 8, 2)->nullable()->comment('Leq periode L3');
            $table->decimal('l4_leq', 8, 2)->nullable()->comment('Leq periode L4');
            $table->timestamps();
            
            $table->unique(['device_id', 'calculation_date']);
            $table->index('calculation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('noise_daily_summaries');
    }
};
