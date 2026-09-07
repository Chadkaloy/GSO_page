<?php
// app/Http/Resources/PropertyReturnSlipResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyReturnSlipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'LGU_Name' => $this->LGU_Name,
            'PurposeID' => $this->PurposeID,
            'Qty' => $this->Qty,
            'Unit' => $this->Unit,
            'Descrp' => $this->Descrp,
            'Serial_Num' => $this->Serial_Num,
            'Prop_Number' => $this->Prop_Number,
            'ParNo' => $this->ParNo,
            'Name_of_Enduser' => $this->Name_of_Enduser,
            'Unit_Value' => $this->Unit_Value,
            'Total_Value' => $this->Total_Value,
            'Status' => $this->Status,
            'ReceiveBy_Name' => $this->ReceiveBy_Name,
            'ReceiveBy_Position' => $this->ReceiveBy_Position,
            'ReceiveBy_Date' => $this->ReceiveBy_Date?->toDateString(),
            'ReceiveFrom_Name' => $this->ReceiveFrom_Name,
            'ReceiveFrom_Position' => $this->ReceiveFrom_Position,
            'ReceiveFrom_Date' => $this->ReceiveFrom_Date?->toDateString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'purpose' => new PrsPurposeDictionaryResource($this->whenLoaded('purpose')),
        ];
    }
}