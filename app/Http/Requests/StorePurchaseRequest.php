<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
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
            'user_id' => 'required|uuid|exists:users,id',
            'organization_id' => 'required|uuid|exists:organizations,id',
            'store_id' => 'required|uuid|exists:stores,id',
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'inventory_id' => 'required|uuid|exists:inventories,id',
            'total' => 'required|numeric',
            'purchase_date' => 'required|date',
            'purchase_note' => 'nullable|string',
            'total_items' => 'required|numeric',
        ];
    }
}
