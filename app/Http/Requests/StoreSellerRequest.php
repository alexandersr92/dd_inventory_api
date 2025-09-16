<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSellerRequest extends FormRequest
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
            'name'            => ['required', 'string', 'max:255'],
            'code'            => ['required', 'string', 'max:8'],
            'pin'             => ['required', 'string', 'min:4', 'max:10'],
            // status opcional; por defecto 'active' en el controlador
            'status'          => ['sometimes', 'string', 'in:active,inactive,blocked'],

            // Relación muchos-a-muchos con stores
            'stores'          => ['required', 'array', 'min:1'],
            'stores.*'        => ['uuid', 'exists:stores,id'],
        ];
    }
}
