<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

enum RoundingMode: string
{
    case HalfAwayFromZero = 'half_away_from_zero';
    case HalfEven = 'half_even';
    case TowardZero = 'toward_zero';
    case Floor = 'floor';
    case Ceiling = 'ceiling';
}
