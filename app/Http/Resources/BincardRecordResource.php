<?php
// app/Http/Resources/BincardRecordResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BincardRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bin_Date' => $this->bin_Date?->toDateString(),
            'Supplier' => $this->Supplier,
            'Descrp' => $this->Descrp,
            'Qty' => $this->Qty,
            'Issued' => $this->Issued,
            'Balance' => $this->Balance,
            'PoNo' => $this->PoNo,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'issued_records' => BincardIssuedRecordResource::collection($this->whenLoaded('issuedRecords')),
            'inventory_item' => new InventoryDictionaryResource($this->whenLoaded('inventoryItem')),
        ];
    }
}