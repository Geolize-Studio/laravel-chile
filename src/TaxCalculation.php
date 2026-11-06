<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

use GeolizeStudio\Chile\Exceptions\InvalidAmount;
use JsonSerializable;

final readonly class TaxCalculation implements JsonSerializable
{
    /** @internal Obtain calculations through Iva::fromNet() or Iva::fromGross(). */
    public function __construct(
        public Clp $net,
        public Clp $tax,
        public Clp $gross,
        public Iva $rate,
        public RoundingMode $rounding,
        public string $inputBasis,
    ) {
        if (! in_array($inputBasis, ['net', 'gross'], true) || $net->add($tax)->pesos() !== $gross->pesos()) {
            throw new InvalidAmount('A tax calculation requires a valid input basis and net plus tax equal to gross.');
        }
    }

    /** @return array{net: int, tax: int, gross: int, rate: array{numerator: int, denominator: int}, rounding: string, input_basis: string} */
    public function jsonSerialize(): array
    {
        return [
            'net' => $this->net->pesos(),
            'tax' => $this->tax->pesos(),
            'gross' => $this->gross->pesos(),
            'rate' => ['numerator' => $this->rate->numerator, 'denominator' => $this->rate->denominator],
            'rounding' => $this->rounding->value,
            'input_basis' => $this->inputBasis,
        ];
    }
}
