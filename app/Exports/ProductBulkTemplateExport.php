<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\Brand;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductBulkTemplateExport implements WithMultipleSheets
{
    protected string $organizationId;

    public function __construct(string $organizationId)
    {
        $this->organizationId = $organizationId;
    }

    public function sheets(): array
    {
        return [
            new class implements FromArray, WithTitle, WithHeadings, WithStyles {
                public function title(): string
                {
                    return 'Productos';
                }

                public function headings(): array
                {
                    return [
                        'Código',
                        'Codigo nuevo',
                        'categoria',
                        'Marca',
                        'Nombre',
                        'Codigo Barra',
                        'tags',
                    ];
                }

                public function array(): array
                {
                    return [
                        [
                            'CAM-001',
                            '',
                            'Ropa > Camisas',
                            'Marca Demo',
                            'Camisa azul manga corta actualizada',
                            '750000000001',
                            'oferta, temporada',
                        ],
                    ];
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
                    foreach (range('A', 'F') as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                }
            },

            new class implements FromArray, WithTitle, WithHeadings, WithStyles {
                public function title(): string
                {
                    return 'Instrucciones';
                }

                public function headings(): array
                {
                    return [
                        'Columna',
                        'Requerido',
                        'Descripción y Reglas',
                    ];
                }

                public function array(): array
                {
                    return [
                        ['Código', 'Sí', 'Código SKU del producto existente a actualizar. Debe existir en el sistema.'],
                        ['Codigo nuevo', 'No', 'Nuevo código SKU si se desea cambiar el SKU de este producto individual. Debe ser único.'],
                        ['categoria', 'No', 'Nombre o ruta de la categoría (ej. "Ropa > Camisas"). Debe existir.'],
                        ['Marca', 'No', 'Nombre de la marca. Debe existir.'],
                        ['Nombre', 'No', 'Nuevo nombre del producto. Si se deja vacío, conserva el actual.'],
                        ['Codigo Barra', 'No', 'Nuevo código de barras. Si se deja vacío, conserva el actual.'],
                        ['tags', 'No', 'Etiquetas separadas por coma (ej. "oferta, verano").'],
                        ['---', 'IMPORTANTE', 'Esta plantilla es exclusivamente informativa. NO modifica cantidades de inventario, costos, precios, compras ni movimientos.'],
                    ];
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                    $sheet->getColumnDimension('A')->setAutoSize(true);
                    $sheet->getColumnDimension('B')->setAutoSize(true);
                    $sheet->getColumnDimension('C')->setWidth(65);
                }
            },

            new class($this->organizationId) implements FromArray, WithTitle, WithHeadings, WithStyles {
                protected string $organizationId;

                public function __construct(string $orgId)
                {
                    $this->organizationId = $orgId;
                }

                public function title(): string
                {
                    return 'Valores permitidos';
                }

                public function headings(): array
                {
                    return [
                        'Categorías Disponibles (Ruta)',
                        'Marcas Disponibles',
                    ];
                }

                public function array(): array
                {
                    $categories = Category::where('organization_id', $this->organizationId)
                        ->where('status', 'active')
                        ->get()
                        ->map(fn($c) => $c->full_path)
                        ->sort()
                        ->values();

                    $brands = Brand::where('organization_id', $this->organizationId)
                        ->where('status', 'active')
                        ->pluck('name')
                        ->sort()
                        ->values();

                    $max = max($categories->count(), $brands->count(), 1);
                    $rows = [];

                    for ($i = 0; $i < $max; $i++) {
                        $rows[] = [
                            $categories[$i] ?? '',
                            $brands[$i] ?? '',
                        ];
                    }

                    return $rows;
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('A1:B1')->getFont()->setBold(true);
                    $sheet->getColumnDimension('A')->setWidth(40);
                    $sheet->getColumnDimension('B')->setWidth(30);
                }
            },
        ];
    }
}
