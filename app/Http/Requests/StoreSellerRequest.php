<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'code'            => [
                'required', 
                'string', 
                'max:8',
                Rule::unique('sellers', 'code')->where(function ($query) {
                    return $query->where('organization_id', auth()->user()->organization_id);
                })
            ],
            'pin'             => [
                'required', 
                'string', 
                'min:4', 
                'max:10',
                function ($attribute, $value, $fail) {
                    $orgId = auth()->user()->organization_id;
                    $sellers = \App\Models\Seller::where('organization_id', $orgId)
                        ->where('status', 'active')
                        ->get();
                    
                    foreach ($sellers as $s) {
                        if (\Illuminate\Support\Facades\Hash::check($value, $s->pin_hash)) {
                            $fail('El PIN de seguridad ya está asignado a otro vendedor activo.');
                            return;
                        }
                    }
                }
            ],
            // status opcional; por defecto 'active' en el controlador
            'status'          => ['sometimes', 'string', 'in:active,inactive,blocked'],

            // Relación muchos-a-muchos con stores
            'stores'          => ['required', 'array', 'min:1'],
            'stores.*'        => ['uuid', 'exists:stores,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'El código de vendedor ya está en uso.',
            'code.required' => 'El código de vendedor es requerido.',
            'pin.required' => 'El PIN de seguridad es requerido.',
            'pin.min' => 'El PIN debe tener al menos 4 caracteres.',
            'pin.max' => 'El PIN no puede tener más de 10 caracteres.',
        ];
    }
}
