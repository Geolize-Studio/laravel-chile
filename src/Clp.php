<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

use GeolizeStudio\Chile\Exceptions\AmountOverflow;
use GeolizeStudio\Chile\Exceptions\InvalidAmount;
use GeolizeStudio\Chile\Support\IntegerMath;
use JsonSerializable;
use LogicException;
use Stringable;

final readonly class Clp implements JsonSerializable, Stringable
{
    private function __construct(private int $amount)
    {
        if (PHP_INT_SIZE !== 8) {
            throw new LogicException('CLP requires a 64-bit PHP runtime.');
        }
    }

    /** Accept an integer without PHP weak-call-site scalar coercion. */
    public static function fromPesos(mixed $pesos): self
    {
        if (! is_int($pesos)) {
            throw new InvalidAmount('The amount must be an integer number of pesos.');
        }

        return new self($pesos);
    }

    /** Accept a Clp value, an integer, or a complete documented CLP string. */
    public static function parse(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_int($value)) {
            return self::fromPesos($value);
        }

        if (! is_string($value)) {
            throw new InvalidAmount('The amount must be an integer or a valid CLP string.');
        }

        $value = trim($value, " \t\n\r\v\f");

        if (preg_match('/\A(?<sign>[+-]?)(?:\$[ ]*)?(?<digits>0|[1-9][0-9]*|[1-9][0-9]{0,2}(?:\.[0-9]{3})+)\z/', $value, $matches) !== 1) {
            throw new InvalidAmount('The amount must contain whole pesos with valid Chilean grouping.');
        }

        $digits = str_replace('.', '', $matches['digits']);
        $negative = $matches['sign'] === '-';
        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new AmountOverflow('The amount exceeds the supported integer range.');
        }

        return new self((int) (($negative ? '-' : '').$digits));
    }

    public static function tryParse(mixed $value): ?self
    {
        try {
            return self::parse($value);
        } catch (InvalidAmount|AmountOverflow) {
            return null;
        }
    }

    public function pesos(): int
    {
        return $this->amount;
    }

    public function add(self $other): self
    {
        return new self(IntegerMath::add($this->amount, $other->amount));
    }

    public function subtract(self $other): self
    {
        return new self(IntegerMath::subtract($this->amount, $other->amount));
    }

    public function format(): string
    {
        $digits = $this->amount < 0 ? substr((string) $this->amount, 1) : (string) $this->amount;
        $grouped = strrev(implode('.', str_split(strrev($digits), 3)));

        return ($this->amount < 0 ? '-' : '').'$'.$grouped;
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }

    public function jsonSerialize(): int
    {
        return $this->amount;
    }
}
