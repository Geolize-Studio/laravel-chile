# CLP and IVA

**Status: implemented in the local pre-release workspace; not yet published.** These examples describe the checked-in API. See the repository verification record for the tested compatibility matrix.

`Clp` represents whole pesos with signed 64-bit integers. `Iva` calculates one amount at a time with exact rational arithmetic and an explicit rounding policy. Runtime calculations require no network, database, credentials, or locale configuration. Invoice-wide allocation, foreign exchange, UF/UTM, and tax-applicability decisions are outside this API.

## Amounts

```php
use GeolizeStudio\Chile\Clp;

$price = Clp::parse('$1.234');
$price->pesos();                    // 1234
$price->format();                   // '$1.234'
(string) $price;                    // '1234'
json_encode($price);                // '1234' (a JSON integer)
$price->add(Clp::fromPesos(100));    // a new Clp representing 1334
$price->subtract(Clp::fromPesos(34)); // a new Clp representing 1200

Clp::tryParse('1.234,56');           // null
Clp::parse('1.234,56');              // throws InvalidAmount
```

`fromPesos(mixed $pesos): Clp` accepts only a PHP integer. `parse(mixed $value): Clp` accepts an integer, a documented string, or an existing `Clp` (returned unchanged). Both explicitly inspect input types, so floats, booleans, and numeric strings passed to `fromPesos` are rejected even from a PHP file without `strict_types`.

`tryParse(mixed $value): ?Clp` follows the same grammar and range; it returns `null` for invalid or overflowing input. Use `parse` and catch the documented exception when you need to distinguish those failure categories. Neither path returns zero for invalid data.

### String grammar

A string consists of an optional leading `+` or `-`, an optional `$` followed by zero or more ASCII spaces, and whole-peso digits. The sign comes before `$`. Digits are either ungrouped, or grouped with `.` and exactly three digits after each separator. Grouped strings start with one to three digits. Leading zeros are rejected, except the single digit `0`; signed zero becomes canonical `0`.

Surrounding ASCII spaces, tabs, carriage returns, newlines, vertical tabs, and form feeds are trimmed. Internal whitespace is allowed only immediately after `$`, and only as ASCII spaces. Nonbreaking spaces, NUL bytes, Unicode digits, decimal fractions, scientific notation, and other currency labels are not accepted.

| Input | Result |
| --- | --- |
| `1234`, `'1234'`, `'1.234'`, `'$1.234'`, `'$ 1.234'` | 1234 pesos |
| `'-1.234'`, `'-$1.234'` | −1234 pesos |
| `' +1234 '` | 1234 pesos |
| `0`, `'0'`, `'-0'` | 0 pesos |
| `'01'`, `'0.000'`, `'1234.567'`, `'1.23'`, `'$-1234'` | `InvalidAmount` |
| `'1.234,56'`, `1234.0`, `true`, `null`, `''` | `InvalidAmount` |
| `'9223372036854775808'` | `AmountOverflow` |

The full supported range is **−9,223,372,036,854,775,808 through 9,223,372,036,854,775,807** pesos. `format()` always emits the deterministic Chilean display form (`'$1.234'`, `'-$1.234'`), without changing process locale or converting to float. Canonical string output omits `$`, grouping, and a positive sign. JSON contains an integer: consumers whose number type cannot represent all 64-bit integers should transport `(string) $amount` explicitly instead.

## IVA

The [SII documents a standard IVA rate of 19%](https://www.sii.cl/preguntas_frecuentes/impuestos_mensuales/001_130_0572.htm). `Iva::standard()` represents that rate as `19/100`. Selecting it calculates an amount; the application remains responsible for deciding whether IVA applies.

```php
use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\RoundingMode;

$rate = Iva::standard();
$result = $rate->fromNet(Clp::fromPesos(10000), RoundingMode::HalfAwayFromZero);

$result->net->pesos();   // 10000
$result->tax->pesos();   // 1900
$result->gross->pesos(); // 11900
$result->rate;          // the immutable Iva rate
$result->rounding;      // RoundingMode::HalfAwayFromZero
$result->inputBasis;    // 'net'

$included = $rate->fromGross(Clp::fromPesos(11900), RoundingMode::HalfAwayFromZero);
$included->net->pesos(); // 10000
$included->inputBasis;  // 'gross'
```

Both methods return an immutable `TaxCalculation`. For net input, the tax is `net × numerator / denominator`, rounded once; gross is net plus that tax. For gross input, net is `gross × denominator / (denominator + numerator)`, rounded once; tax is gross minus net. Every successful result satisfies `net + tax = gross`. Net-to-gross and gross-to-net are not universally reversible after rounding.

`Iva::fromRatio(mixed $numerator, mixed $denominator): Iva` accepts integer components only: numerator **0–1,000,000**, denominator **1–1,000,000**. Bounds apply before reducing the fraction. The stored public readonly `numerator` and `denominator` are reduced by their greatest common divisor. A zero rate becomes `0/1`. This is a bounded calculation facility, not a catalog of legally applicable rates.

```php
Iva::fromRatio(1900, 10000); // reduced to 19/100
Iva::fromRatio(0, 1);       // explicit zero rate
Iva::fromRatio(19.0, 100);  // throws InvalidTaxRate
Iva::fromRatio(19, 0);     // throws InvalidTaxRate
```

### Exact rate serialization

`Iva::fromArray(mixed $value): Iva` accepts exactly two keys, `numerator` and `denominator`, with integer values. It applies the same bounds and reduction as `fromRatio`. Missing or additional keys, numeric strings, floats, percentages, objects, and JSON strings throw `InvalidTaxRate`. Decode external JSON explicitly before using this domain constructor.

```php
$rate = Iva::fromArray(['numerator' => 1900, 'denominator' => 10000]);
$rate->toArray();   // ['numerator' => 19, 'denominator' => 100]
json_encode($rate); // '{"numerator":19,"denominator":100}'
```

### Rounding

There is no implicit rounding policy. Pass a `RoundingMode` to every IVA calculation; strings are not accepted in place of the enum. The policy applies to tax for `fromNet`, and to net for `fromGross`.

| Policy | Behavior | +0.5 | −0.5 | +1.5 | −1.5 |
| --- | --- | ---: | ---: | ---: | ---: |
| `HalfAwayFromZero` | Nearest integer; ties away from zero | 1 | −1 | 2 | −2 |
| `HalfEven` | Nearest integer; ties to the even integer | 0 | 0 | 2 | −2 |
| `TowardZero` | Discard the fractional part | 0 | 0 | 1 | −1 |
| `Floor` | Toward negative infinity | 0 | −1 | 1 | −2 |
| `Ceiling` | Toward positive infinity | 1 | 0 | 2 | −1 |

For example, net 50 at 19% produces tax 10 with `HalfAwayFromZero`, and net −50 produces tax −10. These named policies are calculation choices, not a claim about a legally required invoice policy.

`TaxCalculation` serializes to JSON with integer `net`, `tax`, and `gross`, a `rate` object containing integer `numerator` and `denominator`, a `rounding` enum value such as `'half_even'`, and `input_basis` (`'net'` or `'gross'`). Obtain calculations through `Iva`; the result constructor is internal.

## Laravel boundaries

```php
use GeolizeStudio\Chile\Laravel\Rules\ClpRule;

$rules = [
    'amount' => ['required', new ClpRule],
    'optional_amount' => ['nullable', new ClpRule],
];
```

`ClpRule` uses the domain parser and validates without rewriting request data. Laravel controls missing, empty, and nullable fields: use `required` when presence is mandatory. Validation does not convert the accepted value into a `Clp`; parse the validated input explicitly.

Use a signed `BIGINT` database column for the full supported amount range. Add the cast to your application's model:

```php
use GeolizeStudio\Chile\Laravel\Casts\ClpCast;

protected function casts(): array
{
    return ['amount' => ClpCast::class];
}
```

`ClpCast` accepts `Clp`, integer, or parser-valid string assignments and stores integer pesos. Reads return `Clp`; `null` remains `null` in both directions. Model arrays and JSON contain integer pesos or `null`. Reading a valid legacy display string does not rewrite its raw attribute; assigning a value explicitly canonicalizes it. Invalid historical values throw instead of becoming zero, and invalid assignments throw before replacing the attribute.

### Direct value object casts

The value objects implement Laravel's `Castable` contract, so their class names work directly in a model's `casts()` method. The explicit `ClpCast`, `RutCast`, and `IvaCast` adapters remain available with identical behavior.

```php
use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\RoundingMode;
use GeolizeStudio\Chile\Rut;

protected function casts(): array
{
    return [
        'amount' => Clp::class,
        'rut' => Rut::class,
        'iva_rate' => Iva::class,
        'rounding' => RoundingMode::class,
    ];
}
```

Use a signed `BIGINT` for `amount`, a string column for `rut`, a `JSON` or `TEXT` column for `iva_rate`, and a string column for `rounding`. Use nullable columns only when absence is meaningful to your application. The package does not create database columns.

```php
$invoice->amount = Clp::fromPesos(10000);
$invoice->iva_rate = Iva::standard();
$invoice->rounding = RoundingMode::HalfAwayFromZero;
$invoice->save();

$calculation = $invoice->iva_rate->fromNet($invoice->amount, $invoice->rounding);
$calculation->gross->pesos(); // 11900
```

`IvaCast` accepts an `Iva`, an exact ratio array, or `null`. Writes use canonical JSON such as `{"numerator":19,"denominator":100}`. Reads return an immutable `Iva`; model arrays and JSON expose the same ratio object. Null stays null and never means zero or an exempt transaction. The rounding policy remains a separate native enum cast; changing a rate does not silently change that policy. `TaxCalculation` remains a derived result rather than a second persisted source of monetary totals.

A rate's mathematical value controls dirty detection: assigning `1900/10000` over `19/100` is unchanged, including when a database reorders JSON keys. Reading or saving an unchanged rate never rewrites historical JSON formatting or fraction representation. Changed rates are stored in canonical reduced form. Object caching is disabled for these immutable casts.

Malformed historical rate JSON, wrong shapes, additional fields, floats, and invalid ratio components throw `InvalidTaxRate` on reading or serialization. Invalid assignments throw before changing the attribute. To repair an invalid historical value, assign an explicitly reviewed valid rate; it is marked dirty and can be saved. There is no automatic data repair. Use Eloquent model assignment for these guarantees: query-builder inserts and bulk updates bypass model casts.

## Errors and limits

All exception classes are under `GeolizeStudio\Chile\Exceptions`:

| Exception | Meaning |
| --- | --- |
| `InvalidAmount` (`InvalidArgumentException`) | Unsupported type or malformed whole-peso input |
| `AmountOverflow` (`OverflowException`) | Parsed value or arithmetic exceeds signed 64-bit range |
| `InvalidTaxRate` (`InvalidArgumentException`) | Malformed rate representation, non-integer or out-of-bounds rate component |

Arithmetic checks integer limits before addition, subtraction, multiplication, or rounding adjustment. Quotient/remainder decomposition allows gross calculations at both integer extremes without forming an overflowing multiplication. An unrepresentable tax or gross amount throws, leaving immutable inputs unchanged. A 64-bit PHP runtime is required by Composer and checked when constructing an amount.

## Verification

Run the module's checked-in tests with:

```sh
vendor/bin/pest tests/Unit/MoneyTest.php tests/Unit/IvaSerializationTest.php tests/Integration/MoneyIntegrationTest.php tests/Integration/ValueObjectCastsTest.php
```

Tests cover grammar, weakly typed callers, exact formatting and JSON, signed integer boundaries, invalid rates, all rounding policies, accounting invariants, and Laravel validation/casts. Boundary IVA fixtures were independently calculated with exact rational arithmetic. Full compatibility-matrix and mutation-test evidence belongs in the release verification record; this page does not imply publication or completed release gates.
