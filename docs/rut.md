# RUT

**Status: implemented in the pre-release workspace; not published.**

`Rut` parses, validates, stores, and formats Chilean identifiers. Validation checks structure and the dígito verificador (DV). It does not establish that an identifier exists, belongs to a person, or has a particular tax status. The [SII explanation of an incorrect DV](https://www.sii.cl/preguntas_frecuentes/inf_general/001_023_5071.htm) distinguishes the arithmetic check from other verification.

## Parse and format

```php
use GeolizeStudio\Chile\Rut;

$rut = Rut::parse('12.345.670-k');

$rut->body();      // '12345670'
$rut->dv();        // 'K'
$rut->toString();  // '12345670-K'
$rut->format();    // '12.345.670-K'
(string) $rut;     // '12345670-K'
json_encode($rut); // '"12345670-K"'

Rut::tryParse('12.345.678-4'); // null: incorrect DV
```

`Rut` is a final, readonly value object. Its constructor is private; both parsing methods return validated instances. Runtime operations are deterministic and perform no network requests. `parse(mixed $value): Rut` accepts strings only; integers, objects, arrays, and other input types are rejected without coercion. `tryParse(mixed $value): ?Rut` returns `null` for an `InvalidRut` error; it does not catch unrelated exceptions.

Canonical storage is an ungrouped body, an ASCII hyphen, and an uppercase DV. `format(): string` groups the body in thousands using periods. Both representations can be parsed again without changing the identifier. `body(): string` and `dv(): string` retain identifier components as text.

## Input grammar

The body must contain one to eight ASCII decimal digits, begin with `1`–`9`, and be followed by an ASCII hyphen and one DV character (`0`–`9`, `K`, or `k`). Periods are optional; when present, they must form complete groups of three digits from the right. Only surrounding ASCII spaces (`U+0020`) are trimmed. Tabs, line breaks, other whitespace, alternate hyphens, and non-ASCII digits are rejected.

These bounds define the supported arithmetic input domain, not a list of assigned taxpayer identifiers. A valid checksum for a short body does not establish identity or issuance. No minimum taxpayer age, legal-person classification, or assignment status is inferred from the body.

| Input | Result | Canonical representation |
| --- | --- | --- |
| `12.345.678-5` | Accept | `12345678-5` |
| `12345678-5` | Accept | `12345678-5` |
| ` 12.345.670-k ` | Accept | `12345670-K` |
| `1-9` | Accept arithmetic structure and DV | `1-9` |
| `12.345.678-4` | Reject incorrect DV | None |
| `12.345.678` | Reject missing DV | None |
| `123456785` | Reject missing hyphen | None |
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

`RutRule` implements Laravel's `ValidationRule` contract and accepts the same string grammar as `Rut::parse()`. Its default message is `The :attribute field must be a valid RUT.` The rule validates input without modifying it; parse the validated string to obtain the canonical representation.

Laravel decides whether to run non-implicit rules for absent or empty values. Include `required` when the field must be supplied; include `nullable` to allow `null`. The domain parser itself does not accept `null`.

## Eloquent storage

```php
use GeolizeStudio\Chile\Laravel\Casts\RutCast;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected function casts(): array
    {
        return ['rut' => RutCast::class];
    }
}
```

Use a string column with room for the canonical representation, such as `$table->string('rut', 10)->nullable()`. Schema creation, indexes, uniqueness, and backfills belong to the consuming application.

The cast accepts valid strings, existing `Rut` objects, and `null`. Writes store canonical text. Reads return a `Rut` object or `null`; malformed stored values throw `InvalidRut`. Model array and JSON serialization emit canonical strings or `null`. Assigning a differently formatted equivalent value to an already canonical attribute does not mark it dirty; assigning a different valid RUT does.

Before applying the cast to an existing column, inventory the records using raw database values and `Rut::tryParse()`. Review invalid records explicitly, retaining source values for correction. The cast disables Eloquent object caching so reading or serializing an existing formatted value does not rewrite its stored presentation on a later save. Explicitly assigning the attribute normalizes it. Plan data normalization in the consuming application; a display formatter is not a migration strategy.

## Verification

Run the focused suite from the package workspace:

```sh
vendor/bin/pest tests/Unit/RutTest.php tests/Integration/RutIntegrationTest.php
```

The tests cover independent valid and invalid fixtures, lowercase `k`, DV `0`, body boundaries, malformed separators, forbidden input types, immutable values, canonical and formatted round trips, JSON, Laravel validation, Eloquent nulls and dirty attributes, invalid writes, and malformed historical records. Compatibility and package-wide release checks are maintained separately; this module's implementation is not a published release.
