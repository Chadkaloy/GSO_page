<?php
// app/Http/Resources/TripTicketPassengerResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripTicketPassengerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'passenger_name' => $this->passenger_name,
            'office' => $this->office,
            'contact_no' => $this->contact_no,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'trip' => new TripTicketRecordResource($this->whenLoaded('trip')),
        ];
    }
}