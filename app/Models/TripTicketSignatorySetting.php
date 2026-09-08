<?php
// app/Models/TripTicketSignatorySetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTicketSignatorySetting extends Model
{
    protected $table = 'trip_ticket_signatory_settings';

    protected $fillable = [
        'municipal_administrator_name',
        'municipal_mayor_name',
    ];
}