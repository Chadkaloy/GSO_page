<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_ticket_signatory_settings', function (Blueprint $table) {
            $table->id();
            // Free-text placeholder names used to pre-fill the "Authorized By"
            // and "Approved By" signature lines on the printed Trip Ticket.
            // Kept as a single-row settings table (not per-trip) since these
            // positions rarely change.
            $table->string('municipal_administrator_name', 150)->nullable();
            $table->string('municipal_mayor_name', 150)->nullable();
            $table->timestamps();
        });

        // Seed the single settings row immediately so the settings page
        // always has a record (id = 1) to load and update, rather than
        // needing a separate "create" step in the UI.
        DB::table('trip_ticket_signatory_settings')->insert([
            'municipal_administrator_name' => null,
            'municipal_mayor_name'         => null,
            'created_at'                   => now(),
            'updated_at'                   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_ticket_signatory_settings');
    }
};
