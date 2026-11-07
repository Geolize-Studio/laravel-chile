<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

enum RutFormat: string
{
    case Canonical = 'canonical';
    case Compact = 'compact';
    case Formatted = 'formatted';
}
