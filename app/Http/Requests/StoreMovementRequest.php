<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMovementRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'inventory_detail_id' => ['required', 'uuid', 'exists:inventory_details,id'],
            'type' => ['required', 'string', 'in:return,adjustment_in,gift_in,manual_in,damage,personal_use,gift_out,adjustment_out,loss,manual_out'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
            'reference_id' => ['nullable', 'uuid'],
            'reference_type' => ['nullable', 'string'],
        ];
    }
}
