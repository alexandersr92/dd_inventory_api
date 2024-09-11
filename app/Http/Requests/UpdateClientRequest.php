<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'email|max:255',
            'phone' => 'string|max:255',
            'address' => 'string|max:255',
            'city' => 'string|max:255',
            'state' => 'string|max:255',
            'country' => 'string|max:255',
            'zip' => 'string|max:255',

            'wholeasaler' => 'required|boolean',
            'notes' => 'nullable|string',
        ];
    }
}
