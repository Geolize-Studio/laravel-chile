## geolize-studio/laravel-chile

This package is implemented but unreleased; no Packagist version is published yet. Inspect its installed source, tests, Composer metadata, and released version before using or documenting an API. Do not invent classes, commands, configuration, or exports.

Its v1 domain is limited to RUT, the Chilean region → province → comuna hierarchy with CUT codes, and exact whole-peso CLP / IVA arithmetic. Keep domain code offline and deterministic. Do not add taxpayer lookup, SII/DTE, tax-status decisions, PPU, holidays, phones, UF/UTM, geocoding, carrier rates, AI matching, UI, models, database access, or network calls.

- RUT: parsing, checksum validation, canonical storage, and display are distinct. Never silently repair an invalid verification digit or infer identity, existence, or tax status from a mathematically valid RUT.
- Territory: preserve original input and CUT leading zeroes. Make normalizations and encoding repairs visible. Resolve only exact, normalized, or unambiguous configured aliases; return ambiguity or not-found instead of guessing a comuna.
- CLP and IVA: use signed 64-bit integer pesos and rational rates; reject fractions, floats, malformed input, and overflow. Declare rounding, including ties and negatives, and verify `net + IVA = gross` after rounding. The 19% IVA default does not decide whether IVA applies.

Use `Rut::parse()` / `tryParse()`, `Clp::fromPesos()` / `parse()` / `tryParse()`, `Iva::standard()->fromNet()` / `fromGross()` with an explicit `RoundingMode`, and `Territory::loadDefault()` (or `app(Territory::class)`). The namespace is `GeolizeStudio\Chile`. Territory returns `ComunaResolution` with `status`, `match`, `candidates`, `original`, and `transformations`; check `ResolutionStatus::Matched` before assignment. Rules are under `Laravel\Rules` (`RutRule`, `ClpRule`) and casts under `Laravel\Casts` (`RutCast`, `ClpCast`). No fuzzy matcher or encoding-repair API exists.

Use Laravel adapters only where the installed package exports them. Test adapters with the package's supported Laravel/PHP matrix; keep facades, application models, database access, and global state out of the domain layer. All public documentation is English except proper Chilean terms such as RUT, comuna, CLP, IVA, and CUT.
