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

        $this->createSharedWebhookCallsTableIfMissing();

        if (! Schema::hasTable('webhook_calls')) {
            return;
        }

        Schema::table('webhook_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('webhook_calls', 'order_id')) {
                $table->foreignUuid('order_id')->nullable()->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'tracking_number')) {
                $table->string('tracking_number', 30)->nullable()->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'order_reference')) {
                $table->string('order_reference', 50)->nullable()->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'digest')) {
                $table->string('digest', 255)->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'processing_status')) {
                $table->string('processing_status', 32)->default('pending')->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'processing_error')) {
                $table->text('processing_error')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'owner_type') && ! Schema::hasColumn('webhook_calls', 'owner_id')) {
                $table->nullableMorphs('owner');
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

    private function createSharedWebhookCallsTableIfMissing(): void
    {
        if (Schema::hasTable('webhook_calls')) {
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
