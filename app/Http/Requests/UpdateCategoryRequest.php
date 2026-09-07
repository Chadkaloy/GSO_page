<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or authorization logic for updating a category
    }

    public function rules(): array
    {
        // Get the category ID from the route parameters
        $itemid = $this->route('category'); // Assumes your route parameter is named 'category'

        return [
            // Ignore the current category's name for unique check during update
            'name' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($itemid)],
            'description' => 'nullable|string|max:500',
        ];
    }
}