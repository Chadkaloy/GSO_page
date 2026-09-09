<?php
// app/Models/OfficeFuelAllocationTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeFuelAllocationTransaction extends Model
{
    protected $table = 'office_fuel_allocation_transactions';

    protected $fillable = [
        'office_fuel_allocation_id',
        'trip_id',
        'type',
        'liters',
        'note',
    ];

    public function allocation()
    {
        return $this->belongsTo(OfficeFuelAllocation::class, 'office_fuel_allocation_id');
    }
}
