<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchasesTemplateExport implements WithMultipleSheets
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
                        'categoria',
                        'Marca',
                        'Nombre',
                        'Codigo Barra',
                        'Precio',
                        'Cantidad',
                        'Costo',
                        'Total',
                        'Proveedor',
                    ];
                }

                public function array(): array
                {
                    return [
                        [
                            'CAM-001',
                            'Ropa > Camisas',
                            'Marca Demo',
                            'Camisa azul manga corta',
                            '750000000001',
                            25.00,
                            10,
                            12.00,
                            120.00,
                            'Proveedor Demo',
                        ],
                    ];
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('A1:J1')->getFont()->setBold(true);
                    foreach (range('A', 'J') as $col) {
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
                        ['Código', 'Sí', 'Código SKU del producto. Debe ser único en la organización.'],
                        ['categoria', 'No', 'Nombre o ruta completa de la categoría (ej. "Ropa > Camisas"). Debe existir en el sistema.'],
                        ['Marca', 'No', 'Nombre de la marca del producto. Debe existir en el sistema.'],
                        ['Nombre', 'Sí (nuevos)', 'Nombre descriptivo del producto. Obligatorio si el SKU no está registrado aún.'],
                        ['Codigo Barra', 'No', 'Código de barras numérico o alfanumérico.'],
                        ['Precio', 'Sí', 'Precio de venta al público (numérico).'],
                        ['Cantidad', 'Sí', 'Cantidad a ingresar en la compra (número mayor a 0).'],
                        ['Costo', 'Sí', 'Costo unitario de compra (numérico mayor o igual a 0).'],
                        ['Total', 'No', 'Opcional. Si se ingresa, debe coincidir con Cantidad × Costo.'],
                        ['Proveedor', 'No', 'Nombre del proveedor asignado a la compra. Debe existir en el sistema.'],
                    ];
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                    $sheet->getColumnDimension('A')->setAutoSize(true);
                    $sheet->getColumnDimension('B')->setAutoSize(true);
                    $sheet->getColumnDimension('C')->setWidth(60);
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
                        'Proveedores Disponibles',
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

                    $suppliers = Supplier::where('organization_id', $this->organizationId)
                        ->pluck('name')
                        ->sort()
                        ->values();

                    $max = max($categories->count(), $brands->count(), $suppliers->count(), 1);
                    $rows = [];

                    for ($i = 0; $i < $max; $i++) {
                        $rows[] = [
                            $categories[$i] ?? '',
                            $brands[$i] ?? '',
                            $suppliers[$i] ?? '',
                        ];
                    }

                    return $rows;
                }

                public function styles(Worksheet $sheet)
                {
                    $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                    $sheet->getColumnDimension('A')->setWidth(40);
                    $sheet->getColumnDimension('B')->setWidth(30);
                    $sheet->getColumnDimension('C')->setWidth(30);
                }
            },
        ];
    }
}
