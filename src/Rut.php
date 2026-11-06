<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

use GeolizeStudio\Chile\Exceptions\InvalidRut;
use GeolizeStudio\Chile\Laravel\Casts\RutCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use JsonSerializable;
use Stringable;

final readonly class Rut implements Castable, JsonSerializable, Stringable
{
    private function __construct(private string $body, private string $dv) {}

    /**
     * @param  array<string>  $arguments
     * @return class-string<RutCast>
     */
    public static function castUsing(array $arguments): string
    {
        return RutCast::class;
    }

    public static function parse(mixed $value): self
    {
        if (! is_string($value)) {
            throw InvalidRut::invalidType();
        }

        $value = trim($value, ' ');

        if (preg_match('/\A([1-9][0-9]{0,7}|[1-9][0-9]{0,2}(?:\.[0-9]{3}){1,2})-([0-9kK])\z/', $value, $matches) !== 1) {
            throw InvalidRut::invalidFormat();
        }

        $body = str_replace('.', '', $matches[1]);

        if (strlen($body) > 8) {
            throw InvalidRut::invalidFormat();
        }

        $dv = strtoupper($matches[2]);

        if (self::checkDigit($body) !== $dv) {
            throw InvalidRut::invalidChecksum();
        }

        return new self($body, $dv);
    }

    public static function tryParse(mixed $value): ?self
    {
        try {
            return self::parse($value);
        } catch (InvalidRut) {
            return null;
        }
    }

    public function body(): string
    {
        return $this->body;
    }

    public function dv(): string
    {
        return $this->dv;
    }

    public function toString(): string
    {
        return $this->body.'-'.$this->dv;
    }

    public function format(): string
    {
        return strrev(implode('.', str_split(strrev($this->body), 3))).'-'.$this->dv;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private static function checkDigit(string $body): string
    {
        $sum = 0;
        $weight = 2;

        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += (ord($body[$index]) - ord('0')) * $weight;
            $weight = $weight === 7 ? 2 : $weight + 1;
        }

        return match (11 - ($sum % 11)) {
            11 => '0',
            10 => 'K',
            default => (string) (11 - ($sum % 11)),
        };
    }
}
