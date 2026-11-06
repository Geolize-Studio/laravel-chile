<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Exceptions\InvalidTaxRate;
use GeolizeStudio\Chile\Iva;

it('round trips exact reduced IVA rates through arrays and JSON', function (int $numerator, int $denominator, array $expected): void {
    $rate = Iva::fromArray(['denominator' => $denominator, 'numerator' => $numerator]);

    expect($rate->toArray())->toBe($expected)
        ->and(json_decode(json_encode($rate, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))->toBe($expected)
        ->and(Iva::fromArray($rate->toArray()))->toEqual($rate);
})->with([
    [1900, 10000, ['numerator' => 19, 'denominator' => 100]],
    [0, 1000000, ['numerator' => 0, 'denominator' => 1]],
    [1000000, 1, ['numerator' => 1000000, 'denominator' => 1]],
    [1, 1000000, ['numerator' => 1, 'denominator' => 1000000]],
]);

it('rejects invalid serialized IVA rates without coercion or dropping fields', function (mixed $value): void {
    expect(fn () => Iva::fromArray($value))->toThrow(InvalidTaxRate::class);
})->with([
    [null], [19], [0.19], [true], ['19%'], ['{"numerator":19,"denominator":100}'],
    [new stdClass], [[]], [[19, 100]], [['numerator' => 19]], [['denominator' => 100]],
    [['numerator' => 19, 'denominator' => 100, 'rounding' => 'half_even']],
    [['numerator' => 19.0, 'denominator' => 100]],
    [['numerator' => 19, 'denominator' => 100.0]],
    [['numerator' => '19', 'denominator' => 100]],
    [['numerator' => true, 'denominator' => 100]],
    [['numerator' => -19, 'denominator' => 100]],
    [['numerator' => 19, 'denominator' => -100]],
    [['numerator' => 19, 'denominator' => 0]],
    [['numerator' => 1000001, 'denominator' => 100]],
    [['numerator' => 19, 'denominator' => 1000001]],
    [['numerator' => null, 'denominator' => 100]],
]);

it('preserves strict rate validation when deserializing from weak callers', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'chile-iva-');
    file_put_contents($path, <<<'CODE'
<?php
return array_map(function ($value) {
    try { \GeolizeStudio\Chile\Iva::fromArray($value); return null; }
    catch (Throwable $exception) { return $exception::class; }
}, [
    ['numerator' => 19.0, 'denominator' => 100],
    ['numerator' => '19', 'denominator' => '100'],
    ['numerator' => true, 'denominator' => 100],
]);
CODE);

    try {
        expect(include $path)->toBe(array_fill(0, 3, InvalidTaxRate::class));
    } finally {
        unlink($path);
    }
});
