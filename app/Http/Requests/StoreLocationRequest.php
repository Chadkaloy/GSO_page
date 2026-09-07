<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
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
            'name' => 'required|string|min:3|max:255|unique:locations,name', // Added unique rule for creation
            'address' => 'required|string|max:500',
        ];
    }

    // Optional: Custom error messages
    public function messages(): array
    {
        return [
            'name.unique' => 'A location with this name already exists.',
        ];
    }
}
