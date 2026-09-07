<?php
// app/Http/Resources/TripTicketRecordResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripTicketRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_no' => $this->trip_no,
            'date_requested' => $this->date_requested?->toDateString(),
            'requester_name' => $this->requester_name,
            'requester_office' => $this->requester_office,
            'destination' => $this->destination,
            'purpose' => $this->purpose,
            'time_departure' => $this->time_departure?->toDateTimeString(),
            'time_return' => $this->time_return?->toDateTimeString(),
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'passenger_count' => $this->passenger_count,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'approved_by' => $this->approved_by,
            'approved_date' => $this->approved_date?->toDateTimeString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'vehicle' => new TripTicketVehicleResource($this->whenLoaded('vehicle')),
            'driver' => new TripTicketDriverResource($this->whenLoaded('driver')),
            'passengers' => TripTicketPassengerResource::collection($this->whenLoaded('passengers')),
            'approvals' => TripTicketApprovalLogResource::collection($this->whenLoaded('approvals')),
            'fuel_records' => TripTicketFuelRecordResource::collection($this->whenLoaded('fuelRecords')),
            'approver' => new EmpAccountsRecordResource($this->whenLoaded('approver')),
            'creator' => new EmpAccountsRecordResource($this->whenLoaded('creator')),
        ];
    }
}