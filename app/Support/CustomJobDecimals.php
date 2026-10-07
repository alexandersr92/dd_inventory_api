<?php

namespace App\Support;

/** JSON numbers may arrive as 1e-8; expand without rounding before decimal validation. */
final class CustomJobDecimals
{
    public static function numbers(array $input): array
    {
        $fields = ['minute_rate', 'purchase_value', 'residual_value', 'useful_minutes', 'maintenance_per_minute', 'energy_per_minute', 'amount', 'preparation_cost', 'ink_rate', 'rate_quantity', 'machine_minutes', 'ink_quantity', 'count', 'minutes'];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = self::numbers($value);
            } elseif (in_array($key, $fields, true) && is_numeric($value)) {
                $input[$key] = $key === 'count' ? (int) $value : (float) $value;
            }
        }

        return $input;
    }

    public static function expand(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = self::expand($value);
            } elseif (is_float($value) && preg_match('/^(-?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', (string) $value, $m)) {
                $digits = $m[2].($m[3] ?? '');
                $position = strlen($m[2]) + (int) $m[4];
                $input[$key] = $m[1].($position <= 0 ? '0.'.str_repeat('0', -$position).$digits
                    : ($position >= strlen($digits) ? $digits.str_repeat('0', $position - strlen($digits))
                        : substr($digits, 0, $position).'.'.substr($digits, $position)));
                if (str_contains($input[$key], '.')) {
                    $input[$key] = rtrim(rtrim($input[$key], '0'), '.');
                }
            }
        }

        return $input;
    }
}
