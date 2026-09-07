<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links emp_accounts_record (the "what role does this person have"
     * table) to users (the "how do they log in" table). These have been
     * two completely independent auto-increment sequences up to now — any
     * code assuming Auth::id() equals an accID was only ever correct by
     * coincidence when both tables happened to have exactly one row.
     *
     * user_id is nullable at the schema level so this migration doesn't
     * fail against existing data (an employee record with no linked login
     * yet is a valid, if incomplete, state) — but the application layer
     * should treat it as required going forward for new records, and a
     * given user can only be linked to one employee record (unique).
     */
    public function up(): void
    {
        Schema::table('emp_accounts_record', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('accID')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emp_accounts_record', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
