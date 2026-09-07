<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeDictionary extends Model
{
    protected $table = 'office_dictionary';

    protected $fillable = [
        'officeName',
        'officeCode',
    ];

    public function employees()
    {
        return $this->hasMany(EmpAccountsRecord::class, 'office', 'officeName');
    }
}