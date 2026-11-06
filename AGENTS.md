# Laravel Chile contributor guidance

## Current state

This repository contains the `0.1` release line. Read README.md and the relevant domain document before editing. The initial release is `0.1.0`; later source-checkout changes may not be in an installed version. Inspect the actual files before referring to classes, scripts, dependencies, or exports. Source monitoring is separate from the offline PHP runtime.

Use the repository-local `chile-development` skill for domain implementation and integration work. Its canonical source is `resources/boost/skills/chile-development/SKILL.md`; `.agents/skills/chile-development` is a local alias. Read `docs/developer-experience.md` before designing public APIs.

## Language and identity

Write public documentation, code identifiers, error messages, comments, examples, and skills in English. Preserve Chilean domain terminology, official proper names, source records, and exact diagnostic fixture inputs. Do not add comparisons with other products to package documentation.

The header title is `LARAVEL CHILE`; its subtitle is `Thoughtful tools for Chilean applications.` Do not add a vendor name, current module list, or release number to the artwork. The Composer vendor remains `geolize-studio`.

## Domain boundaries

- RUT: distinguish parsing, checksum validation, formatting, and identity. Do not repair a wrong dígito verificador or silently rewrite historical records.
- Territory: official names and codes are source data; aliases are a separate search layer. Preserve CUT leading zeros and report ambiguity. Download dates are not legal effective dates.
- CLP/IVA: use exact integer/rational arithmetic, checked ranges, and an explicit rounding policy. Malformed input must not become zero or a truncated amount. Calculating IVA does not determine its applicability.
- Keep runtime operations deterministic and independent of HTTP, application models, database state, and credentials. Maintenance automation may access the explicitly approved official sources.

## Source updates

Read docs/territory.md before changing source configuration, manifests, parsers, or generated data. An observed-source update is not an accepted catalog update. Do not bypass failed checks, overwrite accepted data with an unverified download, infer legal changes from a spreadsheet timestamp, or turn on automatic merges/releases.

Prefer the established pinned PR action for branch/commit/PR lifecycle work. Keep local automation focused on domain-specific source checks, artifact validation, and ownership. Dependabot can propose action-pin updates; review them before merge.

## Verification

Use the commands that actually exist. Run the relevant automation tests for source-monitor changes. Run `composer check`, `composer test:automation`, and `python3 scripts/test_consumer.py` for changes affecting the package or its distribution. See docs/verification.md for compatibility evidence and outstanding checks. Never describe a planned command or proposed API as tested.

Keep documentation and the bundled skill aligned with the release status. Keep actual signatures and executable examples synchronized before a release. Do not change unrelated user or agent configuration as part of installation.
