<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->text('violation');
            $table->date('date_of_violation');
            $table->string('ordinance_no');
            $table->decimal('amount', 10, 2);
            $table->string('receipt_no');
            $table->date('issued_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('certifications');
    }
};
