<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paymentsHistory = $this->creditDetails->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'date' => $payment->date,
                'note' => $payment->note,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ];
        });

        return [
            'id' => $this->id,
            'amount' => $this->total,
            'current_debt' => $this->debt,
            'client' => $this->client->name,
            'status' => $this->credit_status,
            'invoice' => $this->invoice,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'paymentsHistory' => $paymentsHistory,
          
        ];
    }
}
