<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
          
            'store_id' => 'required',
            'invoice_date' => 'required|date',
            'invoice_note' => 'nullable | string',
            'client_name' => 'required',
            'total' => 'required',
            'discount' => 'required',
            'tax' => 'required',
            'grand_total' => 'required',
            'payment_method' => 'required',
            'payment_date' => 'required|date',
            'products' => 'required',
        ];
    }
}
