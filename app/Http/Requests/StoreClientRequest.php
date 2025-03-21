<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
            'email' => 'email|max:255 | nullable',
            'phone' => 'string|max:255 | nullable',
            'address' => 'string|max:255 | nullable',
            'city' => 'string|max:255 | nullable',
            'state' => 'string|max:255 | nullable',
            'country' => 'string|max:255 | nullable',
            'zip' => 'string|max:255 | nullable',
            'status' => 'string|max:255 | nullable',
            'wholesaler' => 'required|boolean',
            'notes' => 'nullable|string',
        ];
    }
}
