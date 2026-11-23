<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

class ExcelProductParser
{
    public static function normalizeHeader(string $header): string
    {
        $clean = mb_strtolower(trim($header));
        $clean = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n'],
            $clean
        );
        $clean = preg_replace('/[\s_-]+/', ' ', $clean);
        return trim($clean);
    }

    public static function mapHeaderToKey(string $header): ?string
    {
        $norm = self::normalizeHeader($header);

        if (in_array($norm, ['codigo', 'sku', 'cod', 'item code', 'codigo producto'])) {
            return 'sku';
        }
        if (in_array($norm, ['codigo nuevo', 'nuevo codigo', 'sku nuevo', 'nuevo sku', 'new sku', 'codigo_nuevo'])) {
            return 'new_sku';
        }
        if (in_array($norm, ['categoria', 'category', 'cat', 'rubro'])) {
            return 'category';
        }
        if (in_array($norm, ['marca', 'brand'])) {
            return 'brand';
        }
        if (in_array($norm, ['nombre', 'name', 'producto', 'product', 'descripcion', 'nombre producto', 'product name'])) {
            return 'name';
        }
        if (in_array($norm, ['codigo barra', 'codigo barras', 'codigobarra', 'barcode', 'codigo de barra', 'codigo de barras', 'bar code'])) {
            return 'barcode';
        }
        if (in_array($norm, ['precio', 'price', 'precio venta', 'precio de venta', 'pvp'])) {
            return 'price';
        }
        if (in_array($norm, ['cantidad', 'quantity', 'cant', 'qty'])) {
            return 'quantity';
        }
        if (in_array($norm, ['costo', 'cost', 'costo compra', 'costo unitario', 'costo de compra'])) {
            return 'cost';
        }
        if (in_array($norm, ['total', 'monto total', 'total calculado'])) {
            return 'total';
        }
        if (in_array($norm, ['proveedor', 'supplier', 'suplidor', 'nombre proveedor'])) {
            return 'supplier';
        }
        if (in_array($norm, ['tags', 'etiquetas', 'tag', 'etiqueta'])) {
            return 'tags';
        }

        return null;
    }

    public static function cleanNumeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);
        // Remove currency symbols, commas and spaces
        $str = str_replace(['C$', '$', ' ', ','], ['', '', '', '.'], $str);

        // If there are multiple dots, keep only the last one as decimal separator
        if (substr_count($str, '.') > 1) {
            $parts = explode('.', $str);
            $last = array_pop($parts);
            $str = implode('', $parts) . '.' . $last;
        }

        return is_numeric($str) ? (float) $str : null;
    }

    /**
     * Reads an uploaded excel file into an array of rows mapped by canonical header keys.
     * Returns:
     * [
     *   'headers_map' => [0 => 'sku', 1 => 'category', ...],
     *   'rows' => [
     *      [
     *         'row_index' => 2,
     *         'raw' => [...],
     *         'data' => ['sku' => ..., 'category' => ..., ...]
     *      ]
     *   ]
     * ]
     */
    public static function parseRows($file): array
    {
        $rawSheets = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
            public function array(array $array)
            {
                return $array;
            }
        }, $file);

        if (empty($rawSheets) || empty($rawSheets[0])) {
            return [
                'headers_map' => [],
                'rows' => [],
            ];
        }

        $sheetData = $rawSheets[0]; // Primary sheet
        $headerRow = array_shift($sheetData);

        if (!$headerRow) {
            return [
                'headers_map' => [],
                'rows' => [],
            ];
        }

        $headersMap = [];
        foreach ($headerRow as $colIdx => $colHeader) {
            if ($colHeader === null || trim((string)$colHeader) === '') {
                continue;
            }
            $mappedKey = self::mapHeaderToKey((string)$colHeader);
            if ($mappedKey) {
                $headersMap[$colIdx] = $mappedKey;
            }
        }

        $parsedRows = [];
        foreach ($sheetData as $offset => $rowValues) {
            $rowIndex = $offset + 2; // +1 for 0-index, +1 for header row

            // Check if entirely empty row
            $hasAnyValue = false;
            $rowData = [];

            foreach ($headersMap as $colIdx => $key) {
                $val = isset($rowValues[$colIdx]) ? trim((string)$rowValues[$colIdx]) : '';
                if ($val !== '') {
                    $hasAnyValue = true;
                }
                $rowData[$key] = $val;
            }

            if (!$hasAnyValue) {
                continue;
            }

            $parsedRows[] = [
                'row_index' => $rowIndex,
                'raw' => $rowValues,
                'data' => $rowData,
            ];
        }

        return [
            'headers_map' => $headersMap,
            'rows' => $parsedRows,
        ];
    }
}
