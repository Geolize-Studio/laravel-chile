<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Territory;

enum MatchMethod: string
{
    case Code = 'code';
    case Exact = 'exact';
    case Normalized = 'normalized';
    case Alias = 'alias';
}
