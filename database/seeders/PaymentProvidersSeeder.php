<?php

namespace Database\Seeders;

use App\Models\GlobalSetting;
use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentProvidersSeeder extends Seeder
{
    public function run(): void
    {
        // Método por defecto: transferencia bancaria con validación por comprobante.
        // Toma la cuenta ya configurada en settings si existe.
        $account = GlobalSetting::where('key', 'payment_account')->value('value');

        PaymentProvider::firstOrCreate(
            ['driver' => 'transfer'],
            [
                'name' => 'Transferencia bancaria',
                'is_active' => true,
                'is_default' => true,
                'mode' => 'live',
                'supports_receipt' => true,
                'instructions' => $account
                    ? "Realiza tu pago a: {$account}\nLuego sube el comprobante para validar tu renovación."
                    : "Realiza tu transferencia y sube el comprobante para validar tu renovación.",
            ]
        );
    }
}
