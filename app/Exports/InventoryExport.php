<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
class InventoryExport implements FromCollection, WithHeadings, WithColumnFormatting
{
    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function collection()
    {
        return collect($this->products)->map(function ($product) {
            return [
                $product['product_name'] ?? '',
                (string)$product['quantity'] ?? '0',
                $product['status'] ?? '',
                $product['price'] ?? '0',
                $product['cost'] ?? '0',
                (string) $product['barcode'] ?? '',
                (string)$product['sku'] ?? '',
                $product['tags'] ?? '',
                $product['category'] ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Nombre de Producto',
            'Cantidad',
            'Estado',
            'Precio',
            'Costo',
            'Código de Barras',
            'SKU',
            'Etiquetas',
            'Categoría',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_00, // Costo con dos decimales (opcional)
            'E' => NumberFormat::FORMAT_NUMBER_00, // Costo con dos decimales (opcional)
            'F' => NumberFormat::FORMAT_TEXT, // Código de Barras (ahora está en G)
            'G' => NumberFormat::FORMAT_TEXT, // SKU (opcional, está en H)
        ];
    }
}