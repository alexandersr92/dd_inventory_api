<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class MaterialUnits
{
    private const UNITS = [
        'unit' => ['count', 1], 'pair' => ['count', 2], 'dozen' => ['count', 12],
        'sheet' => ['sheet', 1], 'roll' => ['roll', 1],
        'cm' => ['length', 1], 'mm' => ['length', 0.1], 'm' => ['length', 100],
        'in' => ['length', 2.54], 'ft' => ['length', 30.48],
        'cm2' => ['area', 1], 'm2' => ['area', 10000],
        'g' => ['mass', 1], 'kg' => ['mass', 1000], 'lb' => ['mass', 453.59237], 'oz' => ['mass', 28.349523125],
        'ml' => ['volume', 1], 'l' => ['volume', 1000], 'gal' => ['volume', 3785.411784], 'fl_oz' => ['volume', 29.5735295625],
    ];

    private const PACKS = ['pack', 'box', 'lot', 'bucket', 'barrel'];

    public static function canonical(?string $unit): string
    {
        $aliases = [
            'unit' => ['unidad', 'u'], 'sheet' => ['hoja'], 'roll' => ['rollo'],
            'kg' => ['kilogramo', 'kilogram'], 'g' => ['gramo', 'gram'], 'lb' => ['libra', 'pound'], 'oz' => ['onza', 'ounce'],
            'l' => ['litro', 'liter', 'litre'], 'ml' => ['mililitro', 'milliliter'], 'gal' => ['galon', 'gallon'], 'fl_oz' => ['onza_liquida', 'fluid_ounce'],
            'm' => ['metro', 'meter', 'metre'], 'cm' => ['centimetro', 'centimeter'], 'mm' => ['milimetro', 'millimeter'], 'in' => ['pulgada', 'inch'], 'ft' => ['pie', 'foot'],
            'pack' => ['paquete', 'package'], 'pair' => ['par'], 'dozen' => ['docena'], 'lot' => ['lote', 'batch'],
            'cm2' => ['centimetro_cuadrado', 'sqcm', 'square_centimeter'], 'm2' => ['metro_cuadrado', 'sqm', 'square_meter'],
            'box' => ['caja'], 'bucket' => ['cubeta'], 'barrel' => ['barril'],
        ];
        foreach ($aliases as $canonical => $values) {
            if (in_array($unit, $values, true)) {
                return $canonical;
            }
        }

        return $unit ?: 'unit';
    }

    public static function validate(?array $profile, string $baseUnit): ?array
    {
        if ($profile === null) {
            return null;
        }
        foreach (['base_unit', 'purchase_unit', 'consumption_unit'] as $field) {
            if (! isset($profile[$field]) || ! is_string($profile[$field]) || trim($profile[$field]) === '') {
                self::fail('Completa las unidades con valores válidos.');
            }
        }
        $base = self::canonical($profile['base_unit']);
        if ($base !== self::canonical($baseUnit)) {
            self::fail('La unidad base debe coincidir con la unidad de inventario del producto.');
        }
        $result = ['base_unit' => $base];
        foreach (['width_cm', 'height_cm'] as $dimension) {
            if (isset($profile[$dimension])) {
                $result[$dimension] = self::positive($profile[$dimension]);
            }
        }
        foreach (['purchase', 'consumption'] as $kind) {
            if (empty($profile[$kind.'_unit']) || ! isset($profile[$kind.'_to_base_factor'])) {
                self::fail('Completa las unidades de compra/consumo y sus factores.');
            }
            $unit = self::canonical($profile[$kind.'_unit']);
            $factor = self::positive($profile[$kind.'_to_base_factor']);
            if ($unit === $base) {
                $expected = 1;
            } elseif (in_array($unit, ['sheet', 'roll'], true) && in_array($base, ['cm2', 'm2'], true)) {
                if (! isset($result['width_cm'], $result['height_cm'])) {
                    self::fail('Para convertir hoja o rollo a área, registra ancho y alto en centímetros.');
                }
                $expected = $result['width_cm'] * $result['height_cm'] / self::UNITS[$base][1];
            } elseif (isset(self::UNITS[$unit], self::UNITS[$base]) && self::UNITS[$unit][0] === self::UNITS[$base][0]) {
                $expected = self::UNITS[$unit][1] / self::UNITS[$base][1];
            } elseif (in_array($unit, self::PACKS, true)) {
                $expected = $factor; // Explicit calibrated contents of a package, in the base unit.
            } else {
                self::fail('Las unidades seleccionadas son incompatibles.');
            }
            if (abs($factor - $expected) > max(0.00000001, abs($expected) * 0.000000001)) {
                self::fail('El factor no coincide con las unidades o con el área registrada.');
            }
            $result[$kind.'_unit'] = $unit;
            $result[$kind.'_to_base_factor'] = round($expected, 8);
            if ($result[$kind.'_to_base_factor'] <= 0) {
                self::fail('El factor es demasiado pequeño para la precisión admitida.');
            }
        }

        return $result;
    }

    public static function stockQuantity(float $quantity, ?array $profile): float
    {
        $value = round($quantity * ($profile['purchase_to_base_factor'] ?? 1), 6);
        if (! is_finite($value) || $value <= 0 || $value >= 100000000000000) {
            self::fail('La cantidad convertida está fuera de la precisión admitida.');
        }

        return $value;
    }

    private static function positive(mixed $value): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0 || (float) $value >= 1000000000000) {
            self::fail('Factores y dimensiones deben ser números positivos válidos.');
        }

        return (float) $value;
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['material_profile' => $message]);
    }
}
