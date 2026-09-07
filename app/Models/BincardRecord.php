<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BincardRecord extends Model
{
    use HasFactory;

    // Maps directly to your 'bincard_record' table in HeidiSQL
    protected $table = 'bincard_record';

    protected $fillable = [
        'bin_Date',
        'Supplier',
        'Descrp',
        'Qty',
        'Issued',
        'Balance',
        'PoNo',
    ];
}