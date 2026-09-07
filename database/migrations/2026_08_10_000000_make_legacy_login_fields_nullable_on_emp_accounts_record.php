<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * username/password/Email on emp_accounts_record are legacy columns —
     * real login now lives on the users table via user_id (see
     * EmpAccountsRecord::user()). The Employee form intentionally stopped
     * collecting these, but the columns were still NOT NULL with no
     * default, so any new insert failed with:
     *   "Field 'username' doesn't have a default value"
     * Making them nullable lets new records be created without touching
     * existing data or the original table structure.
     */
    public function up(): void
    {
        Schema::table('emp_accounts_record', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->change();
            $table->string('password', 50)->nullable()->change();
            $table->string('Email', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('emp_accounts_record', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
            $table->string('password', 50)->nullable(false)->change();
            $table->string('Email', 100)->nullable(false)->change();
        });
    }
};
