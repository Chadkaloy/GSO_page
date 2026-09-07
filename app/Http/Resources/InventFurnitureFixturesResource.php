<?php
// app/Http/Resources/InventFurnitureFixturesResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventFurnitureFixturesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accCode' => $this->accCode,
            'ParNo' => $this->ParNo,
            'Qty' => $this->Qty,
            'Unit' => $this->Unit,
            'Descrp' => $this->Descrp,
            'UnitCost' => $this->UnitCost,
            'TotalCost' => $this->TotalCost,
            'PropNo' => $this->PropNo,
            'AccPerson' => $this->AccPerson,
            'Designation_office' => $this->Designation_office,
            'dateRelease' => $this->dateRelease?->toDateString(),
            'Supplier' => $this->Supplier,
            'Remarks' => $this->Remarks,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}