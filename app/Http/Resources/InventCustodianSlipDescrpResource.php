<?php
// app/Http/Resources/InventCustodianSlipDescrpResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventCustodianSlipDescrpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'icsID' => $this->icsID,
            'Descrp' => $this->Descrp,
            'Invent_Item_No' => $this->Invent_Item_No,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'custodian_slip' => new InventCustodianSlipResource($this->whenLoaded('custodianSlip')),
        ];
    }
}