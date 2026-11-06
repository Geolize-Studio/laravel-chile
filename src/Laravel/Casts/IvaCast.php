<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Laravel\Casts;

use GeolizeStudio\Chile\Exceptions\InvalidTaxRate;
use GeolizeStudio\Chile\Iva;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/** @implements CastsAttributes<Iva|null, Iva|array{numerator: int, denominator: int}|null> */
final class IvaCast implements CastsAttributes, ComparesCastableAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Iva
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidTaxRate('A stored IVA rate must be a JSON object containing numerator and denominator.');
        }

        try {
            $ratio = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidTaxRate('A stored IVA rate must be valid JSON.');
        }

        return Iva::fromArray($ratio);
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode(
            $value instanceof Iva ? $value : Iva::fromArray($value),
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{numerator: int, denominator: int}|null
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value === null ? null : ($value instanceof Iva ? $value : Iva::fromArray($value))->toArray();
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        if ($firstValue === null || $secondValue === null) {
            return $firstValue === $secondValue;
        }

        try {
            return $this->get($model, $key, $firstValue, [])?->toArray()
                === $this->get($model, $key, $secondValue, [])?->toArray();
        } catch (InvalidTaxRate) {
            // An explicit replacement of invalid historical data must remain dirty.
            return false;
        }
    }
}
