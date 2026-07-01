<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'code'   => [
                'sometimes', 
                'string', 
                'max:8',
                Rule::unique('sellers', 'code')
                    ->where(function ($query) {
                        return $query->where('organization_id', auth()->user()->organization_id);
                    })
                    ->ignore($this->route('seller') instanceof \App\Models\Seller ? $this->route('seller')->id : $this->route('seller'))
            ],
            'status' => ['sometimes', 'string', 'in:active,inactive,blocked'],
            // Permitir cambiar el PIN
            'pin'    => [
                'sometimes', 
                'nullable', 
                'string', 
                'min:4', 
                'max:10',
                function ($attribute, $value, $fail) {
                    if (empty($value)) return;
                    
                    $orgId = auth()->user()->organization_id;
                    $currentSellerId = $this->route('seller') instanceof \App\Models\Seller 
                        ? $this->route('seller')->id 
                        : $this->route('seller');
                    
                    $sellers = \App\Models\Seller::where('organization_id', $orgId)
                        ->where('status', 'active')
                        ->when($currentSellerId, function ($q) use ($currentSellerId) {
                            $q->where('id', '!=', $currentSellerId);
                        })
                        ->get();
                    
                    foreach ($sellers as $s) {
                        if (\Illuminate\Support\Facades\Hash::check($value, $s->pin_hash)) {
                            $fail('El PIN de seguridad ya está asignado a otro vendedor activo.');
                            return;
                        }
                    }
                }
            ],
            // Manejar relación muchos-a-muchos con stores
            'stores'   => ['sometimes', 'array'],
            'stores.*' => ['uuid', 'exists:stores,id'],
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
