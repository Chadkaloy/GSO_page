<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_ticket_record', function (Blueprint $table) {
            // Nullable — older trips and any trip not opting into fuel
            // tracking simply have no estimate, and the approval/deduction
            // flow skips them entirely rather than erroring.
            $table->decimal('estimated_liters', 10, 2)->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('trip_ticket_record', function (Blueprint $table) {
            $table->dropColumn('estimated_liters');
        });
    }
};
