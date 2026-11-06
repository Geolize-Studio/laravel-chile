# Territory enums

`GeolizeStudio\Chile\Region`, `Province`, and `Comuna` are native PHP string-backed enums for the accepted bundled catalog: 16 regiones, 56 provincias, and 346 comunas. They provide autocomplete, explicit parent relationships, select options, and Laravel's built-in enum casting. Their membership reflects the reviewed snapshot described in the [territory guide](territory.md), not a claim that every subsequent legal change has been adopted.

```php
use GeolizeStudio\Chile\{Comuna, Province, Region};

Region::Metropolitana->value; // '13'
Region::Tarapaca->code(); // '01'
Comuna::Nunoa->label(); // 'Ñuñoa'
Comuna::Nunoa->province(); // Province::Santiago
Comuna::Nunoa->region(); // Region::Metropolitana
Province::Santiago->region(); // Region::Metropolitana

Region::Tarapaca->provinces(); // [Province::Iquique, Province::Tamarugal]
Province::Iquique->comunas(); // [Comuna::Iquique, Comuna::AltoHospicio]
Region::Tarapaca->comunas(); // All seven comunas, ordered by CUT.
```

`value` and `code()` are the same canonical string CUT. `label()` preserves the exact accepted source spelling, including accents and source labels such as `Los Angeles`. Case names are stable PHP identifiers: `Nunoa` is a symbol, not a replacement for the official label `Ñuñoa`. Longer region labels have concise symbols such as `OHiggins`, `Aysen`, `Magallanes`, and `Metropolitana`; `label()` still returns the complete source name.

## Input and persistence

Use native `from()` when an unknown code should throw `ValueError`; use `tryFrom()` when the application handles an unknown value explicitly. Supply the complete string CUT, including leading zeros. These methods do not normalize names, pad codes, or resolve aliases.

```php
Comuna::from('01101'); // Comuna::Iquique
Comuna::tryFrom('1101'); // null
Comuna::tryFrom('Ñuñoa'); // null
Comuna::tryFrom('99999'); // null
```

For names, aliases, custom snapshots, or an explainable ambiguous result, use `Territory`. Its immutable records live under the separate `GeolizeStudio\Chile\Territory` namespace. They remain useful for caller-reviewed snapshots whose membership differs from the bundled enums. Convert deliberately through a CUT when both representations apply:

```php
use GeolizeStudio\Chile\{Comuna, Territory};

$record = app(Territory::class)->comuna(Comuna::Nunoa->value);
$enum = $record === null ? null : Comuna::tryFrom($record->code);
```

Persist CUT values, never PHP case names or translated labels. Use string database columns: `VARCHAR(2)` for a region, `VARCHAR(3)` for a province, and `VARCHAR(5)` for a comuna. Integer storage discards leading zeros. Native backed-enum JSON serialization emits the string value: `json_encode(Comuna::Iquique)` returns `"01101"`.

## Laravel casts and validation

Use [Laravel's native enum casts](https://laravel.com/docs/13.x/eloquent-mutators#enum-casting) directly. No package-specific enum cast is needed.

```php
use GeolizeStudio\Chile\{Comuna, Province, Region};
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected function casts(): array
    {
        return [
            'region_code' => Region::class,
            'province_code' => Province::class,
            'comuna_code' => Comuna::class,
        ];
    }
}

$address = new Address;
$address->comuna_code = Comuna::Iquique;
$address->save(); // Stores '01101' in the string column.
$address->refresh()->comuna_code; // Comuna::Iquique
$address->toArray()['comuna_code']; // '01101'
```

Casts preserve `null`, serialize to CUT strings, and keep unchanged values clean after a database roundtrip. An unknown or unpadded stored value throws `ValueError` when accessed or serialized; it is not repaired. Validate request data before assigning it:

```php
use GeolizeStudio\Chile\Comuna;
use Illuminate\Validation\Rule;

$validated = $request->validate([
    'comuna_code' => ['required', 'string', Rule::enum(Comuna::class)],
]);

$comuna = Comuna::from($validated['comuna_code']);
$region = $comuna->region();
```

An enum validates membership only. Independent region, province, and comuna casts do not enforce that three columns belong to the same hierarchy. Derive the parent from the selected comuna when appropriate, or compare supplied parents explicitly with `region()` and `province()`. Database constraints and application validation remain the consumer's responsibility.

## Select options

Each enum exposes `options()`, returning CUT-to-label pairs in CUT order. Parent filters are optional and intersect when both are supplied.

```php
Region::options();
Province::options(Region::Tarapaca);
// ['011' => 'Iquique', '014' => 'Tamarugal']

Comuna::options(region: Region::Tarapaca);
Comuna::options(province: Province::Iquique);
// ['01101' => 'Iquique', '01107' => 'Alto Hospicio']

Comuna::options(Region::Metropolitana, Province::Iquique); // []
```

PHP converts a numeric string array key without leading zeros into an integer: the key for `'13'` is `13`, while `'01'` stays a string. The return type is therefore `array<int|string, string>`; this does not change any enum's string value. For an API that requires explicit string identifiers, build a list of objects instead of relying on object keys:

```php
$options = array_map(
    fn (Comuna $comuna): array => [
        'value' => $comuna->value,
        'label' => $comuna->label(),
    ],
    Region::Tarapaca->comunas(),
);
```

These methods return fresh arrays. Labels and parent relationships are generated into PHP; calling them does not read a JSON file, query a database, or contact a remote source.

## Maintaining generated enums

The accepted [catalog](../resources/territory/catalog.json) is the source for codes, labels, and parents. The reviewed [case map](../resources/territory/enum-cases.json) maps those CUT codes to public PHP symbols. The map is deliberately separate from labels: a spelling correction must not silently rename a case used by applications.

After a separately reviewed catalog update:

1. Review additions and removals by CUT and update the case map explicitly. Keep existing symbols unless a deliberate compatibility change is approved. New collisions require distinct reviewed names.
2. Regenerate the three enums with `python3 scripts/generate_territory_enums.py`.
3. Review the generated diff, including parent changes and removed cases. Removing or renaming a public case affects downstream source compatibility even when the legal source changes.
4. Run the generator check, domain tests, and automation tests before merging.

```sh
python3 scripts/generate_territory_enums.py --check
vendor/bin/pest tests/Unit/TerritoryEnumTest.php tests/Integration/TerritoryEnumIntegrationTest.php
python3 -m unittest discover -s tests/automation -p test_territory_enums.py -v
```

The generator checks the accepted manifest hash, unique codes, complete parent relationships, valid noncolliding symbols, and exact case-map coverage. Output is deterministic and `--check` detects missing or changed generated files without writing them. New source observations do not regenerate these enums or modify the accepted catalog automatically. See the [source-update gates](territory.md#semantic-update-gates).
