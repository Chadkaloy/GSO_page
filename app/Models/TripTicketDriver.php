<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripTicketDriver extends Model
{
    protected $table = 'trip_ticket_drivers';

    protected $fillable = [
        'driver_code',
        'full_name',
        'license_no',
        'license_expiry',
        'contact_no',
        'address',
        'status',
        'remarks',
    ];

    /**
     * Cast attributes to native types.
     */
    protected $casts = [
        'license_expiry' => 'date:Y-m-d',
    ];

    /**
     * Link driver to dispatch records.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(TripTicketRecord::class, 'driver_id');
    }
}