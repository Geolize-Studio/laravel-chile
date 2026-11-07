# RUT

**Source documentation for unreleased 0.2 development.** The published release is `0.1.0`. Compact input, `RutFormat`, `withFormat()`, and Laravel format configuration require the development source described here.

`Rut` parses, validates, stores, and formats Chilean identifiers. Validation checks structure and the dígito verificador (DV). It does not establish that an identifier exists, belongs to a person, or has a particular tax status. The [SII explanation of an incorrect DV](https://www.sii.cl/preguntas_frecuentes/inf_general/001_023_5071.htm) distinguishes the arithmetic check from other verification.

## Parse and format

```php
use GeolizeStudio\Chile\{Rut, RutFormat};

$rut = Rut::parse('12.345.670-k');

$rut->body();      // '12345670'
$rut->dv();        // 'K'
$rut->toString();  // '12345670-K'
$rut->format();    // '12.345.670-K'
(string) $rut;     // '12345670-K'
json_encode($rut); // '"12345670-K"'

$compact = Rut::parse('12345670k')->withFormat(RutFormat::Compact);
$compact->toString(); // '12345670K'
json_encode($compact); // '"12345670K"'
$compact->format(); // '12.345.670-K'
$compact->format(RutFormat::Canonical); // '12345670-K'

Rut::tryParse('12.345.678-4'); // null: incorrect DV
```

`Rut` is a final, readonly value object. Its constructor is private; both parsing methods return validated instances. Runtime operations are deterministic and perform no network requests. `parse(mixed $value): Rut` accepts strings only; integers, objects, arrays, and other input types are rejected without coercion. `tryParse(mixed $value): ?Rut` returns `null` for an `InvalidRut` error; it does not catch unrelated exceptions.

`Rut::parse()` creates a value with canonical output by default. `withFormat(RutFormat $format): Rut` returns a new immutable value whose `toString()`, string conversion, and JSON use the selected representation. `body(): string` and `dv(): string` retain identifier components as text.

| Format | Enum case | Example | Maximum length |
| --- | --- | --- | --- |
| `canonical` | `RutFormat::Canonical` | `12345678-5` | 10 |
| `compact` | `RutFormat::Compact` | `123456785` | 9 |
| `formatted` | `RutFormat::Formatted` | `12.345.678-5` | 12 |

`format(RutFormat $format = RutFormat::Formatted): string` explicitly renders the requested format. Calling `format()` produces dotted presentation. All output formats use an uppercase DV and can be parsed again. Direct domain parsing uses its deterministic default, while the Eloquent adapter applies Laravel configuration when constructing a cast value.

## Input grammar

The body must contain one to eight ASCII decimal digits, begin with `1`–`9`, and be followed by one DV character (`0`–`9`, `K`, or `k`). An ASCII hyphen between the body and DV is optional. Periods are optional and must form complete groups of three body digits from the right when present. In compact input, the final character is the supplied DV. Only surrounding ASCII spaces (`U+0020`) are trimmed. Tabs, line breaks, other whitespace, alternate hyphens, and non-ASCII digits are rejected.

These bounds define the supported arithmetic input domain, not a list of assigned taxpayer identifiers. A valid checksum for a short body does not establish identity or issuance. No minimum taxpayer age, legal-person classification, or assignment status is inferred from the body.

| Input | Result | Canonical representation |
| --- | --- | --- |
| `12.345.678-5` | Accept | `12345678-5` |
| `12345678-5` | Accept | `12345678-5` |
| `123456785` | Accept | `12345678-5` |
| `12.345.6785` | Accept | `12345678-5` |
| ` 12.345.670-k ` | Accept | `12345670-K` |
| `1-9` | Accept arithmetic structure and DV | `1-9` |
| `12.345.678-4` | Reject incorrect DV | None |
| `12.345.678` | Reject missing DV | None |
| `1.2345.678-5` | Reject malformed grouping | None |
| `012345678-5` | Reject leading zero and excess length | None |
| `0-0` | Reject zero body | None |
| `abc12345678-5` | Reject unexpected characters | None |
| Empty string or `null` | Reject | None |

Parsing never deletes arbitrary characters, supplies a missing DV, or repairs an incorrect DV. Keep original import values separately when diagnostics require them.

## Error handling

```php
use GeolizeStudio\Chile\Exceptions\InvalidRut;
use GeolizeStudio\Chile\Rut;

try {
    $rut = Rut::parse($input);
} catch (InvalidRut $exception) {
    $reason = $exception->reason;
}
```

`InvalidRut` extends `InvalidArgumentException` and exposes a readonly `reason` string:

| Reason | Meaning |
| --- | --- |
| `invalid_type` | Input is not a string. |
| `invalid_format` | Input does not match the complete supported grammar. |
| `invalid_checksum` | Structure is valid, but the supplied DV is incorrect. |

Exception messages are English and do not include the submitted identifier. Use `parse()` when the application needs the reason; use `tryParse()` when a nullable result is sufficient. Neither method treats an invalid identifier as an empty string or zero.

## Laravel validation

```php
use GeolizeStudio\Chile\Laravel\Rules\RutRule;

public function rules(): array
{
    return [
        'rut' => ['required', new RutRule],
        'billing_rut' => ['nullable', new RutRule],
    ];
}
```

`RutRule` implements Laravel's `ValidationRule` contract and accepts the same string grammar as `Rut::parse()`, including compact input. Its default message is `The :attribute field must be a valid RUT.` The rule validates input. Assign the validated string through the cast to store and return the configured representation.

Laravel decides whether to run non-implicit rules for absent or empty values. Include `required` when the field must be supplied; include `nullable` to allow `null`. The domain parser itself does not accept `null`.

## Eloquent storage and output

Publish the optional configuration with the package tag.

```sh
php artisan vendor:publish --tag=chile-config
```

```php
// config/chile.php
return [
    'rut' => ['format' => 'compact'],
];
```

`chile.rut.format` accepts `canonical`, `compact`, or `formatted`, and defaults to `canonical`. The setting controls explicit writes, the output preference of cast values, and model array/JSON serialization. An unsupported setting raises `InvalidArgumentException` when the cast is constructed.

```php
use GeolizeStudio\Chile\Rut;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected function casts(): array
    {
        return ['rut' => Rut::class];
    }
}

$customer = new Customer;
$customer->rut = '12.345.678-5';
$customer->save(); // Stores '123456785' with the compact configuration above.

$customer->rut->toString(); // '123456785'
(string) $customer->rut; // '123456785'
$customer->toArray()['rut']; // '123456785'
$customer->rut->format(); // '12.345.678-5'
```

Override the application setting for an individual attribute using Laravel's cast parameter syntax.

```php
protected function casts(): array
{
    return ['rut' => Rut::class.':formatted'];
}
```

The explicit adapter `GeolizeStudio\Chile\Laravel\Casts\RutCast::class` supports the same parameter. A cast's selected format takes precedence over the output preference of an assigned `Rut` object. Configuration affects Eloquent operations. For a value created directly with `Rut::parse()`, select an output preference with `withFormat()` or render a particular representation with `format()`.

Use a string column sized for the selected representation. The maximum lengths are 9 for compact, 10 for canonical, and 12 for formatted. Allow 12 characters if the application may switch among all three. Schema creation, indexes, uniqueness, and backfills belong to the consuming application.

The cast accepts valid strings in every supported input format, existing `Rut` objects, and `null`. Reads return a `Rut` object or `null`, and malformed stored values throw `InvalidRut`. Assigning an equivalent value stays clean when its formatted storage matches the existing raw value. An explicit assignment writes the selected representation, including when changing a historical presentation.

Before applying the cast to an existing column, inventory raw values with `Rut::tryParse()` and review invalid records. Reading or serializing a historical value preserves its raw stored text on a later save because the cast disables object caching. Changing configuration affects output and future assignments. Plan any backfill separately, including duplicate checks where several stored forms represent the same RUT.

## Verification

Run the focused suite from the package workspace:

```sh
vendor/bin/pest tests/Unit/RutTest.php tests/Integration/RutIntegrationTest.php
```

The focused suites exercise parsing, formatting, Laravel validation, and Eloquent persistence. See the [verification record](verification.md) for completed checks and their exact revision. Configurable formats and compact input are part of the unreleased development changes described above.
