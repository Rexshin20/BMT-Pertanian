<?php

namespace App\Support;

use InvalidArgumentException;

class FinancialAmount
{
    public static function quantityInThousandths(string $quantity): int
    {
        return self::toMinorUnits($quantity, 3);
    }

    public static function quantityFromThousandths(int $quantity): string
    {
        $whole = intdiv($quantity, 1000);
        $fraction = str_pad((string) ($quantity % 1000), 3, '0', STR_PAD_LEFT);

        return $whole.'.'.$fraction;
    }

    public static function multiply(string $quantity, string $unitPrice): string
    {
        $quantityInThousandths = self::quantityInThousandths($quantity);
        $priceInCents = self::toMinorUnits($unitPrice, 2);
        $totalInCents = intdiv(($quantityInThousandths * $priceInCents) + 500, 1000);

        return self::fromMinorUnits($totalInCents);
    }

    public static function profit(string $quantity, string $unitPrice, string $purchasePrice): string
    {
        $differenceInCents = self::toMinorUnits($unitPrice, 2) - self::toMinorUnits($purchasePrice, 2);
        $differenceInScaledCents = self::quantityInThousandths($quantity) * $differenceInCents;
        $profitInCents = intdiv(abs($differenceInScaledCents) + 500, 1000);

        return self::fromMinorUnits($differenceInScaledCents < 0 ? -$profitInCents : $profitInCents);
    }

    private static function toMinorUnits(string $value, int $decimalPlaces): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Nilai desimal tidak valid.');
        }

        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > $decimalPlaces) {
            throw new InvalidArgumentException('Jumlah angka desimal melampaui batas.');
        }

        return ((int) $matches[1] * (10 ** $decimalPlaces))
            + (int) str_pad($fraction, $decimalPlaces, '0');
    }

    private static function fromMinorUnits(int $value): string
    {
        $whole = intdiv(abs($value), 100);
        $fraction = str_pad((string) (abs($value) % 100), 2, '0', STR_PAD_LEFT);

        return ($value < 0 ? '-' : '').$whole.'.'.$fraction;
    }
}
