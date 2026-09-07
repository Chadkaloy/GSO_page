<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrsPurposeDictionary extends Model
{
    // The database table name associated with the model
    protected $table = 'prs_purpose_dictionary';
    
    // Explicitly set the custom capitalized primary key name
    protected $primaryKey = 'ID';
    
    // Tell Laravel the database automatically increments the ID
    public $incrementing = true;
    
    // Define the data type of the primary key
    protected $keyType = 'integer';

    // Fields that can be safely mass-assigned via Eloquent
    protected $fillable = [
        'Purpose_Type', // 'ID' is removed so it relies purely on database generation
    ];

    /**
     * Relationships
     */
    public function propertyReturnSlips()
    {
        return $this->hasMany(PropertyReturnSlip::class, 'PurposeID', 'ID');
    }
}