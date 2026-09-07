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
        Schema::table('assets', function (Blueprint $table) {
            // Add category_id column and foreign key constraint
            // ->constrained() will assume 'categories' table and 'id' column
            // ->onDelete('set null') means if a category is deleted, assets in that category will have category_id set to null
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('categories')
                  ->onDelete('set null')
                  ->after('id'); // Place it after the 'id' column

            // Add location_id column and foreign key constraint
            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('locations')
                  ->onDelete('set null')
                  ->after('category_id'); // Place it after the 'category_id' column

            // Add manufacturer_id column and foreign key constraint
            $table->foreignId('manufacturer_id')
                  ->nullable()
                  ->constrained('manufacturers')
                  ->onDelete('set null')
                  ->after('location_id'); // Place it after the 'location_id' column

            // Add assigned_to_user_id column and foreign key constraint
            // This assumes Laravel's default 'users' table exists.
            $table->foreignId('assigned_to_user_id')
                  ->nullable()
                  ->constrained('users') // Explicitly specify 'users' table
                  ->onDelete('set null')
                  ->after('notes'); // Place it after 'notes' or where appropriate
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['category_id']);
            $table->dropForeign(['location_id']);
            $table->dropForeign(['manufacturer_id']);
            $table->dropForeign(['assigned_to_user_id']);

            // Then drop the columns
            $table->dropColumn(['category_id', 'location_id', 'manufacturer_id', 'assigned_to_user_id']);
        });
    }
};
