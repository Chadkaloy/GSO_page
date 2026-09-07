<?php
// app/Models/TripTicketPassenger.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTicketPassenger extends Model
{
    protected $table = 'trip_ticket_passengers';

    protected $fillable = [
        'trip_id',
        'passenger_name',
        'office',
        'contact_no',
    ];

    // Relationships
    public function trip()
    {
        return $this->belongsTo(TripTicketRecord::class, 'trip_id');
    }
}