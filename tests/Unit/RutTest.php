<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Exceptions\InvalidRut;
use GeolizeStudio\Chile\Rut;
use GeolizeStudio\Chile\RutFormat;

dataset('valid RUT values', [
    ['1-9', '1-9', '1-9'],
    ['10-8', '10-8', '10-8'],
    ['100-7', '100-7', '100-7'],
    ['1000-6', '1000-6', '1.000-6'],
    ['1.234-3', '1234-3', '1.234-3'],
    ['123.456-0', '123456-0', '123.456-0'],
    ['1.234.567-4', '1234567-4', '1.234.567-4'],
    ['12.345.678-5', '12345678-5', '12.345.678-5'],
    ['12345678-5', '12345678-5', '12.345.678-5'],
    ['12.345.670-k', '12345670-K', '12.345.670-K'],
    ['  12345670-K  ', '12345670-K', '12.345.670-K'],
    ['99.999.999-9', '99999999-9', '99.999.999-9'],
    ['123456785', '12345678-5', '12.345.678-5'],
    ['12345670k', '12345670-K', '12.345.670-K'],
    ['12.345.6785', '12345678-5', '12.345.678-5'],
    ['12.345.670k', '12345670-K', '12.345.670-K'],
    ['19', '1-9', '1-9'],
    ['1234560', '123456-0', '123.456-0'],
]);

it('parses complete RUT values into canonical and formatted representations', function (string $input, string $canonical, string $formatted): void {
    $rut = Rut::parse($input);
    [$body, $dv] = explode('-', $canonical);

    expect($rut->toString())->toBe($canonical)
        ->and((string) $rut)->toBe($canonical)
        ->and($rut->body())->toBe($body)
        ->and($rut->dv())->toBe($dv)
        ->and($rut->format())->toBe($formatted)
        ->and(json_encode($rut, JSON_THROW_ON_ERROR))->toBe('"'.$canonical.'"')
        ->and(Rut::tryParse($input)?->toString())->toBe($canonical)
        ->and(Rut::parse($rut->toString())->toString())->toBe($canonical)
        ->and(Rut::parse($rut->format())->toString())->toBe($canonical);
})->with('valid RUT values');

dataset('malformed RUT values', [
    'empty' => [''],
    'spaces only' => ['   '],
    'missing DV' => ['12.345.678'],
    'missing body' => ['-5'],
    'trailing hyphen' => ['12345678-'],
    'multiple DV digits' => ['12345678-55'],
    'prefix' => ['abc12345678-5'],
    'suffix' => ['12345678-5abc'],
    'bad grouping' => ['1.2345.678-5'],
    'bad grouping without hyphen' => ['1.2345.6785'],
    'multiple hyphens' => ['12.345.678--5'],
    'compact leading zero' => ['0123456785'],
    'compact body too long' => ['1234567892'],
    'partial grouping' => ['12345.678-5'],
    'leading dot' => ['.12345678-5'],
    'trailing dot' => ['12345678.-5'],
    'leading zero' => ['012345678-5'],
    'grouped leading zero' => ['01.234.567-4'],
    'zero body' => ['0-0'],
    'body too long' => ['123456789-2'],
    'grouped body too long' => ['123.456.789-2'],
    'embedded space' => ['12 345 678-5'],
    'space before DV' => ['12345678- 5'],
    'tab' => ["\t12345678-5"],
    'newline' => ["12345678-5\n"],
    'nonbreaking space' => ["\u{00a0}12345678-5"],
    'Unicode digits' => ['１２３４５６７８-5'],
    'Unicode hyphen' => ['12345678–5'],
    'invalid DV letter' => ['12345678-X'],
    'NUL byte' => ["12345678-5\0"],
]);

it('rejects malformed input without stripping or repairing it', function (string $input): void {
    try {
        Rut::parse($input);
        test()->fail('Malformed input was accepted.');
    } catch (InvalidRut $exception) {
        expect($exception->reason)->toBe('invalid_format');
    }

    expect(Rut::tryParse($input))->toBeNull();
})->with('malformed RUT values');

it('rejects incorrect check digits', function (string $input): void {
    try {
        Rut::parse($input);
        test()->fail('An incorrect DV was accepted.');
    } catch (InvalidRut $exception) {
        expect($exception->reason)->toBe('invalid_checksum');
    }

    expect(Rut::tryParse($input))->toBeNull();
})->with(['12345678-4', '12345670-0', '123456-1', '1-K', '99999999-0', '123456784', '123456700', '12.345.6784']);

it('does not coerce non-string input', function (mixed $input): void {
    try {
        Rut::parse($input);
        test()->fail('A non-string value was accepted.');
    } catch (InvalidRut $exception) {
        expect($exception->reason)->toBe('invalid_type');
    }

    expect(Rut::tryParse($input))->toBeNull();
})->with([
    'null' => [null],
    'integer' => [123456785],
    'float' => [12345678.5],
    'true' => [true],
    'false' => [false],
    'array' => [['12345678-5']],
    'object' => [new stdClass],
    'Stringable' => [new class implements Stringable
    {
        public function __toString(): string
        {
            throw new LogicException('Implicit conversion must not run.');
        }
    }],
]);

it('remains immutable after construction', function (): void {
    $rut = Rut::parse('12345678-5');

    expect(fn () => $rut->body = '12345670')->toThrow(Error::class)
        ->and($rut->toString())->toBe('12345678-5');
});

it('renders each output format without changing the identifier or original preference', function (string $format, string $expected): void {
    $original = Rut::parse('12.345.670-k');
    $preferred = $original->withFormat(RutFormat::from($format));

    expect($preferred->toString())->toBe($expected)
        ->and((string) $preferred)->toBe($expected)
        ->and(json_encode($preferred, JSON_THROW_ON_ERROR))->toBe('"'.$expected.'"')
        ->and($preferred->body())->toBe('12345670')
        ->and($preferred->dv())->toBe('K')
        ->and($preferred->format())->toBe('12.345.670-K')
        ->and($original->toString())->toBe('12345670-K')
        ->and($original->format(RutFormat::from($format)))->toBe($expected)
        ->and(Rut::parse($expected)->toString())->toBe('12345670-K');
})->with([
    'canonical' => ['canonical', '12345670-K'],
    'compact' => ['compact', '12345670K'],
    'formatted' => ['formatted', '12.345.670-K'],
]);
