<?php
// app/Http/Resources/EmpPgcRecordResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpPgcRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'accID' => $this->accID,
            'fullName' => $this->fullName,
            'office' => $this->office,
            'designation' => $this->designation,
            'note' => $this->note,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'employee' => new EmpAccountsRecordResource($this->whenLoaded('employee')),
        ];
    }
}