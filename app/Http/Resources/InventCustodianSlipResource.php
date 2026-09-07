<?php
// app/Http/Resources/InventCustodianSlipResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventCustodianSlipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'Qty' => $this->Qty,
            'Unit' => $this->Unit,
            'Descrp' => $this->Descrp,
            'Invent_Item_No' => $this->Invent_Item_No,
            'Ez_Useful_Life' => $this->Ez_Useful_Life,
            'ReceivedBy_Name' => $this->ReceivedBy_Name,
            'ReceivedBy_Position' => $this->ReceivedBy_Position,
            'ReceiveBy_Date' => $this->ReceiveBy_Date?->toDateString(),
            'ReceivedFrom_Name' => $this->ReceivedFrom_Name,
            'ReceivedFrom_Position' => $this->ReceivedFrom_Position,
            'ReceiveFrom_Date' => $this->ReceiveFrom_Date?->toDateString(),
            'ICS' => $this->ICS,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'descriptions' => InventCustodianSlipDescrpResource::collection($this->whenLoaded('descriptions')),
        ];
    }
}