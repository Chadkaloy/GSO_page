<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BincardIssuedRecord extends Model
{
    protected $table = 'bincard_issued_record';

    protected $fillable = [
        'ItemSetID',
        'itemCode',
        'bin_ID',
        'recpnt',
        'issued_date',
        'qty',
    ];

    public function bincard()
    {
        return $this->belongsTo(BincardRecord::class, 'bin_ID', 'id');
    }
}