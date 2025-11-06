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
        $trackingEventsTable = $tables['tracking_events'] ?? $prefix.'tracking_events';

        Schema::create($trackingEventsTable, function (Blueprint $table) use ($ordersTable): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained($ordersTable)->cascadeOnDelete();
            $table->string('tracking_number', 30)->index();
            $table->string('order_reference', 50)->nullable()->index();
            $table->string('scan_type_code', 32)->nullable()->index();
            $table->string('scan_type_name', 128)->nullable();
            $table->string('scan_type', 128)->nullable();
            $table->timestampTz('scan_time')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('scan_network_type_name', 128)->nullable();
            $table->string('scan_network_name', 128)->nullable();
            $table->string('scan_network_contact', 64)->nullable();
            $table->string('scan_network_province', 128)->nullable();
            $table->string('scan_network_city', 128)->nullable();
            $table->string('scan_network_area', 128)->nullable();
            $table->string('scan_network_country', 128)->nullable();
            $table->string('post_code', 16)->nullable();
            $table->string('next_stop_name', 128)->nullable();
            $table->string('next_network_province_name', 128)->nullable();
            $table->string('next_network_city_name', 128)->nullable();
            $table->string('next_network_area_name', 128)->nullable();
            $table->string('remark', 255)->nullable();
            $table->string('problem_type', 128)->nullable();
            $table->string('payment_status', 64)->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->decimal('actual_weight', 10, 3)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->decimal('latitude', 11, 7)->nullable();
            $table->string('time_zone', 32)->nullable();
            $table->unsignedBigInteger('scan_network_id')->nullable();
            $table->string('staff_name', 128)->nullable();
            $table->string('staff_contact', 64)->nullable();
            $table->string('otp', 32)->nullable();
            $table->string('second_level_type_code', 32)->nullable();
            $table->string('wc_trace_flag', 32)->nullable();
            $table->string('signature_picture_url', 500)->nullable();
            $table->string('sign_url', 500)->nullable();
            $table->string('electronic_signature_pic_url', 500)->nullable();
            $jsonType = (string) commerce_json_column_type('jnt', 'json');
            $table->{$jsonType}('payload')->nullable();
            $table->timestamps();
        });

        Schema::table($trackingEventsTable, function (Blueprint $table): void {
            $table->rawIndex('payload', 'jnt_tracking_events_payload_gin_index');
        });
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $trackingEventsTable = $tables['tracking_events'] ?? $prefix.'tracking_events';

        Schema::dropIfExists($trackingEventsTable);
    }
};
