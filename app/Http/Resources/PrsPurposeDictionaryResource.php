<?php
// app/Http/Resources/PrsPurposeDictionaryResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrsPurposeDictionaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ID' => $this->ID,
            'Purpose_Type' => $this->Purpose_Type,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}