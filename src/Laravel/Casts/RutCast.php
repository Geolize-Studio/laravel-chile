<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Laravel\Casts;

use GeolizeStudio\Chile\Rut;
use GeolizeStudio\Chile\RutFormat;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Rut|null, Rut|string|null>
 */
final class RutCast implements CastsAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    private readonly RutFormat $format;

    public function __construct(?string $format = null)
    {
        $configured = $format ?? RutFormat::Canonical->value;
        $container = Container::getInstance();

        if ($format === null && $container->bound('config')) {
            /** @var Repository $config */
            $config = $container->make('config');
            $configured = $config->get('chile.rut.format', RutFormat::Canonical->value);
        }

        $resolved = is_string($configured) ? RutFormat::tryFrom($configured) : null;

        if ($resolved === null) {
            throw new InvalidArgumentException('The RUT format must be canonical, compact, or formatted.');
        }

        $this->format = $resolved;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Rut
    {
        return $value === null ? null : Rut::parse($value)->withFormat($this->format);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return ($value instanceof Rut ? $value : Rut::parse($value))->format($this->format);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return ($value instanceof Rut ? $value : Rut::parse($value))->format($this->format);
    }
}
