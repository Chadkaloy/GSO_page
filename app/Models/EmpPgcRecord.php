<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpPgcRecord extends Model
{
    use HasFactory;

    protected $table = 'emp_pgc_record';
    protected $primaryKey = 'accID';
    
    // Explicitly declaring structural table settings from the schema properties image file
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'fullName',
        'office',
        'designation',
        'note',
    ];

    public function employee()
    {
        return $this->belongsTo(EmpAccountsRecord::class, 'accID', 'accID');
    }
}