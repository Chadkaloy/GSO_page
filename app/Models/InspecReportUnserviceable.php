<?php
// app/Models/InspecReportUnserviceable.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspecReportUnserviceable extends Model
{
    protected $table = 'inspec_report_of_unserviceable_prop';

    // No specific fillable fields yet as the table only has timestamps and id
    protected $fillable = [];
}
