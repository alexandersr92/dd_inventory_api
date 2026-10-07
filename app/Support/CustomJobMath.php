<?php

namespace App\Support;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

/** Exact rational arithmetic; rounding only at documented stock/output boundaries. */
final class CustomJobMath
{
    public const POLICY = 'custom-jobs-v1:stock6-half-up:cost8:line2:order2-half-up';

    public static function of(mixed $value): BigRational
    {
        return BigRational::of((string) $value);
    }

    public static function number(BigRational $value, int $scale = 8): float
    {
        return (float) (string) $value->toScale($scale, RoundingMode::HalfUp);
    }
}
