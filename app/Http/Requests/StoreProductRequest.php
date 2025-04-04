<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'sku' => ['string', 'max:255'],
            'barcode' => ['string', 'max:255', 'unique:products,barcode', 'nullable'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['string'],
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'cost' => ['required', 'numeric'],
            'price' => ['required', 'numeric'],
            'min_stock' => ['numeric'],
            'unit_of_measure' => ['required', 'string', 'max:255'],
            'inventory_id' => ['uuid', 'exists:inventories,id'],
        ];
    }
}
