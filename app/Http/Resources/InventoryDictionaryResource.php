<?php
// app/Http/Resources/InventoryDictionaryResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDictionaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'Invent_ID' => $this->Invent_ID,
            'AC_COA_Cir_04-08' => $this->{'AC_COA_Cir_04-08'},
            'AC_COA_Cir_015-09' => $this->{'AC_COA_Cir_015-09'},
            'AC_Name(Old)' => $this->{'AC_Name(Old)'},
            'AC_name(New)' => $this->{'AC_name(New)'},
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}