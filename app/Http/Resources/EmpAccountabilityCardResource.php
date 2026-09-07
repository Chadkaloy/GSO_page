<?php
// app/Http/Resources/EmpAccountabilityCardResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpAccountabilityCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'Emp_ID' => $this->Emp_ID,
            'ItemSetID' => $this->ItemSetID,
            'itemCode' => $this->itemCode,
            'ParNo' => $this->ParNo,
            'Qty' => $this->Qty,
            'Unit' => $this->Unit,
            'Descrp' => $this->Descrp,
            'SN' => $this->SN,
            'PropNo' => $this->PropNo,
            'Amount' => $this->Amount,
            'TransferTo' => $this->TransferTo,
            'Remarks' => $this->Remarks,
            'DateTurnOver' => $this->DateTurnOver?->toDateString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'employee' => new EmpAccountsRecordResource($this->whenLoaded('employee')),
            'inventory_item' => new InventoryDictionaryResource($this->whenLoaded('inventoryItem')),
        ];
    }
}