<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftInvoiceRequest extends FormRequest
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
            'store_id' => 'nullable',
            'invoice_date' => 'nullable|date',
            'invoice_note' => 'nullable|string',
            'client_id' => 'nullable',
            'client_name' => 'nullable|string',
            'total' => 'nullable',
            'discount' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'grand_total' => 'nullable|numeric',
            'payment_method' => 'nullable|string',
            'payment_date' => 'nullable|date',
            'payment_metadata' => 'nullable|array',
            'products' => 'nullable',
            'source' => 'nullable|string|in:POS,ADMIN,MOBILE_APP,ECOMMERCE',
            'seller_id' => 'nullable|string',
            'cash_session_id' => 'nullable|string',
        ];
    }
}
