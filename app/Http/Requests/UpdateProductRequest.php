<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
    protected function prepareForValidation(): void
    {
        if (is_string($this->material_profile)) {
            $decoded = json_decode($this->material_profile, true);
            if (json_last_error() === JSON_ERROR_NONE) $this->merge(['material_profile' => $decoded]);
        }
    }

    public function rules(): array
    {
        return [
            'product_usage' => ['sometimes', 'required', 'in:sale,supply,both'],
            'material_profile' => ['sometimes', 'nullable', 'array'],
            'sku' => ['string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cost' => ['numeric'],
            'price' => ['numeric'],
            'min_stock' => ['numeric'],
            'unit_of_measure' => ['string', 'max:255'],
            'inventory' => ['nullable', 'string'],
            'update_all_inventories' => ['nullable', 'boolean'],
            'suppliers' => ['nullable'],
            'categories' => ['nullable'],
            'tags' => ['nullable'],
        ];
    }
}
