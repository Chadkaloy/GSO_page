<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventCustodianSlip extends Model
{
    /**
     * Table name exactly matches the database layout mapping
     */
    protected $table = 'invent_custodian_slip';

    /**
     * Array specifications matching schema allocations
     */
    protected $fillable = [
        'Qty',
        'Unit',
        'Descrp',
        'Invent_Item_No',
        'Ez_Useful_Life',
        'ReceivedBy_Name',
        'ReceivedBy_Position',
        'ReceiveBy_Date',
        'ReceivedFrom_Name',
        'ReceivedFrom_Position',
        'ReceiveFrom_Date',
        'ICS',
    ];

    /**
     * Standardized casting properties matching database datatypes directly
     */
    protected $casts = [
        'id'               => 'integer',
        'Qty'              => 'integer',
        'ICS'              => 'integer',
        'ReceiveBy_Date'   => 'date:Y-m-d',
        'ReceiveFrom_Date' => 'date:Y-m-d',
    ];

    /**
     * Relational dependency to child segment parameters
     */
    public function descriptions()
    {
        return $this->hasMany(InventCustodianSlipDescrp::class, 'icsID');
    }
}