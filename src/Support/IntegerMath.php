<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Support;

use GeolizeStudio\Chile\Exceptions\AmountOverflow;
use GeolizeStudio\Chile\RoundingMode;
use InvalidArgumentException;

/** @internal */
final class IntegerMath
{
    public static function add(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new AmountOverflow('The amount exceeds the supported integer range.');
        }

        return $left + $right;
    }

    public static function subtract(int $left, int $right): int
    {
        if (($right > 0 && $left < PHP_INT_MIN + $right)
            || ($right < 0 && $left > PHP_INT_MAX + $right)) {
            throw new AmountOverflow('The amount exceeds the supported integer range.');
        }

        return $left - $right;
    }

    /** Round value × numerator / denominator without converting to float. */
    public static function multiplyDivide(int $value, int $numerator, int $denominator, RoundingMode $rounding): int
    {
        if ($numerator < 0 || $denominator < 1) {
            throw new InvalidArgumentException('An exact ratio requires a nonnegative numerator and positive denominator.');
        }

        // The bounded IVA rates keep the remainder product small, while splitting
        // the quotient allows even PHP_INT_MIN/MAX gross amounts to be calculated.
        $whole = self::multiply(intdiv($value, $denominator), $numerator);
        $fraction = self::multiply($value % $denominator, $numerator);
        $truncated = self::add($whole, intdiv($fraction, $denominator));
        $remainder = $fraction % $denominator;

        if ($remainder === 0) {
            return $truncated;
        }

        $direction = $remainder < 0 ? -1 : 1;
        $magnitude = abs($remainder);
        $halfComparison = $magnitude <=> ($denominator - $magnitude);

        $adjustment = match ($rounding) {
            RoundingMode::TowardZero => 0,
            RoundingMode::Floor => $remainder < 0 ? -1 : 0,
            RoundingMode::Ceiling => $remainder > 0 ? 1 : 0,
            RoundingMode::HalfAwayFromZero => $halfComparison >= 0 ? $direction : 0,
            RoundingMode::HalfEven => ($halfComparison > 0 || ($halfComparison === 0 && $truncated % 2 !== 0)) ? $direction : 0,
        };

        return self::add($truncated, $adjustment);
    }

    private static function multiply(int $value, int $factor): int
    {
        if ($factor === 0) {
            return 0;
        }

        if (($value > 0 && $value > intdiv(PHP_INT_MAX, $factor))
            || ($value < 0 && $value < intdiv(PHP_INT_MIN, $factor))) {
            throw new AmountOverflow('The amount exceeds the supported integer range.');
        }

        return $value * $factor;
    }
}
