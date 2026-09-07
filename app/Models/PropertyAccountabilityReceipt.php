<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyAccountabilityReceipt extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'property_accountability_receipt_record';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Qty',
        'Unit',
        'Descrp',
        'PropNo',
        'ReceivedFrom_Name',
        'ReceivedFrom_Position',
        'ReceivedFrom_Date',
        'ReceivedBy_Name',
        'ReceivedBy_Position',
        'ReceivedBy_Date',
        'PAR',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'Qty' => 'integer',
        'ReceivedFrom_Date' => 'date:Y-m-d',
        'ReceivedBy_Date' => 'date:Y-m-d',
    ];
}