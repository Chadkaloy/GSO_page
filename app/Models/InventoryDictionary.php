<?php
// app/Models/InventoryDictionary.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryDictionary extends Model
{
    protected $table = 'inventory_dictionary';
    protected $primaryKey = 'Invent_ID';

    protected $fillable = [
        'AC_COA_Cir_04-08',
        'AC_COA_Cir_015-09',
        'AC_Name(Old)',
        'AC_name(New)',
    ];

    // Relationships
    public function bincardRecords()
    {
        return $this->hasMany(BincardRecord::class, 'itemCode', 'AC_COA_Cir_04-08');
    }

    public function accountabilityCards()
    {
        return $this->hasMany(EmpAccountabilityCard::class, 'itemCode', 'AC_COA_Cir_04-08');
    }
}
