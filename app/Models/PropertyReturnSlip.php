<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyReturnSlip extends Model
{
    /**
     * The table associated with the model matching schema image exactly.
     *
     * @var string
     */
    protected $table = 'property_return_slip_record';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'LGU_Name',
        'PurposeID',
        'Qty',
        'Unit',
        'Descrp',
        'Serial_Num',
        'Prop_Number',
        'ParNo',
        'Name_of_Enduser',
        'Unit_Value',
        'Total_Value',
        'Status',
        'ReceiveBy_Name',
        'ReceiveBy_Position',
        'ReceiveBy_Date',
        'ReceiveFrom_Name',
        'ReceiveFrom_Position',
        'ReceiveFrom_Date',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'PurposeID'          => 'integer',
        'Qty'                => 'integer',
        'Unit_Value'         => 'integer',
        'Total_Value'        => 'integer',
        'ReceiveBy_Date'     => 'date:Y-m-d',
        'ReceiveFrom_Date'   => 'date:Y-m-d',
    ];

    /**
     * Relationship with the purpose dictionary lookup
     */
    public function purpose()
    {
        return $this->belongsTo(PrsPurposeDictionary::class, 'PurposeID', 'ID');
    }
}