<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTicketMaintenance extends Model
{
    protected $table = 'trip_ticket_maintenance';

    protected $fillable = [
        'vehicle_id',
        'maintenance_date',
        'maintenance_type',
        'description',
        'cost',
        'service_provider',
        'status',
        'next_maintenance_date',
        'remarks',
        'recorded_by',
    ];

    public function vehicle()
    {
        return $this->belongsTo(TripTicketVehicle::class, 'vehicle_id');
    }

    public function recorder()
    {
        return $this->belongsTo(EmpAccountsRecord::class, 'recorded_by', 'accID');
    }
}