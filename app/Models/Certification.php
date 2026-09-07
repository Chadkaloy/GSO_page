<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'violation',
        'date_of_violation',
        'ordinance_no',
        'amount',
        'receipt_no',
        'issued_date',
    ];

    protected $casts = [
        'date_of_violation' => 'date',
        'issued_date' => 'date',
        'amount' => 'decimal:2',
    ];
}
