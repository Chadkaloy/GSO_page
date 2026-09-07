<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManufacturerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $itemid = $this->route('manufacturer');
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('manufacturers')->ignore($itemid)],
            'url' => 'nullable|url|max:255', // URL is optional but must be a valid URL if provided
            'support_url' => 'nullable|url|max:255', // Support URL is optional but must be a valid URL if provided
            'support_phone' => 'required|string|max:20', // Support phone is optional but must be a string with a max length of 20
            'support_email' => 'required|email|max:255',
        ];
    }
}
