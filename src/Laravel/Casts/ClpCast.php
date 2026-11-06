<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Laravel\Casts;

use GeolizeStudio\Chile\Clp;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Clp|null, Clp|int|string|null> */
final class ClpCast implements CastsAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Clp
    {
        return $value === null ? null : Clp::parse($value);
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : Clp::parse($value)->pesos();
    }

    /** @param array<string, mixed> $attributes */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : Clp::parse($value)->pesos();
    }
}
