<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string',
            'violation' => 'sometimes|required|string',
            'date_of_violation' => 'sometimes|required|date',
            'ordinance_no' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'receipt_no' => 'sometimes|required|string|max:255',
            'issued_date' => 'sometimes|required|date',
        ];
    }
}
