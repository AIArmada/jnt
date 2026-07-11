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
        $tableName = config('jnt.database.tables.orders', config('jnt.database.table_prefix', 'jnt_') . 'orders');

        // Drop index on has_problem if it exists
        $indexName = $tableName . '_has_problem_index';
        if (Schema::hasIndex($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }

        // Add problem_at first so we can backfill from has_problem = true rows
        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('problem_at')->nullable()->after('delivered_at');
        });

        // Backfill problem_at from existing has_problem = true rows
        DB::table($tableName)
            ->where('has_problem', true)
            ->update(['problem_at' => DB::raw('updated_at')]);

        // Drop has_problem and add remaining lifecycle columns
        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('has_problem');

            $table->timestampTz('exception_at')->nullable()->after('problem_at');
            $table->timestampTz('returned_at')->nullable()->after('exception_at');
            $table->timestampTz('resolved_at')->nullable()->after('returned_at');
        });

        // Ensure processed_at exists on webhook_calls (P3)
        if (Schema::hasTable('webhook_calls') && ! Schema::hasColumn('webhook_calls', 'processed_at')) {
            Schema::table('webhook_calls', function (Blueprint $table): void {
                $table->timestamp('processed_at')->nullable();
            });
        }
    }
};
