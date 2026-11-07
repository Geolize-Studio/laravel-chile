# Verification record

Recorded on 2026-11-06 during preparation of `0.1.0`. These results are implementation evidence, not a published support or legal-completeness certification.

## Runtime and compatibility

The pre-DX baseline local matrix passed **12/12 cases** on macOS 15.6 arm64, using real 64-bit PHP interpreters and Composer 2.11.1. Each case ran the same unchanged source snapshot. No Composer platform emulation was used. [Machine-readable results](matrix-results.json) include interpreter/source hashes, exact dependency versions, lock hashes, checks, and exit codes.

| PHP | Dependency policy | Laravel | Testbench | Result |
| --- | --- | --- | --- | --- |
| 8.3.36 | highest | 12.71.0 | 10.12.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.3.36 | lowest | 12.69.0 | 10.5.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.3.36 | highest | 13.40.0 | 11.3.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.3.36 | lowest | 13.30.0 | 11.0.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.4.14 | highest | 12.71.0 | 10.12.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.4.14 | lowest | 12.69.0 | 10.5.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.4.14 | highest | 13.40.0 | 11.3.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.4.14 | lowest | 13.30.0 | 11.0.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.5.12 | highest | 12.71.0 | 10.12.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.5.12 | lowest | 12.69.0 | 10.5.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.5.12 | highest | 13.40.0 | 11.3.0 | 203 tests, 14,098 assertions; no diagnostics |
| 8.5.12 | lowest | 13.30.0 | 11.0.0 | 203 tests, 14,098 assertions; no diagnostics |

All cases passed Composer validation, actual platform checks, domain/Testbench tests, and executable examples. All six current-dependency cases also passed PHPStan/Larastan at maximum level, Pint, and Composer audit. Minimum cases use the lowest eligible dependency versions under Composer's default security policy; vulnerable versions remain blocked. The earlier 12.24.0/13.0.0 compatibility fixtures are historical checks, not the current minimum-security resolution.

PHP 8.3.36 and 8.5.12 were built locally from SHA-256-verified official PHP release archives in isolated directories. PHP 8.4.14 was the existing local interpreter. All used ICU 77.1. No global PHP executable or shell configuration was changed.

The first matrix exposed upstream deprecations rather than package-domain failures. The final metadata raises the existing Pest development floor to `^4.7.8` (which selects the corrected PHPUnit/recursion-context chain) and excludes Symfony Translation `<6.4.3 || >=7.0 <7.0.3`. The latter is a transitive-version conflict, not an added runtime dependency. Compare the [6.4.3 fix](https://github.com/symfony/translation/blob/v6.4.3/Resources/functions.php) and [7.0.3 fix](https://github.com/symfony/translation/blob/v7.0.3/Resources/functions.php). PHPUnit now fails on deprecations; the matrix runner also rejects bootstrap diagnostics. All 12 cases were rerun cleanly after these changes.

The Laravel 12 floor remains deliberate: Laravel 12.24.0 and Testbench 10.5/core 10.6.1 contain the PHPUnit 12 error-handler restoration fixes. See the [Laravel release](https://github.com/laravel/framework/releases/tag/v12.24.0) and [Testbench requirements](https://github.com/orchestral/testbench/blob/v10.5.0/composer.json).

The baseline also passed all 18 GitHub-hosted Linux jobs in [run 42307301740](https://github.com/Geolize-Studio/laravel-chile/actions/runs/42307301740). The workflow mirrors the 12 runtime combinations and six production-consumer combinations. Reproduction commands are in [Contributing](../CONTRIBUTING.md).

## Consumer artifact and agent resources

`python3 scripts/test_consumer.py --with-boost` creates a Composer ZIP and installs it in a disposable Laravel application without development dependencies. The final consumer matrix passed all six combinations of PHP 8.3.36 / 8.4.14 / 8.5.12 and Laravel 12 / 13, using Composer 2.11.1. Each combination verifies:

- development/local files excluded from the artifact, with runtime data and agent resources retained;
- automatic provider discovery and one lazy territory singleton;
- validation and executable quickstart output;
- production platform requirements and absence of Testbench;
- operation before and after `config:cache`, followed by `config:clear`.

The same fixture then installs Laravel Boost 2.10.4 as a development dependency. With only Codex selected and MCP registration disabled, it verifies the package guideline in `AGENTS.md` and the formatted skill at `.agents/skills/chile-development/SKILL.md`. Installation preserves existing consumer guidance. Updating propagates a temporary source marker; restoring the source removes it on a subsequent update. Every write is inside the disposable consumer.

A fresh independent consumer agent then executed valid and invalid RUT cases, unambiguous and ambiguous territorial inputs, leading-zero CUTs, CLP/IVA calculations, and fractional rejection using the documented APIs. It correctly identified unavailable fuzzy matching, encoding repair, tax-applicability decisions, and Packagist installation. Its temporary scripts passed and were removed.

These checks prove package-resource delivery and the exercised agent tasks. They do not prove public indexing or automatic agent recommendation.

## Domain correctness

Tests cover strict grammars, canonical serialization, null, malformed historical values, unchanged Eloquent storage on reads, integer extremes, negative and tie rounding, hierarchy integrity, Unicode normalization, alias isolation, and ambiguity.

An independent runtime review found no confirmed defect in its reviewed scope. It compared 1,418 IVA cases against Python integer rational arithmetic, including signed extremes and overflow, and independently exercised SQLite BIGINT cast roundtrips. The implementation lane also compared 1,200 cases against Python `Fraction`.

Isolated temporary-copy mutations were detected by existing tests:

| Deliberate defect | Detection |
| --- | --- |
| Bypass RUT checksum | 5 assertion failures |
| Accept unchecked RUT cast writes | 4 assertion failures |
| Reverse negative rounding direction | 2 assertion failures |
| Change half-away tie comparison | 1 assertion failure |
| Invert half-even parity | 1 assertion failure |
| Assign the first ambiguous comuna | 2 assertion failures |

These are targeted mutation checks, not a full Pest mutation score. `composer test:mutations` requires PCOV or Xdebug; neither coverage driver is loaded locally, so the full command is not recorded as passing.

## Territory and maintenance

The baseline passed 48 Python automation tests: 36 source/proposal checks and 12 importer checks. The enum extension adds 12 generator tests, bringing the current total to 60. The official hash-pinned workbook was rebuilt twice with the pinned LibreOffice converter in an isolated profile with macros, active content, and external-link updates disabled. Both catalog and manifest were byte-identical. The [manifest](../resources/territory/catalog-manifest.json) records the exact source and output hashes, counts, converter, and date distinctions.

The committed source-observation manifest validates. Live checks against the configured SUBDERE publication and attachments found no change in those monitored resources. The separate [final territorial review](territory-review.md) checked the controlling legal record and independent official datasets: all 16 region and 346 comuna CUTs match INE Censo 2024; DPA 2023 confirms the region/province hierarchy and 345 comuna polygons. Antártica is absent only from that geometry dataset. The current catalog is accepted for the stated CUT/hierarchy scope, with official naming conflicts and the limited decree-page visual check documented explicitly. No catalog data changed.

An independent final review found no confirmed automation or packaging defect. Actionlint 1.7.12 passed all three workflows with no diagnostics, and action references are pinned. The official-source monitor passed remotely in [run 42307696415](https://github.com/Geolize-Studio/laravel-chile/actions/runs/42307696415). Sources were unchanged, so its PR step correctly skipped. The [manual synthetic fixture](https://github.com/Geolize-Studio/laravel-chile/actions/runs/42308727712) passed: bot-owned draft [PR #1](https://github.com/Geolize-Studio/laravel-chile/pull/1) was verified, closed without merging, and its branch deleted. This exercises draft permissions; neither exercise proves a future changed-source review or long-term schedule health.

## Import baseline

`php benchmarks/import.php` processes four batches of 10,000 synthetic RUT/territory/IVA rows with one reused catalog. The recorded run took 2.17–3.16 seconds per batch while other verification work was running. Retained memory was exactly 2,694,200 bytes after each batch; allocated memory remained 4 MiB. Measurement records are preallocated to avoid counting telemetry growth as runtime retention.

This is a machine-specific baseline, not a speed guarantee or CI timing budget. Domain source is independent of HTTP and SQL; an integration check also forbids Laravel HTTP requests and observes no database queries during catalog use.

## Ergonomics extension

After the baseline matrix, native territorial enums and direct value-object casts were added. Local verification passed 269 PHP tests with 16,471 assertions, 60 Python tests, maximum-level PHPStan, Pint, generator freshness, and executable examples. A production Laravel 13 consumer passed real SQLite enum/value-object persistence, serialization, null and dirty checks before and after configuration caching, without development dependencies. An independent review found no actionable defect in the 18 implementation/test files it reviewed. The table above remains baseline evidence. The extension passed all 18 Linux jobs (12 runtime combinations and six production consumers with Boost) on commit `5938e5dae025bcd28347d12491d6eddf5d8a1abe` in [run 42308721192](https://github.com/Geolize-Studio/laravel-chile/actions/runs/42308721192). [Machine-readable remote results](ci-results.json) record each job and the source, permission, and automation checks. Local Boost install/update/restore checks also passed after the documentation changes.

## Published-artifact verification

### Unreleased RUT format configuration

The configurable RUT source extension passed 310 PHP tests with 16,688 assertions, maximum-level PHPStan, Pint, and 60 Python automation tests on PHP 8.4.14. Disposable Laravel 12 and 13 applications installed a local Composer archive and exercised the default format plus published compact-format configuration before and after `config:cache`. The Laravel 13 consumer also passed Boost guideline and skill installation, update, and restoration. The public `0.1.0` artifact retains its original API. These results cover the development source and local archive.

The public repository, Packagist package, and private security reporting are configured. Release preparation verifies the exact commit before tagging. Once Packagist indexes the tag, run the public consumer check and record its result in the GitHub release notes.

```sh
python3 scripts/test_consumer.py --package-version 0.1.0 --expected-reference "$(git rev-parse v0.1.0^{commit})" --with-boost
```

This command resolves the package through public Packagist, verifies both source and distribution references against the tag, checks the installed artifact, and exercises Eloquent persistence, provider discovery, configuration caching, examples, and bundled Boost resources. Agent-discovery evidence covers resource delivery and the exercised integration tasks.
