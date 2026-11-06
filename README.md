<p align="center">
  <img src="docs/assets/readme-header.png" alt="Laravel Chile — Thoughtful tools for Chilean applications. A navy and ivory postage-stamp engraving of the snow-covered Andes." width="1200">
</p>

# Laravel Chile

Thoughtful tools for Chilean applications.

Strict RUT values, an offline official CUT catalog, and exact CLP/IVA arithmetic, with Laravel validation rules and Eloquent casts. Small immutable domain objects, explicit failures, and no runtime HTTP.

**Unreleased:** implemented and tested locally; not published on Packagist. The public installation command will be added after the first release. To develop this checkout:

```sh
composer install
composer check
composer test:automation
php examples/quickstart.php
```

## Quickstart

```php
use GeolizeStudio\Chile\Clp;
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
use GeolizeStudio\Chile\Laravel\Casts\ClpCast;
use GeolizeStudio\Chile\Laravel\Casts\RutCast;
use GeolizeStudio\Chile\Laravel\Rules\ClpRule;
use GeolizeStudio\Chile\Laravel\Rules\RutRule;

// Request validation:
$rules = [
    'rut' => ['nullable', new RutRule],
    'amount' => ['required', new ClpRule],
];

// Return these entries from an Eloquent model's casts() method:
$casts = ['rut' => RutCast::class, 'amount' => ClpCast::class];
```

Casts preserve `null`, reject malformed writes and historical values, and serialize to canonical strings or integer pesos. Reading an old formatted value does not silently rewrite it on the next save. Audit existing records before adopting strict casts. See [RUT](docs/rut.md) and [money](docs/money.md) for storage and validation details.

## Contracts and limits

| Domain | Available in this checkout | Deliberate boundary |
| --- | --- | --- |
| [RUT](docs/rut.md) | Strict grammar, DV validation, formatting, JSON, rules and casts | Checksum validity does not establish identity or issuance. |
| [Territory](docs/territory.md) | Región → provincia → comuna, CUT lookup, explainable name resolution, per-instance aliases | Uncertain matches remain ambiguous or not found; no fuzzy assignment, geocoding, or automatic encoding repair. |
| [CLP and IVA](docs/money.md) | Signed integer pesos, exact rational rates, five explicit rounding modes, rules and casts | No fractional pesos, float coercion, tax-applicability decisions, or invoice-wide allocation. |

Territory ships the hash-pinned SUBDERE `CUT_2018_v04` snapshot: 16 regions, 56 provinces, and 346 comunas. Official names and codes are retained, with source and legal references in the [manifest](resources/territory/catalog-manifest.json). The [final territorial review](docs/territory-review.md) accepted its CUT/hierarchy scope after checking the legal record and comparing official INE/DPA products. Differences in official presentation names are documented; the package does not supply boundary geometry. Source observations can open draft review PRs; they never silently adopt data, merge changes, or publish releases.

## Compatibility and verification

Requires 64-bit PHP 8.3+, `intl`, `mbstring`, and Laravel/Illuminate 12.24+ or 13. The local matrix passed all 12 PHP 8.3–8.5 / Laravel 12–13 combinations with minimum eligible and current dependencies, plus all six clean production-consumer combinations. See [verification evidence](docs/verification.md) for exact versions and the distinction between local results and pending GitHub-hosted execution.

## Documentation

- [RUT grammar, errors, and integration](docs/rut.md)
- [Territorial catalog, resolution, and maintenance](docs/territory.md)
- [CLP, IVA, rounding, and overflow](docs/money.md)
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
