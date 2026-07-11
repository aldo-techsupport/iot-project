<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ALTER COLUMN untuk menambah 'manual' ke enum fill_method (MySQL only; SQLite has no enforced enum)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE noise_filtered_data MODIFY COLUMN fill_method ENUM('actual', 'copied', 'zero', 'manual') NOT NULL DEFAULT 'actual'");
        DB::statement("ALTER TABLE noise_raw_data MODIFY COLUMN fill_method ENUM('actual', 'copied', 'zero', 'manual') NOT NULL DEFAULT 'actual'");
        DB::statement("ALTER TABLE telemetries MODIFY COLUMN fill_method ENUM('actual', 'copied', 'zero', 'manual') NOT NULL DEFAULT 'actual'");
    }

    public function down(): void
    {
        // Revert — pastikan tidak ada data 'manual' sebelum rollback (MySQL only)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE noise_filtered_data MODIFY COLUMN fill_method ENUM('actual', 'copied', 'zero') NOT NULL DEFAULT 'actual'");
        DB::statement("ALTER TABLE noise_raw_data MODIFY COLUMN fill_method ENUM('actual', 'copied', 'zero') NOT NULL DEFAULT 'actual'");
        DB::statement("ALTER TABLE telemetries MODIFY COLUMN fill_method ENUM('actual', 'copied', 'zero') NOT NULL DEFAULT 'actual'");
    }
};
