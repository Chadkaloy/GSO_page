<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManufacturerRequest extends FormRequest
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
        return [
            'name' => 'required|string|min:3|max:255|unique:manufacturers,name', // Added unique rule for creation
            'url' => 'nullable|url|max:255', // URL is optional but must be a valid URL if provided
            'support_url' => 'nullable|url|max:255', // Support URL is optional but must be a valid URL if provided
            'support_phone' => 'required|string|max:20', // Support phone is optional but must be a string with a max length of 20
            'support_email' => 'required|email|max:255',
        ];
    }

    // Optional: Custom error messages
    public function messages(): array
    {
        return [
            'name.unique' => 'A manufacturer with this name already exists.',
        ];
    }
}
