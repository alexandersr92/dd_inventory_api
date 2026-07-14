<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\Store;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Exception;

abstract class BaseReportStrategy
{
    /**
     * El nombre legible del reporte.
     */
    abstract protected function getReportName(): string;

    /**
     * El tipo del reporte (ej: invoices, inventory).
     */
    abstract protected function getReportType(): string;

    /**
     * Retorna el nombre de la vista blade a usar.
     */
    abstract protected function getViewName(): string;

    /**
     * Obtiene los datos reales desde la base de datos basados en los filtros.
     */
    abstract protected function fetchData(string $organizationId, array $filters): array;

    /**
     * Método principal para ejecutar el proceso completo.
     */
    public function generate(string $organizationId, string $userId, array $filters): Report
    {
        // 1. Crear el registro en la base de datos
        $report = Report::create([
            'organization_id' => $organizationId,
            'store_id' => $filters['store_id'] ?? null,
            'user_id' => $userId,
            'name' => 'Reporte - ' . $this->getReportName() . ' - ' . date('Y-m-d H:i:s'),
            'type' => $this->getReportType(),
            'filters' => $filters,
            'status' => 'processing',
        ]);

        $report->load('user');

        try {
            // 2. Obtener los datos específicos del reporte
            $items = $this->fetchData($organizationId, $filters);

            // Obtener moneda de la tienda
            $currency = '$';
            if (!empty($filters['store_id'])) {
                $store = Store::find($filters['store_id']);
                if ($store && $store->store_currency) {
                    $currency = $store->store_currency;
                }
            }

            $data = [
                'report' => $report,
                'filters' => $filters,
                'items' => $items,
                'currency' => $currency,
            ];
            
            // 3. Generar el PDF
            $pdf = Pdf::loadView($this->getViewName(), $data);
            
            // 4. Guardar en storage
            $fileName = 'report_' . $report->id . '_' . time() . '.pdf';
            $filePath = 'reports/' . $fileName;
            
            Storage::disk('public')->put($filePath, $pdf->output());

            // 5. Actualizar el registro como completado
            $report->update([
                'status' => 'completed',
                'file_path' => $filePath
            ]);

            return $report;

        } catch (Exception $e) {
            // Si algo falla, marcar el reporte como fallido
            $report->update(['status' => 'failed']);
            throw $e;
        }
    }
}
