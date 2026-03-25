<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerRequest extends FormRequest
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
            // Todos los campos son opcionales en update
            'name'   => ['sometimes', 'string', 'max:255'],
            'code'   => ['sometimes', 'string', 'max:8'],
            'status' => ['sometimes', 'string', 'in:active,inactive,blocked'],
            // Permitir cambiar el PIN
            'pin'    => ['sometimes', 'nullable', 'string', 'min:4', 'max:10'],
            // Manejar relación muchos-a-muchos con stores
            'stores'   => ['sometimes', 'array'],
            'stores.*' => ['uuid', 'exists:stores,id'],
        ];
    }
}
