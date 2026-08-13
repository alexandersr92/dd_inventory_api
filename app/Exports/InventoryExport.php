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
                self::csvSafe($product['product_name'] ?? ''),
                (string)$product['quantity'] ?? '0',
                self::csvSafe($product['status'] ?? ''),
                $product['price'] ?? '0',
                $product['cost'] ?? '0',
                self::csvSafe((string) ($product['barcode'] ?? '')),
                self::csvSafe((string) ($product['sku'] ?? '')),
                self::csvSafe($product['tags'] ?? ''),
                self::csvSafe($product['category'] ?? ''),
            ];
        });
    }

    /**
     * SEGURIDAD (CSV/formula injection): un valor que empieza con = + - @ (o tab/CR)
     * es interpretado como fórmula por Excel/LibreOffice al abrir el archivo. Se le
     * antepone un apóstrofo para forzar que se trate como texto.
     */
    public static function csvSafe($value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
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