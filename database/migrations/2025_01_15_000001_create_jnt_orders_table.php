<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('jnt.database.tables.orders', config('jnt.database.table_prefix', 'jnt_').'orders');

        Schema::create($tableName, function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('jnt', 'json');
            $table->uuid('id')->primary();
            $table->string('order_id', 50)->unique();
            $table->string('tracking_number', 30)->nullable()->unique();
            $table->string('customer_code', 30)->index();
            $table->string('action_type', 30)->default('add');
            $table->string('service_type', 30)->nullable()->index();
            $table->string('payment_type', 30)->nullable()->index();
            $table->string('express_type', 30)->nullable()->index();
            $table->string('status', 50)->nullable()->index();
            $table->string('sorting_code', 64)->nullable();
            $table->string('third_sorting_code', 64)->nullable();
            $table->decimal('chargeable_weight', 10, 3)->nullable();
            $table->unsignedInteger('package_quantity')->default(1);
            $table->decimal('package_weight', 10, 3)->nullable();
            $table->decimal('package_length', 8, 2)->nullable();
            $table->decimal('package_width', 8, 2)->nullable();
            $table->decimal('package_height', 8, 2)->nullable();
            $table->decimal('package_value', 12, 2)->nullable();
            $table->string('goods_type', 30)->nullable();
            $table->decimal('offer_value', 12, 2)->nullable();
            $table->decimal('cod_value', 12, 2)->nullable();
            $table->decimal('insurance_value', 12, 2)->nullable();
            $table->timestampTz('pickup_start_at')->nullable();
            $table->timestampTz('pickup_end_at')->nullable();
            $table->timestampTz('ordered_at')->nullable()
                ->comment('Original order creation time reported by J&T');
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('last_tracked_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->string('last_status_code', 32)->nullable();
            $table->string('last_status', 128)->nullable();
            $table->boolean('has_problem')->default(false);
            $table->text('remark')->nullable();
            $table->{$jsonType}('sender')->nullable();
            $table->{$jsonType}('receiver')->nullable();
            $table->{$jsonType}('return_info')->nullable();
            $table->{$jsonType}('offer_fee_info')->nullable();
            $table->{$jsonType}('customs_info')->nullable();
            $table->{$jsonType}('request_payload')->nullable();
            $table->{$jsonType}('response_payload')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->rawIndex('sender', 'jnt_orders_sender_gin_index');
            $table->rawIndex('receiver', 'jnt_orders_receiver_gin_index');
            $table->rawIndex('metadata', 'jnt_orders_metadata_gin_index');
        });
    }

    public function down(): void
    {
        $tableName = config('jnt.database.tables.orders', config('jnt.database.table_prefix', 'jnt_').'orders');
        Schema::dropIfExists($tableName);
    }
};
