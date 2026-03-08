<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');
        $webhookLogsTable = $tables['webhook_logs'] ?? $prefix . 'webhook_logs';

        Schema::create($webhookLogsTable, function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('jnt', 'json');
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->index();
            $table->string('tracking_number', 30)->nullable()->index();
            $table->string('order_reference', 50)->nullable()->index();
            $table->string('digest', 255)->nullable();
            $table->{$jsonType}('headers')->nullable();
            $table->{$jsonType}('payload')->nullable();
            $table->string('processing_status', 32)->default('pending')->index();
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->nullableMorphs('owner');
            $table->timestamps();

            $table->index(['processing_status', 'created_at'], 'jnt_webhook_logs_pending_idx');
        });

        // GIN indexes only work with jsonb in PostgreSQL
        if (commerce_json_column_type('jnt', 'json') === 'jsonb') {
            Schema::table($webhookLogsTable, function (Blueprint $table) use ($webhookLogsTable): void {
                DB::statement('CREATE INDEX jnt_webhook_logs_payload_gin_index ON ' . $webhookLogsTable . ' USING GIN (payload)');
            });
        }
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $webhookLogsTable = $tables['webhook_logs'] ?? $prefix . 'webhook_logs';

        Schema::dropIfExists($webhookLogsTable);
    }
};
