<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CertificationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'violation' => $this->violation,
            'date_of_violation' => $this->date_of_violation,
            'ordinance_no' => $this->ordinance_no,
            'amount' => $this->amount,
            'receipt_no' => $this->receipt_no,
            'issued_date' => $this->issued_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
