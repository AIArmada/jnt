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
        $orderItemsTable = $tables['order_items'] ?? $prefix.'order_items';

        Schema::create($orderItemsTable, function (Blueprint $table) use ($ordersTable): void {
            $jsonType = (string) commerce_json_column_type('jnt', 'json');
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained($ordersTable)->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('english_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('weight_grams');
            $table->decimal('unit_price', 12, 2);
            $table->string('currency', 3)->default('MYR');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'name']);
        });

        Schema::table($orderItemsTable, function (Blueprint $table): void {
            $table->rawIndex('metadata', 'jnt_order_items_metadata_gin_index');
        });
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $orderItemsTable = $tables['order_items'] ?? $prefix.'order_items';

        Schema::dropIfExists($orderItemsTable);
    }
};
