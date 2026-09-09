<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_fuel_allocations', function (Blueprint $table) {
            // One allocation row per office PER YEAR now, instead of one
            // ever. Existing rows (created before this migration) are
            // backfilled to the current year below.
            $table->unsignedSmallInteger('year')->after('office_id')->default(now()->year);

            // The actual spendable balance. liters_allocated stays as the
            // record of what was granted; liters_remaining is what trip
            // creation deducts from and trip reconciliation refunds into.
            $table->decimal('liters_remaining', 10, 2)->after('liters_allocated')->default(0);
        });

        // Backfill: any row created before this migration starts fully
        // unspent, and is treated as belonging to the current year.
        DB::table('office_fuel_allocations')->update([
            'year'             => now()->year,
            'liters_remaining' => DB::raw('liters_allocated'),
        ]);

        Schema::table('office_fuel_allocations', function (Blueprint $table) {
            $table->dropUnique(['office_id']);
            $table->unique(['office_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::table('office_fuel_allocations', function (Blueprint $table) {
            $table->dropUnique(['office_id', 'year']);
            $table->unique('office_id');
            $table->dropColumn(['year', 'liters_remaining']);
        });
    }
};
