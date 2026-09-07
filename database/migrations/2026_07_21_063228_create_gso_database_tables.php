<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ============================================
        // 1. prs_purpose_dictionary
        // ============================================
        Schema::create('prs_purpose_dictionary', function (Blueprint $table) {
            $table->integer('ID')->primary();
            $table->string('Purpose_Type', 25);
            $table->timestamps();
        });

        // ============================================
        // 2. emp_accounts_record
        // ============================================
        Schema::create('emp_accounts_record', function (Blueprint $table) {
            $table->id('accID');
            $table->char('accLevel', 1);
            $table->string('username', 50)->unique();
            $table->string('password', 50);
            $table->string('fullName', 25);
            $table->integer('Age');
            $table->string('Gender', 25);
            $table->string('Address', 150);
            $table->string('Email', 100);
            $table->string('Pos', 25);
            $table->string('Mobile', 11);
            $table->string('image', 250);
            $table->timestamps();
        });

        // ============================================
        // 3. emp_pgc_record
        // ============================================
        Schema::create('emp_pgc_record', function (Blueprint $table) {
            $table->id('accID');
            $table->string('fullName', 150);
            $table->string('office', 50);
            $table->string('designation', 100);
            $table->string('note', 50);
            $table->timestamps();
        });

        // ============================================
        // 4. office_dictionary
        // ============================================
        Schema::create('office_dictionary', function (Blueprint $table) {
            $table->id();
            $table->string('officeName', 50)->unique();
            $table->string('officeCode', 100);
            $table->timestamps();
        });

        // ============================================
        // 5. inventory_dictionary
        // ============================================
        Schema::create('inventory_dictionary', function (Blueprint $table) {
            $table->id('Invent_ID');
            $table->string('AC_COA_Cir_04-08', 25)->unique();
            $table->string('AC_COA_Cir_015-09', 25);
            $table->string('AC_Name(Old)', 100);
            $table->string('AC_name(New)', 100);
            $table->timestamps();
        });

        // ============================================
        // 6. bincard_record
        // ============================================
        Schema::create('bincard_record', function (Blueprint $table) {
            $table->id();
            $table->date('bin_Date');
            $table->string('Supplier', 100);
            $table->string('Descrp', 250);
            $table->string('Qty', 10);
            $table->integer('Issued');
            $table->float('Balance');
            $table->string('PoNo', 50);
            $table->timestamps();
        });

        // ============================================
        // 7. bincard_issued_record
        // ============================================
        Schema::create('bincard_issued_record', function (Blueprint $table) {
            $table->id();
            $table->string('ItemSetID', 25);
            $table->string('itemCode', 25);
            $table->integer('bin_ID');
            $table->string('recpnt', 50);
            $table->date('issued_date');
            $table->integer('qty');
            $table->timestamps();
        });

        // ============================================
        // 8. emp_accountability_card
        // ============================================
        Schema::create('emp_accountability_card', function (Blueprint $table) {
            $table->id();
            $table->integer('Emp_ID');
            $table->string('ItemSetID', 25);
            $table->string('itemCode', 25);
            $table->string('ParNo', 50);
            $table->integer('Qty');
            $table->string('Unit', 50);
            $table->string('Descrp', 150);
            $table->string('SN', 50);
            $table->string('PropNo', 50);
            $table->float('Amount');
            $table->string('TransferTo', 100);
            $table->string('Remarks', 200);
            $table->date('DateTurnOver');
            $table->timestamps();
        });

        // ============================================
        // 9. property_accountability_receipt_record
        // ============================================
        Schema::create('property_accountability_receipt_record', function (Blueprint $table) {
            $table->id();
            $table->integer('Qty');
            $table->string('Unit', 25);
            $table->string('Descrp', 150);
            $table->string('PropNo', 25);
            $table->string('ReceivedFrom_Name', 50);
            $table->string('ReceivedFrom_Position', 50);
            $table->date('ReceivedFrom_Date');
            $table->string('ReceivedBy_Name', 50);
            $table->string('ReceivedBy_Position', 50);
            $table->date('ReceivedBy_Date');
            $table->string('PAR', 12);
            $table->timestamps();
        });

        // ============================================
        // 10. property_return_slip_record
        // ============================================
        Schema::create('property_return_slip_record', function (Blueprint $table) {
            $table->id();
            $table->string('LGU_Name', 250);
            $table->integer('PurposeID');
            $table->integer('Qty');
            $table->string('Unit', 25);
            $table->string('Descrp', 200);
            $table->string('Serial_Num', 50);
            $table->string('Prop_Number', 50);
            $table->string('ParNo', 50);
            $table->string('Name_of_Enduser', 50);
            $table->integer('Unit_Value');
            $table->integer('Total_Value');
            $table->string('Status', 50);
            $table->string('ReceiveBy_Name', 50);
            $table->string('ReceiveBy_Position', 50);
            $table->date('ReceiveBy_Date');
            $table->string('ReceiveFrom_Name', 50);
            $table->string('ReceiveFrom_Position', 50);
            $table->date('ReceiveFrom_Date');
            $table->timestamps();

            $table->foreign('PurposeID')->references('ID')->on('prs_purpose_dictionary');
        });

        // ============================================
        // 11. invent_custodian_slip
        // ============================================
        Schema::create('invent_custodian_slip', function (Blueprint $table) {
            $table->id();
            $table->integer('Qty');
            $table->string('Unit', 50);
            $table->string('Descrp', 250);
            $table->string('Invent_Item_No', 50);
            $table->string('Ez_Useful_Life', 50);
            $table->string('ReceivedBy_Name', 50);
            $table->string('ReceivedBy_Position', 50);
            $table->date('ReceiveBy_Date');
            $table->string('ReceivedFrom_Name', 50);
            $table->string('ReceivedFrom_Position', 50);
            $table->date('ReceiveFrom_Date');
            $table->integer('ICS');
            $table->timestamps();
        });

        // ============================================
        // 12. invent_custodian_slip_descrp (FIXED)
        // ============================================
        Schema::create('invent_custodian_slip_descrp', function (Blueprint $table) {
            $table->id();
            // FIX: Use unsignedBigInteger to match the parent table's ID type
            $table->unsignedBigInteger('icsID');
            $table->string('Descrp', 50);
            $table->string('Invent_Item_No', 50);
            $table->timestamps();

            $table->foreign('icsID')->references('id')->on('invent_custodian_slip')->onDelete('cascade');
        });

        // ============================================
        // 13. invent_222_1_07_07_010_furniture_fixtures
        // ============================================
        Schema::create('invent_222_1_07_07_010_furniture_fixtures', function (Blueprint $table) {
            $table->id();
            $table->string('accCode', 50);
            $table->integer('ParNo');
            $table->integer('Qty');
            $table->string('Unit', 100);
            $table->string('Descrp', 250);
            $table->integer('UnitCost');
            $table->integer('TotalCost');
            $table->string('PropNo', 50);
            $table->string('AccPerson', 200);
            $table->string('Designation_office', 100);
            $table->date('dateRelease');
            $table->string('Supplier', 150);
            $table->string('Remarks', 150);
            $table->timestamps();
        });

        // ============================================
        // 14. organizationchart
        // ============================================
        Schema::create('organizationchart', function (Blueprint $table) {
            $table->id();
            $table->string('Name', 100);
            $table->string('Position', 200);
            $table->timestamps();
        });

        // ============================================
        // 15. inspec_report_of_unserviceable_prop
        // ============================================
        Schema::create('inspec_report_of_unserviceable_prop', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        // ============================================
        // 16. trip_ticket_vehicles
        // ============================================
        Schema::create('trip_ticket_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_no', 20)->unique();
            $table->string('vehicle_type', 50);
            $table->string('brand', 50);
            $table->string('model', 50);
            $table->integer('year_model')->nullable();
            $table->string('color', 30);
            $table->string('engine_no', 50)->nullable();
            $table->string('chassis_no', 50)->nullable();
            $table->integer('capacity')->default(5);
            $table->string('status', 20)->default('Available');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('plate_no');
            $table->index('status');
        });

        // ============================================
        // 17. trip_ticket_drivers
        // ============================================
        Schema::create('trip_ticket_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('driver_code', 20)->unique();
            $table->string('full_name', 100);
            $table->string('license_no', 50)->unique();
            $table->date('license_expiry')->nullable();
            $table->string('contact_no', 20);
            $table->string('address', 200)->nullable();
            $table->string('status', 20)->default('Active');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('driver_code');
            $table->index('status');
        });

        // ============================================
        // 18. trip_ticket_record
        // ============================================
        Schema::create('trip_ticket_record', function (Blueprint $table) {
            $table->id();
            $table->string('trip_no', 50)->unique();
            $table->date('date_requested');
            $table->string('requester_name', 100);
            $table->string('requester_office', 100);
            $table->string('destination', 200);
            $table->text('purpose');
            $table->dateTime('time_departure');
            $table->dateTime('time_return')->nullable();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('driver_id');
            $table->integer('passenger_count')->default(0);
            $table->string('status', 20)->default('Pending');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('trip_ticket_vehicles');
            $table->foreign('driver_id')->references('id')->on('trip_ticket_drivers');
            $table->index('trip_no');
            $table->index('status');
            $table->index('date_requested');
        });

        // ============================================
        // 19. trip_ticket_passengers
        // ============================================
        Schema::create('trip_ticket_passengers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trip_id');
            $table->string('passenger_name', 100);
            $table->string('office', 100)->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trip_ticket_record')->onDelete('cascade');
            $table->index('trip_id');
        });

        // ============================================
        // 20. trip_ticket_approval_log
        // ============================================
        Schema::create('trip_ticket_approval_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trip_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('action', 20);
            $table->text('comments')->nullable();
            $table->timestamp('action_date')->useCurrent();
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trip_ticket_record')->onDelete('cascade');
            $table->index('trip_id');
            $table->index('approver_id');
        });

        // ============================================
        // 21. trip_ticket_fuel_record
        // ============================================
        Schema::create('trip_ticket_fuel_record', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trip_id');
            $table->decimal('fuel_liters', 10, 2);
            $table->decimal('fuel_cost', 10, 2);
            $table->decimal('odometer_start', 10, 2);
            $table->decimal('odometer_end', 10, 2);
            $table->date('fuel_date');
            $table->string('station_name', 100)->nullable();
            $table->string('or_number', 50)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trip_ticket_record')->onDelete('cascade');
            $table->index('trip_id');
        });

        // ============================================
        // 22. trip_ticket_maintenance
        // ============================================
        Schema::create('trip_ticket_maintenance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->date('maintenance_date');
            $table->string('maintenance_type', 50);
            $table->text('description');
            $table->decimal('cost', 10, 2);
            $table->string('service_provider', 100);
            $table->string('status', 20)->default('Scheduled');
            $table->date('next_maintenance_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('trip_ticket_vehicles')->onDelete('cascade');
            $table->index('vehicle_id');
            $table->index('maintenance_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to avoid foreign key constraint issues
        Schema::dropIfExists('trip_ticket_maintenance');
        Schema::dropIfExists('trip_ticket_fuel_record');
        Schema::dropIfExists('trip_ticket_approval_log');
        Schema::dropIfExists('trip_ticket_passengers');
        Schema::dropIfExists('trip_ticket_record');
        Schema::dropIfExists('trip_ticket_drivers');
        Schema::dropIfExists('trip_ticket_vehicles');
        Schema::dropIfExists('inspec_report_of_unserviceable_prop');
        Schema::dropIfExists('organizationchart');
        Schema::dropIfExists('invent_222_1_07_07_010_furniture_fixtures');
        Schema::dropIfExists('invent_custodian_slip_descrp');
        Schema::dropIfExists('invent_custodian_slip');
        Schema::dropIfExists('property_return_slip_record');
        Schema::dropIfExists('property_accountability_receipt_record');
        Schema::dropIfExists('emp_accountability_card');
        Schema::dropIfExists('bincard_issued_record');
        Schema::dropIfExists('bincard_record');
        Schema::dropIfExists('inventory_dictionary');
        Schema::dropIfExists('office_dictionary');
        Schema::dropIfExists('emp_pgc_record');
        Schema::dropIfExists('emp_accounts_record');
        Schema::dropIfExists('prs_purpose_dictionary');
    }
};
