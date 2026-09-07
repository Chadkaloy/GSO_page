<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * image on emp_accounts_record was NOT NULL with no default, which
     * broke the auto-provisioning of the first Super Admin (see User.php
     * booted()) since that flow never sets an image — there's no real
     * uploaded photo yet on account creation. Making it nullable lets
     * the insert succeed with image = NULL; the person can upload a
     * photo later via the Employee Accounts page like anyone else.
     * Same reasoning/pattern as the earlier username/password/Email fix.
     */
    public function up(): void
    {
        Schema::table('emp_accounts_record', function (Blueprint $table) {
            $table->string('image', 250)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('emp_accounts_record', function (Blueprint $table) {
            $table->string('image', 250)->nullable(false)->change();
        });
    }
};
