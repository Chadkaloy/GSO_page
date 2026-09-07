<?php
// app/Http/Resources/TripTicketMaintenanceResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripTicketMaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'maintenance_date' => $this->maintenance_date?->toDateString(),
            'maintenance_type' => $this->maintenance_type,
            'description' => $this->description,
            'cost' => $this->cost,
            'service_provider' => $this->service_provider,
            'status' => $this->status,
            'next_maintenance_date' => $this->next_maintenance_date?->toDateString(),
            'remarks' => $this->remarks,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'vehicle' => new TripTicketVehicleResource($this->whenLoaded('vehicle')),
            'recorder' => new EmpAccountsRecordResource($this->whenLoaded('recorder')),
        ];
    }
}