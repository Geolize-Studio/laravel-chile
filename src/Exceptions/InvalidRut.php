<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Exceptions;

use InvalidArgumentException;

final class InvalidRut extends InvalidArgumentException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function invalidType(): self
    {
        return new self('invalid_type', 'A RUT must be provided as a string.');
    }

    public static function invalidFormat(): self
    {
        return new self('invalid_format', 'A RUT must contain a valid numeric body, a hyphen, and a DV.');
    }

    public static function invalidChecksum(): self
    {
        return new self('invalid_checksum', 'The RUT has an incorrect DV.');
    }
}
