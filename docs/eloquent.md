# Eloquent integration

The package's immutable values work directly in Laravel's `casts()` method. Use `Rut::class`, `Clp::class`, and `Iva::class` for value objects, and native enum classes for territory and rounding. The package provides the casts; your application owns its models, schema, validation, and data migration.

Configurable RUT formats and compact input in this guide belong to unreleased `0.2` development. The published `0.1.0` release uses canonical RUT storage and requires a hyphen in input.

## Schema and model

A minimal application migration can use these column types:

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('invoices', function (Blueprint $table): void {
    $table->id();
    $table->string('rut', 10)->nullable();
    $table->bigInteger('amount')->nullable();
    $table->json('iva_rate')->nullable();
    $table->string('rounding')->nullable();
    $table->string('region_code', 2)->nullable();
    $table->string('province_code', 3)->nullable();
    $table->string('comuna_code', 5)->nullable();
    $table->timestamps();
});
```

`BIGINT` must be signed to represent credits and the full CLP range. RUT and CUT are identifiers, so use string columns. Integer CUT columns lose leading zeros. The JSON column stores an exact rate, not a decimal percentage. Nullable columns above illustrate absence; choose required columns and constraints according to your application. The package does not publish a migration or create tables.

```php
use GeolizeStudio\Chile\{Clp, Comuna, Iva, Province, Region, RoundingMode, Rut};
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'rut' => Rut::class,
            'amount' => Clp::class,
            'iva_rate' => Iva::class,
            'rounding' => RoundingMode::class,
            'region_code' => Region::class,
            'province_code' => Province::class,
            'comuna_code' => Comuna::class,
        ];
    }
}
```

`Rut`, `Clp`, and `Iva` implement Laravel's `Castable` contract. The explicit adapters `GeolizeStudio\Chile\Laravel\Casts\RutCast`, `ClpCast`, and `IvaCast` remain valid entries in the same map. Territory and rounding use Laravel's native enum casts without package-specific adapters.

### RUT configuration — unreleased

Publish `config/chile.php` using `php artisan vendor:publish --tag=chile-config`, then set `rut.format` to `canonical`, `compact`, or `formatted`. The default is `canonical`. This selects the representation used for explicit writes, cast value string/JSON output, and model array/JSON serialization.

```php
// config/chile.php
return [
    'rut' => ['format' => 'compact'],
];

// An attribute can override the application setting.
protected function casts(): array
{
    return ['rut' => Rut::class.':formatted'];
}
```

All three formats accept the same validated input variants. The largest supported RUT needs 9 characters in compact form, 10 in canonical form, or 12 in formatted form. Adjust the example schema's RUT column length before selecting formatted storage. Reading historical values preserves their stored representation on an unrelated save. Explicit assignment applies the selected format. See the [RUT guide](rut.md#eloquent-storage-and-output) for the complete contract.

## Assignment and serialization

```php
$invoice = new Invoice;
$invoice->rut = Rut::parse('12.345.678-5');
$invoice->amount = Clp::fromPesos(10_000);
$invoice->iva_rate = Iva::standard();
$invoice->rounding = RoundingMode::HalfAwayFromZero;
$invoice->comuna_code = Comuna::Nunoa;
$invoice->province_code = $invoice->comuna_code->province();
$invoice->region_code = $invoice->comuna_code->region();
$invoice->save();
$invoice->refresh();

$invoice->rut->format(); // '12.345.678-5'
$invoice->amount->pesos(); // 10000
$invoice->comuna_code->label(); // 'Ñuñoa'

$calculation = $invoice->iva_rate->fromNet($invoice->amount, $invoice->rounding);
$calculation->tax->pesos(); // 1900
$calculation->gross->pesos(); // 11900
```

| Cast | Accepted assignments, besides `null` | Model array / JSON value |
| --- | --- | --- |
| `Rut::class` | `Rut` or a valid documented RUT string | Selected format, defaulting to `'12345678-5'` in canonical form |
| `Clp::class` | `Clp`, integer pesos, or a documented CLP string | Integer pesos, such as `10000` |
| `Iva::class` | `Iva` or an array with exactly integer `numerator` and `denominator` | Object-shaped ratio, such as `{"numerator":19,"denominator":100}` |
| `Region::class`, `Province::class`, `Comuna::class` | Matching enum case or complete string CUT | String CUT, including leading zeros |
| `RoundingMode::class` | Matching enum case or its backing string | String, such as `'half_away_from_zero'` |

`toArray()` returns IVA's ratio as a PHP array; `toJson()` emits it as a JSON object. Null remains `null` for every cast. CLP JSON uses an integer, so clients whose number type cannot preserve all signed 64-bit values should transport a string explicitly in their API resource. See the [money contract](money.md#amounts).

An IVA rate is independent of rounding and tax applicability. The cast does not calculate a tax amount, choose a rounding policy, decide exemptions, or assert that IVA applies. When territory columns are stored together, derive their parents as above or validate consistency explicitly; independent native enum casts only check membership. See [territory enums](enums.md) for lookups, hierarchy methods, and select options.

## Exact IVA persistence

The rate is a reduced rational value. Both constructors and the cast accept integer components within the documented [bounds](money.md#iva). They do not accept floats, numeric strings, percentages, or arbitrary extra fields.

```php
$invoice->iva_rate = ['numerator' => 1900, 'denominator' => 10000];
$invoice->iva_rate->toArray(); // ['numerator' => 19, 'denominator' => 100]

$invoice->iva_rate = Iva::fromRatio(0, 100);
$invoice->iva_rate->toArray(); // ['numerator' => 0, 'denominator' => 1]

$invoice->iva_rate = null; // Missing rate, not a zero rate.
```

The cast serializes the ratio to JSON for storage and decodes database JSON when reading. Assign an `Iva` or a valid array; assigning `'19%'`, `0.19`, or the string `'{"numerator":19,"denominator":100}'` throws `InvalidTaxRate`. For external JSON input, decode and validate before assignment. JSON key order or an equivalent fraction in an existing record does not make its rate dirty. Assigning a different rate does.

## Validation and historical records

Use the exported request rules and Laravel's native enum validation before persisting user input:

```php
use GeolizeStudio\Chile\Laravel\Rules\ClpRule;
use GeolizeStudio\Chile\Laravel\Rules\RutRule;
use Illuminate\Validation\Rule;

$validated = $request->validate([
    'rut' => ['nullable', new RutRule],
    'amount' => ['required', new ClpRule],
    'comuna_code' => ['required', 'string', Rule::enum(Comuna::class)],
]);
```

Rules validate without transforming the request. Laravel controls missing and nullable fields. No package request rule for IVA is exported: validate its input shape in your application, then construct the domain value with `Iva::fromArray()` or `fromRatio()` before assignment.

Malformed RUT, CLP, and IVA values raise their domain exceptions (`InvalidRut`, `InvalidAmount` / `AmountOverflow`, and `InvalidTaxRate`). Unknown native enum values raise `ValueError`. Those failures also surface when reading or serializing invalid historical data. Do not silently map malformed data to null, zero, or a guessed code.

Reading a valid legacy presentation does not rewrite it on the next save. Value-object casts disable object caching, and IVA compares the normalized ratio rather than raw JSON formatting. Assign an explicit replacement when changing data. Plan and review any cleanup of existing records separately from adding strict casts; the package does not repair database values automatically.

The integration tests exercise persistence, null, serialization, dirty detection, invalid assignments, and malformed historical values through SQLite and Eloquent. For the tested PHP/Laravel combinations and database-driver limits, see [verification evidence](verification.md).
