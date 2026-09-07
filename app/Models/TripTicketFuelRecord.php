<?php
// app/Models/TripTicketFuelRecord.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTicketFuelRecord extends Model
{
    protected $table = 'trip_ticket_fuel_record';

    protected $fillable = [
        'trip_id',
        'fuel_liters',
        'fuel_cost',
        'odometer_start',
        'odometer_end',
        'fuel_date',
        'station_name',
        'or_number',
        'recorded_by',
        'remarks',
    ];

    // Relationships
    public function trip()
    {
        return $this->belongsTo(TripTicketRecord::class, 'trip_id');
    }

    public function recorder()
    {
        return $this->belongsTo(EmpAccountsRecord::class, 'recorded_by', 'accID');
    }
}