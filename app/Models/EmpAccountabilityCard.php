<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpAccountabilityCard extends Model
{
    protected $table = 'emp_accountability_card';

    protected $fillable = [
        'Emp_ID',
        'ItemSetID',
        'itemCode',
        'ParNo',
        'Qty',
        'Unit',
        'Descrp',
        'SN',
        'PropNo',
        'Amount',
        'TransferTo',
        'Remarks',
        'DateTurnOver',
    ];

    /**
     * Relationship with the Employee Accounts Record
     */
    public function employee()
    {
        return $this->belongsTo(EmpAccountsRecord::class, 'Emp_ID', 'accID');
    }

    /**
     * Relationship with the Inventory Dictionary
     */
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryDictionary::class, 'itemCode', 'AC_COA_Cir_04-08');
    }
}