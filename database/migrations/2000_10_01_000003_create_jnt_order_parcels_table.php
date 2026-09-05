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
        $orderParcelsTable = $tables['order_parcels'] ?? $prefix . 'order_parcels';

        commerce_schema_create_if_missing($orderParcelsTable, function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('jnt', 'jsonb');
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->index();
            $table->unsignedInteger('sequence')->default(0)->index();
            $table->string('tracking_number', 30);
            $table->decimal('actual_weight', 10, 3)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->timestampsTz();

            $table->unique(['order_id', 'tracking_number']);
            $table->index(['tracking_number']);
        });

        // GIN indexes only work with jsonb in PostgreSQL
        if (commerce_json_column_type('jnt', 'jsonb') === 'jsonb' && DB::connection()->getDriverName() === 'pgsql') {
            Schema::table($orderParcelsTable, function (Blueprint $table) use ($orderParcelsTable): void {
                DB::statement('CREATE INDEX IF NOT EXISTS jnt_order_parcels_metadata_gin_index ON ' . $orderParcelsTable . ' USING GIN (metadata)');
            });
        }
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $orderParcelsTable = $tables['order_parcels'] ?? $prefix . 'order_parcels';

        Schema::dropIfExists($orderParcelsTable);
    }
};
