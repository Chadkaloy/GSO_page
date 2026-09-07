<?php
// app/Http/Resources/PropertyAccountabilityReceiptResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyAccountabilityReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'Qty' => $this->Qty,
            'Unit' => $this->Unit,
            'Descrp' => $this->Descrp,
            'PropNo' => $this->PropNo,
            'ReceivedFrom_Name' => $this->ReceivedFrom_Name,
            'ReceivedFrom_Position' => $this->ReceivedFrom_Position,
            'ReceivedFrom_Date' => $this->ReceivedFrom_Date?->toDateString(),
            'ReceivedBy_Name' => $this->ReceivedBy_Name,
            'ReceivedBy_Position' => $this->ReceivedBy_Position,
            'ReceivedBy_Date' => $this->ReceivedBy_Date?->toDateString(),
            'PAR' => $this->PAR,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}