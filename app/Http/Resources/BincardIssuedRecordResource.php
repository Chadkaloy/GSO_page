<?php
// app/Http/Resources/BincardIssuedRecordResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BincardIssuedRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ItemSetID' => $this->ItemSetID,
            'itemCode' => $this->itemCode,
            'bin_ID' => $this->bin_ID,
            'recpnt' => $this->recpnt,
            'issued_date' => $this->issued_date?->toDateString(),
            'qty' => $this->qty,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            // Relationships
            'bincard' => new BincardRecordResource($this->whenLoaded('bincard')),
            'inventory_item' => new InventoryDictionaryResource($this->whenLoaded('inventoryItem')),
        ];
    }
}