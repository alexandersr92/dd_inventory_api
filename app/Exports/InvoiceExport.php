<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoiceExport implements FromCollection, WithMapping
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return $this->data;
    }

    /**
     * SEGURIDAD (CSV/formula injection): neutralizar los valores de texto
     * (client_name, etc.) para que Excel no ejecute fórmulas al abrir el archivo.
     */
    public function map($invoice): array
    {
        $row = is_array($invoice) ? $invoice : $invoice->toArray();
        return array_map(function ($v) {
            return is_string($v) ? InventoryExport::csvSafe($v) : $v;
        }, $row);
    }
}
