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
        $trackingEventsTable = $tables['tracking_events'] ?? $prefix . 'tracking_events';

        if (! Schema::hasTable($trackingEventsTable)) {
            return;
        }

        Schema::table($trackingEventsTable, function (Blueprint $table): void {
            if (! Schema::hasColumn($table->getTable(), 'event_hash')) {
                $table->string('event_hash', 64)->nullable()->after('id');
            }
        });

        Schema::table($trackingEventsTable, function (Blueprint $table): void {
            $table->unique('event_hash', 'jnt_tracking_events_event_hash_unique');
        });
    }
};
