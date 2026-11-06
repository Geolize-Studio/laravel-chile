<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Territory;

enum ResolutionStatus: string
{
    case Matched = 'matched';
    case Ambiguous = 'ambiguous';
    case NotFound = 'not_found';
}
