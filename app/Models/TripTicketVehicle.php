<?php
// app/Models/TripTicketVehicle.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTicketVehicle extends Model
{
    protected $table = 'trip_ticket_vehicles';

    protected $fillable = [
        'plate_no',
        'vehicle_type',
        'brand',
        'model',
        'year_model',
        'color',
        'engine_no',
        'chassis_no',
        'capacity',
        'status',
        'remarks',
    ];

    // Relationships
    public function trips()
    {
        return $this->hasMany(TripTicketRecord::class, 'vehicle_id');
    }

    public function maintenances()
    {
        return $this->hasMany(TripTicketMaintenance::class, 'vehicle_id');
    }
}
