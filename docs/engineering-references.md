# Engineering references and adoption decisions

Reviewed on **2026-11-06** using live GitHub repository metadata and actual implementation, build, and CI files. This is an internal engineering decision record, not a comparative product claim. Stars indicate visibility, not correctness, security, or fitness. No dependency was installed and no external repository was modified during this review.

## Evidence snapshot

| Repository | Observed stars | Latest release checked | Implementation revision inspected |
| --- | ---: | --- | --- |
| [brick/money](https://github.com/brick/money) | 1,938 | 0.15.2 | `93f145c6c9f5b58160e2967088e7e083722e28cc` |
| [giggsey/libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php) | 5,075 | 9.0.43, from Composer metadata | `75a801c3dbf9a675e4bc83e0da2c2142fcc5344d` |
| [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools) | 951 | 1.93.3 | `132077d5f74d3a6b57ed5d2a47f6f3e71be419f4` |
| [peter-evans/create-pull-request](https://github.com/peter-evans/create-pull-request) | 2,872 | v8.1.1 | Release `5f6978faf089d4d20b00c7766989d076bb2fc7f1`; current main also inspected |

The first three revisions are inspected branch snapshots, not a claim that all observations apply unchanged to the listed release. Release and development compatibility must be checked separately before adopting a dependency. Metadata endpoints are `https://api.github.com/repos/{owner}/{repository}`; release endpoints are `.../releases/latest`. Source links below freeze the inspected content.

## Exact monetary behavior

The money implementation uses decimal/rational representations, returns new values from arithmetic operations, and requires a rounding decision when a result cannot fit its context. Its rational representation preserves precision across intermediate operations. These are implementation properties, not just README promises. [Money implementation](https://github.com/brick/money/blob/93f145c6c9f5b58160e2967088e7e083722e28cc/src/Money.php), [rational implementation](https://github.com/brick/money/blob/93f145c6c9f5b58160e2967088e7e083722e28cc/src/RationalMoney.php)

**Adopt the contract:** immutable values, exact input types, explicit rounding, and errors instead of silent precision loss. **Adapt the scope:** CLP/IVA need a small domain API, not an entire exchange-rate or accounting framework. Decide whether to use an established arithmetic dependency at implementation time; do not write a general arbitrary-precision engine merely to avoid a dependency. The current bounded integer/rational plan remains valid only with explicit range checks and verified arithmetic properties.

Its CI tests lowest dependencies on the minimum PHP version, multiple PHP versions, static analysis, and skipped-test failures. It fixes the Ubuntu image for internationalization tests because ICU/CLDR data can change output. [Actual CI](https://github.com/brick/money/blob/93f145c6c9f5b58160e2967088e7e083722e28cc/.github/workflows/ci.yml)

**Adapt:** test minimum and latest supported dependencies, and control locale-sensitive fixtures. Do not copy experimental runtime lanes into our support promise without running them.

## Upstream data with reproducible generation

The phone metadata build reads a declared metadata version, checks out that upstream revision, and generates production and test datasets through dedicated builders. The CI matrix exercises highest/lowest dependencies and multiple PHP/architecture combinations with explicit exclusions. [Build command](https://github.com/giggsey/libphonenumber-for-php/blob/75a801c3dbf9a675e4bc83e0da2c2142fcc5344d/build/Commands/BuildCommand.php), [CI](https://github.com/giggsey/libphonenumber-for-php/blob/75a801c3dbf9a675e4bc83e0da2c2142fcc5344d/.github/workflows/ci.yml)

**Adopt:** declared upstream identity, separate generators, generated fixtures, deterministic rebuild checks, and explicit compatibility exceptions. **Adapt for territory:** use immutable official-source snapshots and checksums when no upstream Git tag exists; keep observed-source status separate from the accepted catalog. **Avoid:** copying the build's cleanup-first order into our monitor. A failed download must leave the accepted territorial snapshot intact. Stage, validate, review, and only then replace generated output.

The inspected build demonstrates reproducible source selection. It does not establish that all upstream update scheduling is implemented in the inspected CI, nor that the phone project's data process can determine Chilean territorial legal validity.

## Laravel package integration and test coverage

The package service provider separates registration from boot behavior and delegates configuration, translations, commands, migrations, and other integration features into focused concerns. Its CI explicitly selects Laravel/Testbench versions, dependency stability, PHP, and operating system. [Provider implementation](https://github.com/spatie/laravel-package-tools/blob/132077d5f74d3a6b57ed5d2a47f6f3e71be419f4/src/PackageServiceProvider.php), [test workflow](https://github.com/spatie/laravel-package-tools/blob/132077d5f74d3a6b57ed5d2a47f6f3e71be419f4/.github/workflows/run-tests.yml)

**Adopt:** explicit framework compatibility tests and lifecycle separation. **Adapt:** use a small provider or the established helper only when it meaningfully reduces our integration code. **Avoid:** introducing unused routes, migrations, UI layers, broad extension requirements, or a full provider abstraction for a package that only needs bindings and validation. Copy the reasoning behind the test matrix, not every lane and extension.

## PR automation: reuse the lifecycle implementation

**Implemented decision:** delegate branch/commit/PR lifecycle code to the pinned `peter-evans/create-pull-request` action. Retain domain-specific artifact validation and a small ownership/path preflight. The action's code handles detached checkouts, repeated runs, branch resets after squash merges, no-diff states, force-with-lease pushes, and retry of specific post-creation eventual-consistency failures. [Branch lifecycle](https://github.com/peter-evans/create-pull-request/blob/5f6978faf089d4d20b00c7766989d076bb2fc7f1/src/create-or-update-branch.ts), [push and PR orchestration](https://github.com/peter-evans/create-pull-request/blob/5f6978faf089d4d20b00c7766989d076bb2fc7f1/src/create-pull-request.ts), [API handling](https://github.com/peter-evans/create-pull-request/blob/5f6978faf089d4d20b00c7766989d076bb2fc7f1/src/github-helper.ts)

Verified release pins:

```yaml
uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
uses: peter-evans/create-pull-request@5f6978faf089d4d20b00c7766989d076bb2fc7f1 # v8.1.1
```

The lines above are independent references, not a complete runnable workflow. Pins were resolved from each repository's Git ref API. Ubuntu's existing `python3` is sufficient for our stdlib-only monitor; no Python setup action is needed.

Required integration decisions, verified against the release's [action inputs](https://github.com/peter-evans/create-pull-request/blob/5f6978faf089d4d20b00c7766989d076bb2fc7f1/action.yml):

- Set an explicit `base` because checkout uses a frozen commit and therefore detached HEAD.
- Use a stable automation-only `branch`; do not use timestamp/random suffixes.
- Restrict `add-paths` to `resources/territory/observed-sources.json`.
- Supply a generated `body-path`; keep downloaded text out of shell interpolation.
- Use `delete-branch: false`; retain the existing no-auto-merge policy.
- Choose `draft: true` for draft creation only, or `draft: always-true` to return changed reviews to draft. Document the selected behavior.
- Preserve the read-only inspection job and separate write-capable proposal job. Validate candidate schema and permitted paths before the action runs.

The action manages its branch and may rewrite its history. It is not a substitute for our ownership check, protection against unrelated changes, or policy for a previously rejected PR. Keep these checks small and explicit. Do not allow manual work on the reserved automation branch. Pinning reduces mutable-reference risk; it does not eliminate the action's credential access or replace review.

## Current scaffold and remaining limits

1. `scripts/propose_source_review.py` trusts `github-actions[bot]` in existing-PR and orphan-branch ownership checks. A different GitHub App bot needs an explicit trusted-actor policy before it can be supported.
2. The Python helper reads GitHub metadata and prepares validated local files. The pinned action owns remote lifecycle operations, scoped to the dedicated branch and observation file.
3. PR history and file-list reads retrieve all pages. A regression checks unrelated changes on later pages.
4. The source monitor recognizes document links only in a specific official attachment section. Markup drift or a missing configured link fails visibly. It cannot automatically distinguish a redesigned page from a legal territorial change.
5. XLS/PDF magic and hashes establish observed bytes and basic container identity. The monitor does not parse territorial records, establish legal effective dates, or prove catalog correctness. New links trigger review without becoming trusted runtime data.
6. Download truncation, duplicate candidates, and an upstream A → B → A rollback are covered. Every successful observation reaches the ownership preflight so an obsolete owned proposal can be reconciled; unchanged sources without an open PR remain a no-op.

Final local verification: 36 automation tests passed, the committed manifest validated, and live official-source checks returned an unchanged observation. Independent review confirmed the reported blockers were resolved. Actual GitHub PR creation, update, and closure remain untested: the rollback regression proves reconciliation readiness, not a remote closure. Run a disposable-repository smoke test before relying on scheduled writes.

## Token and schedule limits remain unchanged

Current GitHub documentation says PR `opened`, `synchronize`, and `reopened` events created with `GITHUB_TOKEN` generate runs requiring approval; ordinary token-generated pushes do not trigger additional workflows. A configured GitHub App token can remove that PR approval step. The action does not change these platform rules. [Token behavior](https://docs.github.com/en/actions/concepts/security/github_token)

Schedules run from the default branch, can be delayed or dropped, and public repositories can have schedules disabled after 60 days without activity. Retain manual dispatch and visible failure evidence; do not promise uninterrupted self-maintenance. [Schedule constraints](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule)
