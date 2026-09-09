<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTicketRecord extends Model
{
    protected $table = 'trip_ticket_record';

    protected $fillable = [
        'trip_no',
        'date_requested',
        'requester_name',
        'requester_office',
        'destination',
        'purpose',
        'estimated_liters',
        'time_departure',
        'time_return',
        'vehicle_id',
        'driver_id',
        'passenger_count',
        'status',
        'remarks',
        'approved_by',
        'approved_date',
        'created_by',
    ];

    // Relationships
    public function vehicle()
    {
        return $this->belongsTo(TripTicketVehicle::class, 'vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(TripTicketDriver::class, 'driver_id');
    }

    public function passengers()
    {
        return $this->hasMany(TripTicketPassenger::class, 'trip_id');
    }

    public function approvals()
    {
        return $this->hasMany(TripTicketApprovalLog::class, 'trip_id');
    }

    public function fuelRecords()
    {
        return $this->hasMany(TripTicketFuelRecord::class, 'trip_id');
    }

    public function approver()
    {
        return $this->belongsTo(EmpAccountsRecord::class, 'approved_by', 'accID');
    }

    public function creator()
    {
        return $this->belongsTo(EmpAccountsRecord::class, 'created_by', 'accID');
    }
}