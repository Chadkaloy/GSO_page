<?php
// app/Models/OfficeFuelAllocation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeFuelAllocation extends Model
{
    protected $table = 'office_fuel_allocations';

    protected $fillable = [
        'office_id',
        'year',
        'liters_allocated',
        'liters_remaining',
    ];

    // No formal DB-level FK constraint (see migration comment), but this
    // relation is here for future use once office_dictionary's schema is
    // confirmed — referenced lazily so it never breaks anything unused.
    public function office()
    {
        return $this->belongsTo(\App\Models\OfficeDictionary::class, 'office_id');
    }

    public function transactions()
    {
        return $this->hasMany(OfficeFuelAllocationTransaction::class, 'office_fuel_allocation_id');
    }
}