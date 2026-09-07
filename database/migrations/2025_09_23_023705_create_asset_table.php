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
        Schema::create('assets', function (Blueprint $table) {
            $table->id(); 
            $table->string('asset_tag')->unique(); 
            $table->string('name'); 
            $table->string('serial_number')->unique()->nullable(); 
            $table->string('model_name')->nullable(); 
            $table->date('purchase_date')->nullable(); 
            $table->decimal('purchase_price', 10, 2)->nullable(); 
            
            $table->enum('status', ['Deployed', 'In Storage', 'Maintenance', 'Retired', 'Broken'])->default('In Storage');
            $table->text('notes')->nullable(); 
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
