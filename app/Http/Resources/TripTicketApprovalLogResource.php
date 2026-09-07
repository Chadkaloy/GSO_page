<?php
// app/Http/Resources/TripTicketApprovalLogResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripTicketApprovalLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'approver_id' => $this->approver_id,
            'action' => $this->action,
            'comments' => $this->comments,
            'action_date' => $this->action_date?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'trip' => new TripTicketRecordResource($this->whenLoaded('trip')),
            'approver' => new EmpAccountsRecordResource($this->whenLoaded('approver')),
        ];
    }
}