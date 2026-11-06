<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

use GeolizeStudio\Chile\Exceptions\InvalidTaxRate;
use GeolizeStudio\Chile\Support\IntegerMath;

final readonly class Iva
{
    private function __construct(public int $numerator, public int $denominator) {}

    public static function standard(): self
    {
        return new self(19, 100);
    }

    /** Accept integer ratio components from 0/1 through 1,000,000/1. */
    public static function fromRatio(mixed $numerator, mixed $denominator): self
    {
        if (! is_int($numerator) || ! is_int($denominator)
            || $numerator < 0 || $numerator > 1_000_000
            || $denominator < 1 || $denominator > 1_000_000) {
            throw new InvalidTaxRate('IVA requires integer ratio components: numerator 0–1000000 and denominator 1–1000000.');
        }

        $left = $numerator;
        $right = $denominator;

        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return new self(intdiv($numerator, $left), intdiv($denominator, $left));
    }

    public function fromNet(Clp $net, RoundingMode $rounding): TaxCalculation
    {
        $tax = Clp::fromPesos(IntegerMath::multiplyDivide($net->pesos(), $this->numerator, $this->denominator, $rounding));

        return new TaxCalculation($net, $tax, $net->add($tax), $this, $rounding, 'net');
    }

    public function fromGross(Clp $gross, RoundingMode $rounding): TaxCalculation
    {
        $net = Clp::fromPesos(IntegerMath::multiplyDivide($gross->pesos(), $this->denominator, $this->denominator + $this->numerator, $rounding));

        return new TaxCalculation($net, $gross->subtract($net), $gross, $this, $rounding, 'gross');
    }
}
