<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_fuel_allocations', function (Blueprint $table) {
            $table->id();

            // Intentionally NOT a hard foreign key constraint: office_dictionary's
            // exact primary key name/type hasn't been confirmed against this
            // legacy-imported database, and we've already hit AUTO_INCREMENT/FK
            // mismatches on other tables here. A plain indexed column avoids a
            // migration failure; a real ->constrained() FK can be added later
            // once office_dictionary's schema is confirmed.
            $table->unsignedBigInteger('office_id');
            $table->index('office_id');

            // Liters of fuel allocated to this office. Single running value
            // per office for now — period/monthly scoping can be layered on
            // later if needed.
            $table->decimal('liters_allocated', 10, 2)->default(0);

            $table->timestamps();

            $table->unique('office_id'); // one allocation record per office
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_fuel_allocations');
    }
};
