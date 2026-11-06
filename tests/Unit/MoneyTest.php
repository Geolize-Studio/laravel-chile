<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Exceptions\AmountOverflow;
use GeolizeStudio\Chile\Exceptions\InvalidAmount;
use GeolizeStudio\Chile\Exceptions\InvalidTaxRate;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\RoundingMode;
use GeolizeStudio\Chile\TaxCalculation;

it('parses complete whole-peso inputs', function (mixed $input, int $pesos) {
    expect(Clp::parse($input)->pesos())->toBe($pesos);
})->with([
    [0, 0], [1234, 1234], [-1234, -1234], ['0', 0], ['-0', 0], ['+0', 0],
    ['1234', 1234], ['1.234', 1234], ['$1.234', 1234], ['$ 1.234', 1234],
    ['-$1.234', -1234], ['+1234', 1234], [" \t-1.234\r\n", -1234],
    [(string) PHP_INT_MAX, PHP_INT_MAX], [(string) PHP_INT_MIN, PHP_INT_MIN],
    ['$9.223.372.036.854.775.807', PHP_INT_MAX], ['-$9.223.372.036.854.775.808', PHP_INT_MIN],
]);

it('rejects malformed inputs without returning a substitute value', function (mixed $input) {
    expect(fn () => Clp::parse($input))->toThrow(InvalidAmount::class)
        ->and(Clp::tryParse($input))->toBeNull();
})->with([
    [null], [true], [false], [1234.0], [INF], [NAN], [[]], [new stdClass],
    [''], [' '], ['abc'], ['1-234'], ['1.234,56'], ['1,234'], ['1.23'], ['1.2345'],
    ['1234.567'], ['01'], ['00'], ['0.000'], ['01.234'], ['1e3'], ['0x10'],
    ['$-1234'], ['- 1234'], ["$\t1234"], ['1 234'], ["1234\0"], ["\u{00A0}1234"],
]);

it('rejects out-of-range amounts before conversion', function (string $input) {
    expect(fn () => Clp::parse($input))->toThrow(AmountOverflow::class)
        ->and(Clp::tryParse($input))->toBeNull();
})->with(['9223372036854775808', '-9223372036854775809', '$9.223.372.036.854.775.808', str_repeat('9', 200)]);

it('keeps formatting and serialization exact across the full integer range', function (int $pesos, string $formatted) {
    $amount = Clp::fromPesos($pesos);

    expect($amount->format())->toBe($formatted)
        ->and((string) $amount)->toBe((string) $pesos)
        ->and(json_encode($amount, JSON_THROW_ON_ERROR))->toBe((string) $pesos)
        ->and(Clp::parse($amount))->toBe($amount)
        ->and(Clp::parse($formatted)->pesos())->toBe($pesos);
})->with([
    [0, '$0'], [1234567, '$1.234.567'], [-1234567, '-$1.234.567'],
    [PHP_INT_MAX, '$9.223.372.036.854.775.807'], [PHP_INT_MIN, '-$9.223.372.036.854.775.808'],
]);

it('rejects coercible values even when called from a weakly typed PHP file', function () {
    $path = tempnam(sys_get_temp_dir(), 'chile-money-');
    file_put_contents($path, <<<'PHP'
<?php
$calls = [
    fn () => \GeolizeStudio\Chile\Clp::fromPesos(1.5),
    fn () => \GeolizeStudio\Chile\Clp::fromPesos(true),
    fn () => \GeolizeStudio\Chile\Clp::fromPesos('1'),
    fn () => \GeolizeStudio\Chile\Iva::fromRatio(19.0, 100),
    fn () => \GeolizeStudio\Chile\Iva::fromRatio(19, true),
    fn () => \GeolizeStudio\Chile\Iva::fromRatio('19', '100'),
];
return array_map(function ($call) {
    try { $call(); return null; } catch (Throwable $exception) { return $exception::class; }
}, $calls);
PHP);

    try {
        expect(include $path)->toBe([
            InvalidAmount::class, InvalidAmount::class, InvalidAmount::class,
            InvalidTaxRate::class, InvalidTaxRate::class, InvalidTaxRate::class,
        ]);
    } finally {
        unlink($path);
    }
});

it('adds and subtracts immutable values with exact signed boundaries', function () {
    $minimum = Clp::fromPesos(PHP_INT_MIN);
    $maximum = Clp::fromPesos(PHP_INT_MAX);

    expect($minimum->add($maximum)->pesos())->toBe(-1)
        ->and($maximum->add($minimum)->pesos())->toBe(-1)
        ->and($minimum->subtract($minimum)->pesos())->toBe(0)
        ->and($maximum->subtract($maximum)->pesos())->toBe(0)
        ->and($minimum->subtract(Clp::fromPesos(-1))->pesos())->toBe(PHP_INT_MIN + 1)
        ->and($maximum->add(Clp::fromPesos(-1))->pesos())->toBe(PHP_INT_MAX - 1)
        ->and($minimum->pesos())->toBe(PHP_INT_MIN)
        ->and($maximum->pesos())->toBe(PHP_INT_MAX);
});

it('throws before arithmetic overflow', function (int $left, string $operation, int $right) {
    expect(fn () => Clp::fromPesos($left)->{$operation}(Clp::fromPesos($right)))->toThrow(AmountOverflow::class);
})->with([
    [PHP_INT_MAX, 'add', 1], [PHP_INT_MIN, 'add', -1],
    [PHP_INT_MAX, 'subtract', -1], [PHP_INT_MIN, 'subtract', 1], [0, 'subtract', PHP_INT_MIN],
]);

it('normalizes bounded rational rates exactly', function () {
    expect(Iva::standard()->numerator)->toBe(19)
        ->and(Iva::standard()->denominator)->toBe(100)
        ->and(Iva::fromRatio(1900, 10000)->numerator)->toBe(19)
        ->and(Iva::fromRatio(1900, 10000)->denominator)->toBe(100)
        ->and(Iva::fromRatio(0, 100)->denominator)->toBe(1)
        ->and(Iva::fromRatio(1000000, 1)->numerator)->toBe(1000000)
        ->and(Iva::fromRatio(1, 1000000)->denominator)->toBe(1000000);
});

it('rejects unsupported rate components', function (mixed $numerator, mixed $denominator) {
    expect(fn () => Iva::fromRatio($numerator, $denominator))->toThrow(InvalidTaxRate::class);
})->with([[19, 0], [-1, 100], [19, -100], [1000001, 1], [1, 1000001], [19.0, 100], [true, 100], [19, null]]);

it('makes IVA input basis, rate, and rounding observable', function () {
    $rate = Iva::standard();
    $result = $rate->fromNet(Clp::fromPesos(10000), RoundingMode::HalfAwayFromZero);

    expect($result->net->pesos())->toBe(10000)
        ->and($result->tax->pesos())->toBe(1900)
        ->and($result->gross->pesos())->toBe(11900)
        ->and($result->rate)->toBe($rate)
        ->and($result->rounding)->toBe(RoundingMode::HalfAwayFromZero)
        ->and($result->inputBasis)->toBe('net')
        ->and($rate->fromGross($result->gross, $result->rounding)->net->pesos())->toBe(10000)
        ->and($rate->fromGross($result->gross, $result->rounding)->inputBasis)->toBe('gross')
        ->and(json_decode(json_encode($result, JSON_THROW_ON_ERROR), true))->toBe([
            'net' => 10000, 'tax' => 1900, 'gross' => 11900,
            'rate' => ['numerator' => 19, 'denominator' => 100],
            'rounding' => 'half_away_from_zero', 'input_basis' => 'net',
        ]);
});

it('rounds ties and signed fractional tax according to the explicit policy', function (RoundingMode $mode, array $expected) {
    $values = [5, -5, 15, -15, 4, -4, 6, -6];
    $actual = array_map(fn (int $pesos) => Iva::fromRatio(1, 10)->fromNet(Clp::fromPesos($pesos), $mode)->tax->pesos(), $values);

    expect($actual)->toBe($expected);
})->with([
    [RoundingMode::HalfAwayFromZero, [1, -1, 2, -2, 0, 0, 1, -1]],
    [RoundingMode::HalfEven, [0, 0, 2, -2, 0, 0, 1, -1]],
    [RoundingMode::TowardZero, [0, 0, 1, -1, 0, 0, 0, 0]],
    [RoundingMode::Floor, [0, -1, 1, -2, 0, -1, 0, -1]],
    [RoundingMode::Ceiling, [1, 0, 2, -1, 1, 0, 1, 0]],
]);

it('calculates gross integer extremes without an overflowing intermediate product', function (int $gross, int $net, int $tax) {
    $result = Iva::standard()->fromGross(Clp::fromPesos($gross), RoundingMode::HalfAwayFromZero);
    expect($result->net->pesos())->toBe($net)
        ->and($result->tax->pesos())->toBe($tax)
        ->and($result->net->add($result->tax)->pesos())->toBe($gross);
})->with([
    [PHP_INT_MAX, 7750732804079643535, 1472639232775132272],
    [PHP_INT_MIN, -7750732804079643536, -1472639232775132272],
]);

it('preserves both integer extremes at a zero rate', function (int $value) {
    foreach (RoundingMode::cases() as $rounding) {
        $rate = Iva::fromRatio(0, 1);
        expect($rate->fromNet(Clp::fromPesos($value), $rounding)->gross->pesos())->toBe($value)
            ->and($rate->fromGross(Clp::fromPesos($value), $rounding)->net->pesos())->toBe($value);
    }
})->with([PHP_INT_MIN, PHP_INT_MAX, 0]);

it('rejects tax or total overflow without changing its input', function (int $value) {
    $amount = Clp::fromPesos($value);
    expect(fn () => Iva::standard()->fromNet($amount, RoundingMode::HalfEven))->toThrow(AmountOverflow::class)
        ->and(fn () => Iva::fromRatio(1000000, 1)->fromNet($amount, RoundingMode::TowardZero))->toThrow(AmountOverflow::class)
        ->and($amount->pesos())->toBe($value);
})->with([PHP_INT_MIN, PHP_INT_MAX]);

it('preserves accounting invariants over bounded signed amounts and rates', function () {
    foreach ([Iva::standard(), Iva::fromRatio(0, 1), Iva::fromRatio(1, 3), Iva::fromRatio(1000000, 999999)] as $rate) {
        foreach (RoundingMode::cases() as $rounding) {
            for ($value = -1000; $value <= 1000; $value += 7) {
                foreach (['fromNet', 'fromGross'] as $method) {
                    $result = $rate->{$method}(Clp::fromPesos($value), $rounding);
                    expect($result->net->add($result->tax)->pesos())->toBe($result->gross->pesos());
                }
            }
        }
    }
});

it('rejects inconsistent calculation records', function () {
    expect(fn () => new TaxCalculation(Clp::fromPesos(10), Clp::fromPesos(2), Clp::fromPesos(11), Iva::standard(), RoundingMode::HalfEven, 'net'))
        ->toThrow(InvalidAmount::class)
        ->and(fn () => new TaxCalculation(Clp::fromPesos(10), Clp::fromPesos(2), Clp::fromPesos(12), Iva::standard(), RoundingMode::HalfEven, 'unknown'))
        ->toThrow(InvalidAmount::class);
});
