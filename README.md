<p align="center">
  <img src="docs/assets/readme-header.png" alt="Laravel Chile — Thoughtful tools for Chilean applications. A navy and ivory postage-stamp engraving of the snow-covered Andes." width="1200">
</p>

# Laravel Chile

Thoughtful tools for Chilean applications.

Strict RUT values, an offline official CUT catalog with native territory enums, and exact CLP/IVA arithmetic, with Laravel validation rules and Eloquent casts. Small immutable domain objects, explicit failures, and no runtime HTTP.

**Unreleased:** the [public repository](https://github.com/Geolize-Studio/laravel-chile) is available; no version is published on Packagist. To evaluate the development branch in a compatible Laravel application:

```sh
composer config repositories.laravel-chile vcs https://github.com/Geolize-Studio/laravel-chile
composer require geolize-studio/laravel-chile:dev-main
```

`dev-main` is a moving development branch, not a stable release. Commit your application's Composer lock file to retain the resolved revision. For package development from this checkout:

```sh
composer install
composer check
composer test:automation
php examples/quickstart.php
```

## Quickstart

```php
use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Comuna;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\RoundingMode;
use GeolizeStudio\Chile\Rut;
use GeolizeStudio\Chile\Territory;

$rut = Rut::parse('12.345.678-5');
$rut->toString(); // '12345678-5'
$rut->format();   // '12.345.678-5'
Rut::tryParse('12.345.678-9'); // null: incorrect DV

$territory = app(Territory::class); // Automatically registered, lazily loaded.
$territory->comuna('01101')->name; // 'Iquique'; CUT codes stay strings.
$place = $territory->resolveComuna('Ñuñoa');
$place->match?->code; // '13120'; inspect status before accepting uncertain input.

Comuna::Nunoa->value; // '13120'
Comuna::Nunoa->label(); // 'Ñuñoa'
Comuna::Nunoa->province(); // Province::Santiago
Comuna::Nunoa->region(); // Region::Metropolitana

$price = Iva::standard()->fromNet(
    Clp::fromPesos(10_000),
    RoundingMode::HalfAwayFromZero,
);
$price->tax->pesos();   // 1900
$price->gross->pesos(); // 11900
Clp::tryParse('1.234,56'); // null: fractional pesos are rejected.
```

The [executable quickstart](examples/quickstart.php) asserts these results. Outside Laravel, use `Territory::loadDefault()` and reuse the returned instance. No configuration publishing, database migration, or facade is required.

## Laravel integration

```php
use GeolizeStudio\Chile\{Clp, Comuna, Iva, Region, Rut};
use GeolizeStudio\Chile\Laravel\Rules\ClpRule;
use GeolizeStudio\Chile\Laravel\Rules\RutRule;
use Illuminate\Validation\Rule;

// Request validation:
$rules = [
    'rut' => ['nullable', new RutRule],
    'amount' => ['required', new ClpRule],
    'comuna_code' => ['required', 'string', Rule::enum(Comuna::class)],
];

// Return these entries from an Eloquent model's casts() method:
$casts = [
    'rut' => Rut::class,
    'amount' => Clp::class,
    'iva_rate' => Iva::class,
    'comuna_code' => Comuna::class,
];

// CUT-to-label pairs for selects:
Comuna::options(region: Region::Metropolitana);
```

Use string columns for RUT and CUT, a signed `BIGINT` for pesos, and a JSON column for IVA's exact ratio: `{"numerator":19,"denominator":100}`. `Rut`, `Clp`, and `Iva` work directly as casts; territory enums use Laravel's built-in enum casting. Explicit `RutCast`, `ClpCast`, and `IvaCast` classes are also available.

Casts preserve `null` and reject malformed writes and historical values. Reading a legacy presentation does not silently rewrite it on the next save. An IVA rate does not determine tax applicability, and separate territory casts do not validate their parent relationship. See the [Eloquent guide](docs/eloquent.md) for schema examples, serialization, and failure behavior, and [territory enums](docs/enums.md) for hierarchy helpers and select options.

## Contracts and limits

| Domain | Available in this checkout | Deliberate boundary |
| --- | --- | --- |
| [RUT](docs/rut.md) | Strict grammar, DV validation, formatting, JSON, rules and casts | Checksum validity does not establish identity or issuance. |
| [Territory](docs/territory.md) | Native enums, región → provincia → comuna, CUT lookup, explainable name resolution, per-instance aliases | Uncertain matches remain ambiguous or not found; no fuzzy assignment, geocoding, or automatic encoding repair. |
| [CLP and IVA](docs/money.md) | Signed integer pesos, exact rational rates, five explicit rounding modes, rules and casts | No fractional pesos, float coercion, tax-applicability decisions, or invoice-wide allocation. |

Territory ships the hash-pinned SUBDERE `CUT_2018_v04` snapshot: 16 regions, 56 provinces, and 346 comunas. Official names and codes are retained, with source and legal references in the [manifest](resources/territory/catalog-manifest.json). The [final territorial review](docs/territory-review.md) accepted its CUT/hierarchy scope after checking the legal record and comparing official INE/DPA products. Differences in official presentation names are documented; the package does not supply boundary geometry. Source observations can open draft review PRs; they never silently adopt data, merge changes, or publish releases.

## Compatibility and verification

Requires 64-bit PHP 8.3+, `intl`, `mbstring`, and Laravel/Illuminate 12.24+ or 13. The local matrix passed all 12 PHP 8.3–8.5 / Laravel 12–13 combinations with minimum eligible and current dependencies, plus all six clean production-consumer combinations. See [verification evidence](docs/verification.md) for exact versions and the distinction between local results and pending GitHub-hosted execution.

## Documentation

- [RUT grammar, errors, and integration](docs/rut.md)
- [Territorial catalog, resolution, and maintenance](docs/territory.md)
- [CLP, IVA, rounding, and overflow](docs/money.md)
- [Native territory enums and select options](docs/enums.md)
- [Eloquent casts, schemas, and serialization](docs/eloquent.md)
- [Developer experience](docs/developer-experience.md)
- [Engineering references and adoption decisions](docs/engineering-references.md)
- [Agent discovery and adoption](docs/discoverability.md)
- [Verification evidence](docs/verification.md) and [release checklist](docs/release-checklist.md)
- [Contributing](CONTRIBUTING.md), [changelog](CHANGELOG.md), and [security](SECURITY.md)
- [Artwork references](docs/artwork.md)

## Coding agents

Maintainers: read [AGENTS.md](AGENTS.md). Consumers: use the versioned [skill](resources/boost/skills/chile-development/SKILL.md) and [Boost guideline](resources/boost/guidelines/core.blade.php). Resources follow Laravel Boost's third-party package layout. Installation and update were exercised with Boost 2.10.4 in a disposable Codex consumer; see [the evidence](docs/verification.md).

The skill can also be installed deliberately in a client's documented skill location. Composer does not install it globally or change agent settings. This repository's `.agents/skills/chile-development` alias points to the canonical bundled resource. [llms.txt](llms.txt) is an optional navigation index, not a guarantee of indexing or recommendation.

## Language and license

Documentation, code, errors, examples, and agent guidance are English. Chilean terminology and official names retain their correct spelling. Code is [MIT licensed](LICENSE); source-document and artwork terms are described separately in their provenance documents.
