<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $ordersTable = $tables['orders'] ?? $prefix.'orders';
        $orderParcelsTable = $tables['order_parcels'] ?? $prefix.'order_parcels';

        Schema::create($orderParcelsTable, function (Blueprint $table) use ($ordersTable): void {
            $jsonType = (string) commerce_json_column_type('jnt', 'json');
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained($ordersTable)->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->string('tracking_number', 30);
            $table->decimal('actual_weight', 10, 3)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'tracking_number']);
            $table->index(['tracking_number']);
        });

        // GIN indexes only work with jsonb in PostgreSQL
        if (commerce_json_column_type('jnt', 'json') === 'jsonb') {
            Schema::table($orderParcelsTable, function (Blueprint $table): void {
                $table->rawIndex('metadata', 'jnt_order_parcels_metadata_gin_index');
            });
        }
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $orderParcelsTable = $tables['order_parcels'] ?? $prefix.'order_parcels';

        Schema::dropIfExists($orderParcelsTable);
    }
};
