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
        $tableName = config('jnt.database.tables.orders', 'jnt_orders');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'problem_at')) {
                $table->timestampTz('problem_at')->nullable()->after('has_problem');
            }

            if (! Schema::hasColumn($tableName, 'exception_at')) {
                $table->timestampTz('exception_at')->nullable()->after('problem_at');
            }

            if (! Schema::hasColumn($tableName, 'returned_at')) {
                $table->timestampTz('returned_at')->nullable()->after('exception_at');
            }

            if (! Schema::hasColumn($tableName, 'resolved_at')) {
                $table->timestampTz('resolved_at')->nullable()->after('returned_at');
            }
        });

        if (Schema::hasColumn($tableName, 'has_problem')) {
            // Backfill problem_at from existing has_problem = true rows
            DB::table($tableName)
                ->where('has_problem', true)
                ->whereNull('problem_at')
                ->update(['problem_at' => DB::raw('updated_at')]);

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex('jnt_orders_has_problem_index');
                $table->dropColumn('has_problem');
            });
        }
    }
};
