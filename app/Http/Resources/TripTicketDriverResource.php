<?php
// app/Http/Resources/TripTicketDriverResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripTicketDriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_code' => $this->driver_code,
            'full_name' => $this->full_name,
            'license_no' => $this->license_no,
            'license_expiry' => $this->license_expiry?->toDateString(),
            'contact_no' => $this->contact_no,
            'address' => $this->address,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'trips' => TripTicketRecordResource::collection($this->whenLoaded('trips')),
        ];
    }
}