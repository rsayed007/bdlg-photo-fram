<?php

namespace App\Support;

/**
 * Mirrors resources/js/lib/format.ts so server- and client-rendered frames
 * print identical numbers.
 */
class NumberFormat
{
    public const UNKNOWN = '—';

    /** 1.2K / 3.4M / 1.1B — one decimal, truncated (999,950 → 999.9K, never 1000K). */
    public static function compact(int|float|null $value): string
    {
        if ($value === null) {
            return self::UNKNOWN;
        }

        $abs = abs($value);
        foreach ([[1e9, 'B'], [1e6, 'M'], [1e3, 'K']] as [$size, $suffix]) {
            if ($abs >= $size) {
                $scaled = floor(($value / $size) * 10) / 10;

                return rtrim(rtrim(number_format($scaled, 1, '.', ''), '0'), '.').$suffix;
            }
        }

        return (string) $value;
    }

    public static function number(int|float|null $value): string
    {
        return $value === null ? self::UNKNOWN : number_format($value);
    }
}
