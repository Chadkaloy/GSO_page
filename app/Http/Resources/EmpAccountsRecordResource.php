<?php
// app/Http/Resources/EmpAccountsRecordResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpAccountsRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'accID' => $this->accID,
            'accLevel' => $this->accLevel,
            'username' => $this->username,
            'fullName' => $this->fullName,
            'Age' => $this->Age,
            'Gender' => $this->Gender,
            'Address' => $this->Address,
            'Email' => $this->Email,
            'Pos' => $this->Pos,
            'Mobile' => $this->Mobile,
            'image' => $this->image,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'pgc_record' => new EmpPgcRecordResource($this->whenLoaded('pgcRecord')),
            'accountability_cards' => EmpAccountabilityCardResource::collection($this->whenLoaded('accountabilityCards')),
        ];
    }
}