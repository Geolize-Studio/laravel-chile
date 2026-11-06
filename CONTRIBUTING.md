# Contributing

This project is pre-release. Begin with [README.md](README.md), [AGENTS.md](AGENTS.md), and the relevant [domain documentation](README.md#documentation). The three domain modules are implemented locally but not released; distinguish implementation evidence from publication status.

## Writing and scope

Use English for prose, code, errors, comments, and examples. Preserve official Chilean terminology, names, codes, and original diagnostic input. Keep changes focused on one behavior with a reproducible example and appropriate tests.

The public header is intentionally broad. Do not add a current feature list or vendor branding to it. The Composer name and source attribution remain ordinary repository metadata.

## Automation checks

The current source-monitor tooling uses Python's standard library. Run from the repository root:

```sh
python3 -m unittest discover -s tests/automation -v
python3 scripts/territory_source_monitor.py --validate resources/territory/observed-sources.json
```

To perform a read-only live source check, write the candidate outside the accepted observation file:

```sh
python3 scripts/territory_source_monitor.py --output .source-review/observed-sources.json
```

This downloads approved official sources and compares observations. It does not interpret territorial records or update the runtime dataset. A reported source change requires review under the [territory policy](docs/territory.md).

Do not overwrite a baseline after a failed request, relax a parser simply to make an unexpected document pass, or execute upstream spreadsheet content. Tests should cover both successful observations and failed/truncated/redirected responses.

## PHP checks

Requires 64-bit PHP 8.3+, Composer 2, `intl`, `mbstring`, and SQLite for integration tests. Install dependencies and run:

```sh
composer install
composer validate --strict
composer check
php examples/quickstart.php
python3 scripts/test_consumer.py --with-boost
```

`composer check` runs Pest, PHPStan/Larastan at maximum level, and Pint. The consumer check archives the package, installs production dependencies in a temporary Laravel application, and checks discovery, examples, rules, and configuration caching. It needs network access to resolve Composer packages and does not modify the checkout's dependencies. `--with-boost` also verifies Codex guideline/skill installation and updates in that disposable app.

To repeat the compatibility matrix with a real local interpreter:

```sh
python3 scripts/test_matrix.py --php "$(command -v php)" --output .source-review/matrix-local
```

Repeat `--php` with explicit PHP 8.3, 8.4, and 8.5 executable paths to run all 12 cases. Use a new output directory for each run. The runner resolves minimum/current eligible dependencies for Laravel 12/13, records interpreter/source hashes and command exits, rejects bootstrap warnings, and removes each disposable `vendor` directory after its checks. Keep `results.json`, per-case logs, and lockfiles as evidence. It does not emulate PHP through Composer's `config.platform` setting.

For consumer coverage, repeat `scripts/test_consumer.py --php /path/to/php --laravel 12 --with-boost` for each interpreter and Laravel major. `--composer` selects an isolated Composer PHAR when needed. The published CI configuration mirrors the same runtime and consumer combinations.

For targeted work, use `composer test:unit`, `composer test:integration`, or Pest's `--filter`. `composer test:mutations` uses Pest mutation testing and requires a supported coverage driver (Xdebug or PCOV); missing coverage is a validation gap, not a passing result.

`php benchmarks/import.php` measures four batches of 10,000 synthetic rows with one reused catalog. Treat results as an environment-specific baseline; avoid performance claims based on a single machine.

Follow the [developer-experience principles](docs/developer-experience.md). Add regression fixtures for changed contracts and keep fixtures independent of the implementation.

## Documentation and agents

Keep examples paired with implementation tests. Update the README, domain contracts, bundled Boost guideline, and skill together when release status or behavior changes. The canonical consumer skill is in `resources/boost/skills/chile-development`; the repository-local `.agents/skills/chile-development` entry points to that folder.

Review the [discovery strategy](docs/discoverability.md) before changing public metadata. Use accurate descriptions and keywords for shipped features. Documentation should help readers choose the package appropriately, including understanding its limits.

## Pull requests

Describe the concrete problem, resulting behavior, test evidence, and any remaining limitation. Source-update PRs must include provenance and distinguish observed source changes from accepted dataset changes. An approved code or source-observation PR does not itself publish a package release.
