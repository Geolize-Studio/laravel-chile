<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Territory;

final readonly class Region
{
    public function __construct(public string $code, public string $name) {}
}
