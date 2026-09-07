<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reverts users.role back to the original super_admin / inventory_manager
     * / inventory_user model. This exists because the migration that
     * originally converted to the 3-tier super_admin/admin/employee model
     * was deleted from database/migrations before it could be rolled back
     * normally, leaving the database out of sync with the (reverted)
     * UserRoleEnum.php.
     */
    public function up(): void
    {
        // Step 1: widen the column so we can freely rewrite values without
        // the current (new) enum constraint rejecting the old ones.
        DB::statement("ALTER TABLE `users` MODIFY `role` VARCHAR(30) NOT NULL DEFAULT 'inventory_user'");

        // Step 2: map the new values back to the old ones.
        DB::table('users')->where('role', 'admin')->update(['role' => 'inventory_manager']);
        DB::table('users')->where('role', 'employee')->update(['role' => 'inventory_user']);
        // 'super_admin' needs no change either direction.

        // Step 3: restore the original enum constraint.
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('super_admin', 'inventory_manager', 'inventory_user') NOT NULL DEFAULT 'inventory_user'");
    }

    /**
     * No down() — this migration is itself a manual revert. If you ever
     * want to move to the 3-tier model again, restore UserRoleEnum.php and
     * write a fresh forward migration for that, rather than trying to
     * "undo" this one.
     */
    public function down(): void
    {
        //
    }
};
