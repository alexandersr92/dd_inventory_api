<?php

namespace Database\Seeders;

use App\Models\Credit;
use App\Models\CreditDetail;
use App\Models\Invoice;
use App\Models\Seller;
use Illuminate\Database\Seeder;

class CreditSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener facturas con estado 'credit' que no tengan ya un crédito asociado
        $invoices = Invoice::where('invoice_status', 'credit')->get();

        if ($invoices->isEmpty()) {
            $this->command->info('No se encontraron facturas con estado "credit".');
            return;
        }

        foreach ($invoices as $invoice) {
            // Crear el registro de crédito
            $credit = Credit::factory()->create([
                'user_id' => $invoice->user_id,
                'organization_id' => $invoice->organization_id,
                'store_id' => $invoice->store_id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'total' => $invoice->grand_total,
                'debt' => $invoice->grand_total, // Iniciamos la deuda con el total
                'credit_status' => 'active',
            ]);

            // Crear algunos abonos (detalles de crédito)
            $numPayments = rand(1, 3);
            $totalPaid = 0;

            for ($i = 0; $i < $numPayments; $i++) {
                $paymentAmount = rand(50, 200);
                
                // Asegurarse de no pagar más del total
                if ($totalPaid + $paymentAmount > $credit->total) {
                    $paymentAmount = $credit->total - $totalPaid;
                }

                if ($paymentAmount <= 0) break;

                CreditDetail::factory()->create([
                    'credit_id' => $credit->id,
                    'amount' => $paymentAmount,
                    'seller_id' => Seller::where('organization_id', $credit->organization_id)->inRandomOrder()->first()?->id ?? Seller::factory(),
                ]);

                $totalPaid += $paymentAmount;
            }

            // Actualizar la deuda actual en el crédito
            $credit->update([
                'debt' => $credit->total - $totalPaid,
                'credit_status' => ($totalPaid >= $credit->total) ? 'paid' : 'active',
            ]);
        }

        $this->command->info('Créditos y detalles generados exitosamente.');
    }
}
