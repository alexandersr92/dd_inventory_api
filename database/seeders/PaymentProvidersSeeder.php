<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentProvidersSeeder extends Seeder
{
    public function run(): void
    {
        // Método por defecto: transferencia bancaria con validación por comprobante.
        // El admin edita las instrucciones reales en Pagos → Métodos de pago.
        PaymentProvider::firstOrCreate(
            ['driver' => 'transfer'],
            [
                'name' => 'Transferencia bancaria',
                'is_active' => true,
                'is_default' => true,
                'mode' => 'live',
                'supports_receipt' => true,
                'instructions' => 'Realiza tu transferencia y sube el comprobante para validar tu renovación.',
            ]
        );
    }
}
