---
name: chile-development
description: Implement or integrate geolize-studio/laravel-chile domain features for RUT, Chilean territory, CLP, and IVA. Use when the task touches package contracts, validation, casts, territory matching, money, or package documentation.
---

# Chile Development

`geolize-studio/laravel-chile` has implemented RUT, territory, and money modules in an unreleased checkout. It is not published on Packagist. Before integrating, inspect the installed source, Composer constraints, tests, and version. Verify actual exports rather than assuming a newer feature exists.

## Implemented API

```php
use GeolizeStudio\Chile\{Clp, Iva, RoundingMode, Rut, Territory};
use GeolizeStudio\Chile\Territory\ResolutionStatus;

$rut = Rut::parse('12.345.678-5'); // Throws InvalidRut on invalid input.
$rut->toString(); // '12345678-5'; format() returns dotted presentation.
Rut::tryParse('12.345.678-9'); // null, never a repaired DV.

$territory = Territory::loadDefault(); // Reuse; Laravel also binds Territory::class.
$result = $territory->resolveComuna('Ñuñoa', regionCode: '13');
if ($result->status === ResolutionStatus::Matched) {
    $cut = $result->match->code; // '13120'
}

$calculation = Iva::standard()->fromNet(
    Clp::fromPesos(10_000), RoundingMode::HalfAwayFromZero,
);
$calculation->tax->pesos(); // 1900
$calculation->gross->pesos(); // 11900
Clp::tryParse('1.234,56'); // null
```

Public Laravel adapters are `GeolizeStudio\Chile\Laravel\Rules\RutRule`, `ClpRule`, and `GeolizeStudio\Chile\Laravel\Casts\RutCast`, `ClpCast`. Instantiate rules; put cast class names in a model's `casts()` map. Rules follow Laravel's required/nullable behavior. Casts preserve null, reject malformed historical values, and avoid rewriting a value just because it was read. No facade, config publishing, migration, or translation resource is exported.

Resolve the package root with `Composer\InstalledVersions::getInstallPath('geolize-studio/laravel-chile')`, or use the checkout root during package development. Documentation and example paths here are relative to that package root, not the consumer app or installed skill directory. Read `docs/rut.md`, `docs/money.md`, and `docs/territory.md` for full grammars and failure contracts; `examples/quickstart.php` is executable. Requirements are 64-bit PHP 8.3+, intl, mbstring, and Illuminate 12.24+ or 13. Consult `docs/verification.md` for tested combinations rather than treating CI configuration as execution evidence.

## Scope

The v1 scope is limited to three offline, deterministic domains:

- RUT parsing, validation, canonical storage, and presentation.
- Chilean territory through the official region → province → comuna hierarchy and CUT codes.
- CLP amounts in whole pesos and IVA arithmetic.

Do not add SII/DTE workflows, taxpayer lookup, tax-status decisions, PPU, holidays, phone numbers, UF/UTM, geocoding, carrier rates, AI matching, UI components, database models, or network calls. The package calculates domain values; it does not determine legal tax applicability, exemptions, identity, or compliance.

## RUT

Treat parsing, mathematical validation, identity, and presentation as separate concerns.

- Accept only documented input forms. Never remove arbitrary characters or repair an invalid verification digit.
- Preserve `null` separately from an invalid value. A malformed value must produce the package's documented error or result, never an empty or zero-like substitute.
- Use an uppercase `K` in canonical representation only after validation. Mathematical validity does not prove that a RUT exists, identifies its holder, or establishes tax status.
- When integrating Laravel validation or casts, inspect the installed rule and cast behavior first. Do not silently rewrite historical malformed database values.

## Territory

Territory resolution must be explainable and conservative.

- Keep CUT codes as strings so leading zeroes survive.
- Retain the original input and report every applied normalization or encoding repair.
- Exact, normalized, and explicitly configured alias matches may resolve only when unambiguous. Return the package's explicit ambiguous or not-found result otherwise.
- No encoding-repair or fuzzy-suggestion API is implemented. Mojibake is not automatically repaired; invalid UTF-8 is rejected. Never silently turn an uncertain name into a comuna.
- Treat approximate matches as suggestions, never as automatic assignments. Check the data snapshot version, effective date, source, and hash before changing territorial behavior.

## CLP and IVA

Use exact arithmetic and make each policy observable.

- Represent CLP in signed 64-bit whole pesos. Reject fractions, `float` inputs, non-finite values, malformed strings, and values outside the documented range; never truncate or coerce them.
- Check overflow before every conversion and intermediate operation. Do not rely on PHP converting an overflowing integer to `float`.
- Model IVA rates as rational values. Select and document a named rounding policy before calculation, including ties and negative values.
- For gross-price calculations, verify the invariant `net + IVA = gross` after rounding. Do not claim universal reversibility between net and gross values when rounding is involved.
- The standard 19% IVA rate is a calculation default only. The consuming application decides whether a transaction is taxable or exempt.

## Laravel Integration

The package is Laravel-first, but domain code must remain independent of facades, application models, global state, and database access. Use only the Illuminate components actually required by the implemented adapter. Before adding discovery metadata, translations, configuration, rules, or casts, confirm they exist in the source and cover them with Testbench integration tests.

Keep examples version-aware: inspect the consuming application's PHP and Laravel versions, then the package's own supported matrix. Run the relevant targeted tests, static analysis, and formatting checks from the package's documented Composer scripts. Do not add dependencies, change compatibility claims, or publish a release as part of ordinary integration work.

## Documentation and Verification

Write public material in English; retain correct Chilean domain terms such as RUT, comuna, CLP, IVA, and CUT. Keep examples executable against the actual installed release. Before claiming behavior, verify the source, tests, data manifest, and release notes for the installed version.

Run `composer check` and relevant automation tests after changing behavior. Add or update tests for invalid input, `null`, ambiguity, encoding rejection, rounding, negative amounts, and overflow. State a limitation plainly when the package does not export the needed capability.
