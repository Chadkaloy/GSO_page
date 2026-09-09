<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_fuel_allocation_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('office_fuel_allocation_id');
            $table->index('office_fuel_allocation_id', 'oft_allocation_id_idx');

            // Which trip caused this movement. Nullable so manual
            // adjustments (if ever needed) aren't forced to fake a trip_id.
            // No hard FK — same reasoning as office_fuel_allocations itself.
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->index('trip_id', 'oft_trip_id_idx');

            // deduction: estimated liters reserved when the trip was created.
            // refund: unused portion returned once actual usage was logged
            //   (actual < estimate).
            // extra_deduction: actual usage exceeded the original estimate;
            //   the extra was deducted on top of the original reservation.
            $table->enum('type', ['deduction', 'refund', 'extra_deduction']);

            $table->decimal('liters', 10, 2);
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_fuel_allocation_transactions');
    }
};
