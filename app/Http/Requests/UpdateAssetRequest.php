<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\AssetStatusEnum;
class UpdateAssetRequest extends FormRequest
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
        $asset = $this->route('asset'); // Assumes the route parameter is named 'asset'
        return [
            'category_id' => 'required|exists:categories,id', // Must reference an existing category
            'location_id' => 'nullable|exists:locations,id', // Optional but must reference an existing location
            'manufacturer_id' => 'nullable|exists:manufacturers,id', // Optional but must reference an existing manufacturer
            'assigned_to_user_id' => 'nullable|exists:users,id', // Optional but must reference an existing user
            'asset_tag' => 'nullable|string|max:255|unique:assets,asset_tag,' . $asset->id, // Optional but must be unique, ignoring the current asset
            'name' => 'required|string|max:255|unique:assets,name,' . $asset->id, // Required, unique, max 255 characters, ignoring the current asset
            'serial_number' => 'required|string|max:255|unique:assets,serial_number,' . $asset->id, // Required, unique, max 255 characters, ignoring the current asset
            'model_name' => 'nullable|string|max:255', // Optional, max 255 characters
            'purchase_date' => 'nullable|date', // Optional, must be a valid date
            'purchase_price' => 'nullable|numeric|min:0', // Optional, must be a positive number
            'status' => 'required|in:'. implode(',', array_column(AssetStatusEnum::cases(), 'value')), // Optional, must be one of the valid statuses
            'notes' => 'nullable|string|max:1000', // Optional, max 1000 characters
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'The category is required.',
            'category_id.exists' => 'The selected category does not exist.',
            'location_id.exists' => 'The selected location does not exist.',
            'manufacturer_id.exists' => 'The selected manufacturer does not exist.',
            'assigned_to_user_id.exists' => 'The selected user does not exist.',
            'asset_tag.unique' => 'The asset tag must be unique.',
            'name.required' => 'The asset name is required.',
            'name.unique' => 'The asset name must be unique.',
            'serial_number.required' => 'The serial number is required.',
            'serial_number.unique' => 'The serial number must be unique.',
            'model_name.max' => 'The model name may not be greater than 255 characters.',
            'purchase_date.date' => 'The purchase date must be a valid date.',
            'purchase_price.numeric' => 'The purchase price must be a number.',
            'purchase_price.min' => 'The purchase price must be at least 0.',
            'status.in' => 'The status must be one of the following: Deployed,In Storage,Maintenance,Retired,Broken.',
            'notes.max' => 'The notes may not be greater than 1000 characters.',
        ];
    }
}
