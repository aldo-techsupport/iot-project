<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add performance indexes to optimize frequent queries
     */
    public function up(): void
    {
        // Uses MySQL-only SHOW INDEX / ADD INDEX syntax; skip on other drivers (e.g. SQLite tests)
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Use raw SQL to check and add indexes
        $connection = Schema::getConnection();

        // Add indexes to noise_raw_data
        $this->addIndexIfNotExists($connection, 'noise_raw_data', 'idx_device_period_measured',
            'ALTER TABLE `noise_raw_data` ADD INDEX `idx_device_period_measured`(`device_id`, `period`, `measured_at`)');

        $this->addIndexIfNotExists($connection, 'noise_raw_data', 'idx_device_period_created',
            'ALTER TABLE `noise_raw_data` ADD INDEX `idx_device_period_created`(`device_id`, `period`, `created_at`)');

        // Add indexes to noise_calculations
        $this->addIndexIfNotExists($connection, 'noise_calculations', 'idx_device_period_calc_date',
            'ALTER TABLE `noise_calculations` ADD INDEX `idx_device_period_calc_date`(`device_id`, `period`, `calculation_date`)');

        // Add indexes to telemetries (skip if exists - likely already exists)
        $this->addIndexIfNotExists($connection, 'telemetries', 'idx_device_measured',
            'ALTER TABLE `telemetries` ADD INDEX `idx_device_measured`(`device_id`, `measured_at`)');

        // Add indexes to noise_timeout_logs
        $this->addIndexIfNotExists($connection, 'noise_timeout_logs', 'idx_device_expected',
            'ALTER TABLE `noise_timeout_logs` ADD INDEX `idx_device_expected`(`device_id`, `expected_at`)');
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists($connection, string $table, string $indexName, string $sql): void
    {
        $indexes = $connection->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        if (empty($indexes)) {
            $connection->statement($sql);
            echo "✓ Added index {$indexName} to {$table}\n";
        } else {
            echo "⏭ Index {$indexName} already exists on {$table}\n";
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes($table);

        return isset($indexes[$indexName]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('noise_raw_data', function (Blueprint $table) {
            $table->dropIndex('idx_device_period_measured');
            $table->dropIndex('idx_device_period_created');
        });

        Schema::table('noise_calculations', function (Blueprint $table) {
            $table->dropIndex('idx_device_period_calc_date');
        });

        Schema::table('telemetries', function (Blueprint $table) {
            $table->dropIndex('idx_device_measured');
        });

        Schema::table('noise_timeout_logs', function (Blueprint $table) {
            $table->dropIndex('idx_device_expected');
        });
    }
};
