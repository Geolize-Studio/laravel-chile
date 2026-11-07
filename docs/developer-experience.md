# Developer experience

This guide describes the source checkout, including unreleased `0.2` RUT format configuration and compact input support. The published release is `0.1.0`. Its public surface is three immutable domains and a small Laravel adapter layer. See [verification evidence](verification.md) for completed checks and their revision.

## Package shape

[Laravel package discovery](https://laravel.com/docs/13.x/packages) registers `ChileServiceProvider` through Composer metadata. The provider binds one lazy `Territory` singleton and merges the RUT format default. Applications can publish `config/chile.php` through the optional `chile-config` tag to choose RUT storage and output.

The [Spatie Laravel skeleton](https://github.com/spatie/package-skeleton-laravel) and [Package Tools](https://github.com/spatie/laravel-package-tools) inform the structure: focused metadata, a small provider, Testbench integration, clear documentation, and routine quality checks. Optional resources are added only when they solve an actual consumer need. Package Tools itself is not a dependency because this provider needs only one binding.

Runtime dependencies are explicit Illuminate components and extensions; test tooling stays in `require-dev`. A clean production consumer installs the distribution archive without development packages. This follows [Composer's dependency model](https://getcomposer.org/doc/04-schema.md).

## Consistent entry points

| Task | Entry point | Failure behavior |
| --- | --- | --- |
| Validate and construct a RUT | `Rut::parse(mixed)` | `InvalidRut`, with a stable reason; no sensitive input echoed |
| Attempt a RUT | `Rut::tryParse(mixed)` | `null` for domain validation failure |
| Select a RUT output format | `Rut::withFormat(RutFormat)` | A new immutable value, preserving the parsed body and DV |
| Construct integer pesos | `Clp::fromPesos(mixed)` | Rejects non-integers even from weak PHP callers |
| Parse supported CLP input | `Clp::parse(mixed)` / `tryParse(mixed)` | Domain exception / `null`; never silently truncates |
| Calculate IVA | `Iva::fromNet()` / `fromGross()` | Explicit rounding required; checked arithmetic overflow |
| Look up a CUT | `Territory::region()` / `province()` / `comuna()` | Unknown string code returns `null` |
| Resolve a comuna name | `Territory::resolveComuna()` | Result distinguishes matched, ambiguous, and not found |

RUT and CLP values are final and readonly. Their constructors are private. RUT JSON uses its selected output format, with canonical output as the domain default. CLP JSON uses integer pesos. `TaxCalculation` exposes net, tax, gross, rate, rounding, and input basis, preserving `net + tax = gross`.

Request rules and Eloquent casts reuse the same domain parsing. Laravel's `required` / `nullable` rules control presence. Casts preserve null, reject invalid historic data, and disable Eloquent object caching so reading a presentation variant preserves its stored form on an unrelated save. Explicit RUT assignment uses the cast's selected format. See the [RUT](rut.md) and [money](money.md) guides for exact grammars and examples.

The Laravel RUT cast reads `chile.rut.format` and supports a per-attribute parameter such as `Rut::class.':compact'`. `Rut::parse()` retains a deterministic canonical default outside that adapter. Use `withFormat()` for a domain value's string/JSON preference, or `format(RutFormat)` for explicit rendering. This keeps configuration at the Laravel boundary while supporting application-specific storage conventions.

## Territory lifecycle

`Territory::loadDefault()` reads and validates the bundled catalog against its manifest. Reuse that object for imports; Laravel resolves it lazily once per container. There is no persistent process-global catalog, remote synchronization, or hidden invalidation mechanism.

A catalog's official names remain distinct from the normalization used for search. Aliases are supplied per instance and cannot rewrite official records. Resolution preserves the original input, transformations, candidate list, and match method. Unknown regional context is an error. There is no automatic mojibake repair or fuzzy assignment.

The [territory guide](territory.md) defines source identity and the difference between observing a document change and adopting a reviewed dataset. A downloaded source is not automatically a new legal truth.

## Documentation and agents

Examples must execute against the current source. The README points to exact domain contracts and a checked [quickstart](../examples/quickstart.php). Use these status labels precisely:

- **Implemented, unreleased:** source and tests exist in this checkout; no public installation claim.
- **Released:** an immutable tag and verified installable artifact exist.
- **Planned:** unavailable; do not document it as a callable API.

The bundled [skill](../resources/boost/skills/chile-development/SKILL.md) and guideline follow [Laravel Boost's package resource convention](https://laravel.com/docs/13.x/boost#third-party-package-skills). They teach correct selection, strict failures, and limitations. They do not guarantee package discovery or authorize modifications to global agent settings.

## Verification before release

Run domain and Testbench tests, PHPStan/Larastan at maximum level, Pint, source automation tests, executable examples, and the production-consumer smoke check. Exercise lowest resolvable and current dependencies across the supported PHP/Laravel matrix. A configured job is not a completed job.

Review targeted mutations for checksum bypass, ambiguous assignment, negative signs, and tie rounding. Inspect the final distribution for the required data and agent resources. Exercise Boost installation/update in a disposable consumer, and verify a fresh agent can handle both successful and invalid inputs without inventing APIs.

The [release checklist](release-checklist.md) retains publication gates separately from local implementation work. Do not advertise a compatibility range or performance result beyond its recorded evidence.
