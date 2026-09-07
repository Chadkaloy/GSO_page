<?php
// app/Models/TripTicketApprovalLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTicketApprovalLog extends Model
{
    protected $table = 'trip_ticket_approval_log';

    protected $fillable = [
        'trip_id',
        'approver_id',
        'action',
        'comments',
        'action_date',
    ];

    // Relationships
    public function trip()
    {
        return $this->belongsTo(TripTicketRecord::class, 'trip_id');
    }

    public function approver()
    {
        return $this->belongsTo(EmpAccountsRecord::class, 'approver_id', 'accID');
    }
}