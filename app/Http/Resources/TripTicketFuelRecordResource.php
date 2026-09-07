<?php
// app/Http/Resources/TripTicketFuelRecordResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripTicketFuelRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'fuel_liters' => $this->fuel_liters,
            'fuel_cost' => $this->fuel_cost,
            'odometer_start' => $this->odometer_start,
            'odometer_end' => $this->odometer_end,
            'fuel_date' => $this->fuel_date?->toDateString(),
            'station_name' => $this->station_name,
            'or_number' => $this->or_number,
            'recorded_by' => $this->recorded_by,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'trip' => new TripTicketRecordResource($this->whenLoaded('trip')),
            'recorder' => new EmpAccountsRecordResource($this->whenLoaded('recorder')),
        ];
    }
}