# Changelog

## 0.1.0 — 2026-11-06

First public release. During `0.x`, APIs may change in a minor release. The `^0.1` Composer constraint accepts patch releases below `0.2.0`.

- Native backed region, province, and comuna enums with stable case identities, hierarchy helpers, and filtered options.
- Direct Eloquent value-object casts for RUT, CLP, and exact rational IVA rates, including strict serialization and semantic dirty detection.
- Strict RUT value objects, validation, canonical storage, formatting, and Laravel casts.
- Whole-peso CLP values and exact rational IVA calculations with explicit rounding.
- Offline CUT catalog from the hash-pinned SUBDERE workbook, hierarchy lookup, conservative comuna resolution, and isolated aliases.
- Reproducible catalog tooling and reviewed source-observation proposals.
- Laravel package discovery, executable examples, domain documentation, and bundled agent guidance.
- Verified PHP 8.3–8.5 / Laravel 12–13 runtime and production-consumer matrices; deprecation checks and compatible dependency floors.
- Final official-source territorial comparison with documented naming differences and no CUT/hierarchy changes.

The bundled territorial snapshot is `subdere-cut-2018-v04`, with 16 regions, 56 provinces, and 346 comunas. See the [verification record](docs/verification.md) for executed compatibility checks and the [territorial review](docs/territory-review.md) for provenance and scope.
