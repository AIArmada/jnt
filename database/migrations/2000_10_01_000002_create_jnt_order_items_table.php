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
        $orderItemsTable = $tables['order_items'] ?? $prefix . 'order_items';

        commerce_schema_create_if_missing($orderItemsTable, function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('jnt', 'jsonb');
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->index();
            $table->string('name', 200);
            $table->string('english_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('weight_grams');
            $table->unsignedBigInteger('unit_price_minor');
            $table->string('currency', 3)->default('MYR')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->timestampsTz();

            $table->index(['order_id', 'name']);
        });

        // GIN indexes only work with jsonb in PostgreSQL
        if (commerce_json_column_type('jnt', 'jsonb') === 'jsonb' && DB::connection()->getDriverName() === 'pgsql') {
            Schema::table($orderItemsTable, function (Blueprint $table) use ($orderItemsTable): void {
                DB::statement('CREATE INDEX IF NOT EXISTS jnt_order_items_metadata_gin_index ON ' . $orderItemsTable . ' USING GIN (metadata)');
            });
        }
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $orderItemsTable = $tables['order_items'] ?? $prefix . 'order_items';

        Schema::dropIfExists($orderItemsTable);
    }
};
