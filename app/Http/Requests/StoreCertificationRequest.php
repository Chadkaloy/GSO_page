<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'violation' => 'required|string',
            'date_of_violation' => 'required|date',
            'ordinance_no' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'receipt_no' => 'required|string|max:255',
            'issued_date' => 'required|date',
        ];
    }
}
