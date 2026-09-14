<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('jnt.webhooks.enabled', true)) {
            return;
        }

        $table = (string) config('jnt.database.tables.webhook_calls', 'webhook_calls');

        $this->createSharedWebhookCallsTableIfMissing($table);

        if (! Schema::hasTable($table)) {
            return;
        }

        $hasOwnerType = Schema::hasColumn($table, 'owner_type');
        $hasOwnerId = Schema::hasColumn($table, 'owner_id');
        $hasOwnerIndex = Schema::hasIndex($table, 'webhook_calls_owner_type_owner_id_index');

        Schema::table($table, function (Blueprint $table) use ($hasOwnerType, $hasOwnerId, $hasOwnerIndex): void {
            $tableName = $table->getTable();

            if (! Schema::hasColumn($tableName, 'order_id')) {
                $table->foreignUuid('order_id')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'tracking_number')) {
                $table->string('tracking_number', 30)->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'order_reference')) {
                $table->string('order_reference', 50)->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'digest')) {
                $table->string('digest', 255)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'processing_status')) {
                $table->string('processing_status', 32)->default('pending')->index();
            }

            if (! Schema::hasColumn($tableName, 'processing_error')) {
                $table->text('processing_error')->nullable();
            }

            if (! $hasOwnerType && ! $hasOwnerId) {
                $table->nullableMorphs('owner');
            } else {
                if (! $hasOwnerType) {
                    $table->string('owner_type')->nullable();
                }

                if (! $hasOwnerId) {
                    $table->uuid('owner_id')->nullable();
                }

                if (! $hasOwnerIndex) {
                    $table->index(['owner_type', 'owner_id'], 'webhook_calls_owner_type_owner_id_index');
                }
            }

            $this->addIndexIfMissing($table, ['processing_status', 'created_at'], 'jnt_webhook_calls_pending_idx');
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(Blueprint $table, array $columns, string $name): void
    {
        if (! Schema::hasIndex($table->getTable(), $name)) {
            $table->index($columns, $name);
        }
    }

    private function createSharedWebhookCallsTableIfMissing(string $table): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        if ($table !== 'webhook_calls') {
            return;
        }

        if (! class_exists(SupportServiceProvider::class)) {
            return;
        }

        $providerFile = (new ReflectionClass(SupportServiceProvider::class))->getFileName();

        if (! is_string($providerFile) || $providerFile === '') {
            return;
        }

        $migrationPath = dirname($providerFile, 2) . '/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub';

        if (! is_file($migrationPath)) {
            return;
        }

        $migration = require $migrationPath;
        $migration->up();
    }
};
