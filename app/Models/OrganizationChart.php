<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationChart extends Model
{
    /**
     * Exact table naming matching the physical schema configuration.
     */
    protected $table = 'organizationchart';

    /**
     * Columns matching database layout parameters.
     */
    protected $fillable = [
        'Name',
        'Position',
    ];
}