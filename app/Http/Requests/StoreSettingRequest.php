<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettingRequest extends FormRequest
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
        $rules = [
            'type'      => 'required|string|max:100',
            'entity_id' => 'nullable|uuid',
            'key'       => 'required|string|max:255',
            'value'     => 'nullable|string',
            'options'   => 'string'
        ];

        if ($this->input('key') === 'seller_session_mode') {
            $rules['value'] = 'required|string|in:AFTER_EACH_SALE,PERSISTENT';
        }

        if (in_array($this->input('key'), ['allow_proformas', 'allow_invoice_drafts'], true)) {
            $rules['value'] = 'required|string|in:true,false';
        }

        return $rules;
    }
}
