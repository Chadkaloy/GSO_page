<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventFurnitureFixtures extends Model
{
    /**
     * Exact table naming configuration matching database schema
     */
    protected $table = 'invent_222_1_07_07_010_furniture_fixtures';

    /**
     * Managed columns parameters strictly mapped from database setup columns
     */
    protected $fillable = [
        'accCode',
        'ParNo',
        'Qty',
        'Unit',
        'Descrp',
        'UnitCost',
        'TotalCost',
        'PropNo',
        'AccPerson',
        'Designation_office',
        'dateRelease',
        'Supplier',
        'Remarks',
    ];

    /**
     * Direct explicit type casting to align dataset payloads
     */
    protected $casts = [
        'id'        => 'integer',
        'ParNo'     => 'integer',
        'Qty'       => 'integer',
        'UnitCost'  => 'integer',
        'TotalCost' => 'integer',
        'dateRelease' => 'date:Y-m-d',
    ];
}