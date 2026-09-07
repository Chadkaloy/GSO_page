<?php
// app/Http/Resources/TripTicketVehicleResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripTicketVehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_no' => $this->plate_no,
            'vehicle_type' => $this->vehicle_type,
            'brand' => $this->brand,
            'model' => $this->model,
            'year_model' => $this->year_model,
            'color' => $this->color,
            'engine_no' => $this->engine_no,
            'chassis_no' => $this->chassis_no,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'trips' => TripTicketRecordResource::collection($this->whenLoaded('trips')),
            'maintenances' => TripTicketMaintenanceResource::collection($this->whenLoaded('maintenances')),
        ];
    }
}