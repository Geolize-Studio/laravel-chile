<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Territory;

final readonly class Comuna
{
    public function __construct(public string $code, public string $name, public string $provinceCode, public string $regionCode) {}
}
