<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category , // Include category name
            'location' => $this->location, // Include location name
            'manufacturer' => $this->manufacturer, // Include manufacturer name
            'assigned_to' => $this->assignedTo, // Include assigned user name
            'asset_tag' => $this->asset_tag,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'model_name' => $this->model_name,
            'purchase_date' => $this->purchase_date ? $this->purchase_date->toDateString() : null, // Format date
            'purchase_price' => $this->purchase_price,
            'status' => $this->status->value ?? null, // Assuming `status` is an enum
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null, // Include creation timestamp
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null, // Include update timestamp
        ];
    }
}
