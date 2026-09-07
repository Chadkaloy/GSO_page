<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventCustodianSlipDescrp extends Model
{
    /**
     * Table name exactly matches the database layout mapping
     */
    protected $table = 'invent_custodian_slip_descrp';

    /**
     * Managed columns parameters strictly mapped from database setup columns
     */
    protected $fillable = [
        'icsID',
        'Descrp',
        'Invent_Item_No',
    ];

    /**
     * Data casting layers
     */
    protected $casts = [
        'id'    => 'integer',
        'icsID' => 'integer',
    ];

    /**
     * Parent relationship mapping back onto the main Custodian Slip
     */
    public function custodianSlip()
    {
        return $this->belongsTo(InventCustodianSlip::class, 'icsID', 'id');
    }
}