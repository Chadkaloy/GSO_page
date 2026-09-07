<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpAccountsRecord extends Model
{
    use HasFactory;

    protected $table = 'emp_accounts_record';
    protected $primaryKey = 'accID';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'accLevel',
        // username/password/Email below are legacy — real login now lives on
        // the users table via user_id. Kept in $fillable only so existing
        // rows/data aren't broken; the Employee form no longer collects or
        // updates these.
        'username',
        'password',
        'fullName',
        'Age',
        'Gender',
        'Address',
        'Email',
        'Pos',
        'Mobile',
        'image',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * The login account this employee/role record is assigned to.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}