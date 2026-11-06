# Package discovery and agent adoption

Research checked: **2026-11-06**. Package: `geolize-studio/laravel-chile`.

**Status: pre-release design.** This document specifies publication and verification work. The workspace contains a bundled skill, but there is no public repository, Packagist release, deployed documentation site, or released PHP API yet. The implemented checkout has a production-consumer smoke test; see [verification evidence](verification.md). Examples below are planned metadata and future checks, not working installation instructions.

## What success means

An agent should be able to find the package for a relevant Chilean development task, assess whether its released capabilities fit, identify a compatible version, and produce correct integration code from authoritative documentation. Public discovery and local skill activation are separate mechanisms. Neither guarantees that an agent will recommend the package.

| Stage | Primary surface | Evidence of success |
| --- | --- | --- |
| Find before installation | Packagist, GitHub, public documentation | The exact package is retrievable; relevant search results can be inspected |
| Decide whether to adopt | README, compatibility table, tested examples, limitations | An agent can explain fit and exclusions without inventing features |
| Use after installation | Versioned documentation, Laravel Boost resources, installed skills | A fresh consumer project produces the expected behavior in tests |
| Maintain safely | Changelog, upgrade guide, source provenance | An agent can identify changes and avoid mixing incompatible versions |

## Public discovery: supported mechanisms

### Composer and Packagist

Composer defines package names, descriptions, keywords, homepage, license, support links, and runtime requirements. Keywords support search and filtering. Keep metadata specific, truthful, and consistent with the released scope. Omit a manual `version` field when versions come from VCS tags. [Composer schema](https://getcomposer.org/doc/04-schema.md)

Planned baseline metadata:

```json
{
  "name": "geolize-studio/laravel-chile",
  "type": "library",
  "description": "Chilean domain primitives and Laravel integration with explicit validation and documented data provenance.",
  "keywords": ["php", "laravel", "chile", "chilean", "validation"]
}
```

Add module keywords only when their behavior is implemented, tested, documented, and included in the release: `rut`, `run`, `clp`, `iva`, `cut`, `comunas`, `regiones`, `provincias`, `ppu`. Do not add terms for roadmap features. Set `homepage` and `support.docs`, `support.issues`, and `support.source` only to real public endpoints. Declare the actual license, PHP/extensions, and framework constraints.

Packagist exposes anonymous name and tag search, vendor listing, and Composer metadata. Agents can use these interfaces without installing the package. Search results are paginated: absence from page one does not prove absence. Composer v2's `p2` metadata represents tagged releases; development branches use the `~dev` variant. [Packagist API](https://packagist.org/apidoc)

After publication, verify:

```text
GET https://packagist.org/search.json?q=geolize-studio%2Flaravel-chile
GET https://packagist.org/search.json?q=laravel%20chile
GET https://packagist.org/search.json?tags=chile
GET https://packagist.org/packages/list.json?vendor=geolize-studio
GET https://repo.packagist.org/p2/geolize-studio/laravel-chile.json
```

These are verification targets, not claims about their current responses. Preserve response timestamps, package URLs, and release versions in the release evidence. Follow pagination and published API usage limits; avoid polling for rank changes.

### GitHub

Use the repository name `laravel-chile`, a concise English About description, and a real documentation link. Suggested initial topics: `php`, `laravel`, `chile`, `chilean`, `composer-package`, `validation`. Add domain topics as modules ship. GitHub supports topic-based discovery; topics must be relevant and there can be at most 20. [GitHub topics](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/classifying-your-repository-with-topics)

The README should explain utility, capabilities, and usage. [GitHub README guidance](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-readmes) Our first screen should contain:

1. The exact Composer name and release status.
2. A concrete description of supported problems and who benefits.
3. Tested PHP/Laravel compatibility and required extensions.
4. One minimal example with expected output, linked to its test.
5. Links to installation, API contracts, domain sources, and agent setup.

Provide a capability table distinguishing **available**, **experimental**, and **planned**. Do not turn future APIs into copyable quickstart examples. Avoid inflated quality claims, keyword repetition, fabricated adoption statistics, and badges for checks that do not exist.

### Public documentation and search engines

Publish useful English HTML documentation with stable URLs, normal navigation links, descriptive titles, and readable code. Preserve Chilean domain terms such as RUT, RUN, CUT, CLP, IVA, comuna, región, provincia, and PPU, explaining each on first use. These terms are part of the domain vocabulary, not a reason to duplicate every page in another language.

Pages intended for discovery must be publicly accessible, return successful HTTP responses, and contain indexable text. Verify that authentication, robots rules, `noindex`, or CDN challenges do not unintentionally prevent access. Eligibility does not guarantee indexing. [Google technical requirements](https://developers.google.com/search/docs/essentials/technical)

Provide a sitemap when a documentation site exists, keep navigation crawlable, and use Search Console to inspect indexing. A sitemap helps discover URLs but does not guarantee crawling, indexing, or ranking. [Google sitemap guidance](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview)

## Help agents choose the package for the right reason

Create one substantial task guide per shipped use case, with canonical API names and expected behavior:

| Task intent | Evidence the guide should provide |
| --- | --- |
| Validate Chilean RUT input in Laravel | Accepted representations, checksum behavior, error codes, nullable handling, and the distinction between checksum validity and identity verification |
| Resolve comuna names from imports | Original input, normalization steps, CUT result, ambiguity handling, and the limits of approximate suggestions |
| Parse CLP and calculate IVA | Input grammar, exact arithmetic, overflow handling, rounding policy, and reconciliation examples |
| Handle another released module | Supported scope, exclusions, version requirements, minimal example, and test evidence |

These are documentation requirements, not a commitment to ship every module in the initial release. An agent should also learn when **not** to use the package: unsupported jurisdiction, unimplemented capability, incompatible runtime, or a task requiring an external authoritative lookup.

Each API page should state the package version it describes, method signatures, accepted input types, outputs, exceptions/error results, nullable behavior, side effects, and examples. Link data-backed features to their source, retrieval date, transformation notes, and update policy. Explain what is deterministic and what is merely a suggestion.

## Agent usage after installation

### Laravel Boost

Ship concise guidance at `resources/boost/guidelines/core.blade.php` and a task-focused skill at `resources/boost/skills/chile-development/SKILL.md`. Boost discovers package resources during its installation/update workflow; skill installation depends on user preferences. This helps consumers who already installed the package. It does not make an unknown package appear in every agent's context. The hosted Search Docs API has a documented supported ecosystem; publishing third-party resources is not evidence of automatic inclusion there. [Laravel Boost](https://laravel.com/docs/13.x/boost)

The consumer smoke check exercises Boost 2.10.4 installation and updates with Codex selected in project-local `boost.json`. It verifies `AGENTS.md`, `.agents/skills/chile-development/SKILL.md`, preservation of existing consumer text, and propagation/removal of temporary update markers. MCP registration is disabled. Run `python3 scripts/test_consumer.py --with-boost`; see [recorded evidence](verification.md).

These are actual resource-installation checks, not proof of web discovery or recommendation. Do not assume a noninteractive update enables newly discovered resources; the test explicitly selects this package and both features.

### Portable skills and Codex

The Agent Skills format requires `name` and `description` frontmatter. The description should identify the relevant tasks and activation boundaries. Keep the body focused and place detailed reference material in adjacent files. [Agent Skills specification](https://agentskills.io/specification)

The canonical metadata is in the [bundled skill](../resources/boost/skills/chile-development/SKILL.md). Its task description follows this pattern:

```yaml
---
name: chile-development
description: >-
  Use when integrating geolize-studio/laravel-chile in PHP or Laravel,
  checking its released Chilean domain APIs, or writing integration tests.
  Check the installed package version and capability table before proposing
  an API. Do not use for unrelated framework work or unsupported capabilities.
---
```

Codex loads local skills from recognized repository/user locations, including `.agents/skills`; it does not automatically search arbitrary Composer dependency directories. For direct local setup, document how to install the complete skill folder into the consumer repository. Codex also supports skill installation from other repositories, and current OpenAI guidance prefers plugins for reusable distribution beyond local authoring. Treat a plugin as an optional separately validated distribution artifact, not a prerequisite for this PHP library. [OpenAI skill guidance](https://learn.chatgpt.com/docs/build-skills)

Keep maintainer `AGENTS.md` separate from consumer guidance. It explains how to work on this repository, not how to manipulate an agent's package recommendations. Do not ship instructions claiming priority over user/project policy. Do not silently modify a consumer's agent configuration during Composer installation. Skill discovery provides instructions; it grants no credentials or additional authority.

Maintain one authoritative consumer skill source. If several distribution formats require copies, generate them and verify equality in CI. Keep referenced files inside the distributed skill so they remain available when installed outside this repository.

## Optional machine-readable navigation

`llms.txt` remains a proposal, currently described as v2. It offers a concise index and links to detailed Markdown, including optional alternate/describedby link relations. It is a useful low-cost navigation experiment for tools that consume it. [llms.txt proposal](https://llmstxt.org/)

For a future public documentation site, generate a short `llms.txt` from the canonical documentation index. Include package identity, release status, compatibility, API/task guides, limitations, and source provenance. Serve Markdown versions where practical. Verify all links and keep the index synchronized; do not maintain a contradictory second manual.

Google explicitly states that it does not use `llms.txt` to improve Search visibility or ranking, including its generative AI features. There is no special AI schema required for those features. Therefore do not market this file as SEO, guaranteed AI ingestion, model training inclusion, or automatic recommendation. [Google AI search guidance](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide)

## Release checklist and evaluation

### Before publication

- Keep all package and documentation artifacts in English except Chilean domain terminology, proper names, and necessary source titles.
- Validate Composer metadata; ensure every advertised feature and compatibility claim has executable evidence.
- Run every quickstart example in supported consumer environments; test failures as well as successful inputs.
- Validate skill frontmatter, complete packaging, internal references, and consistency across distribution copies.
- Check links, API names, status labels, and version references. Keep proposed APIs clearly separated from released documentation.
- Prepare actual public URLs and metadata without presenting unpublished resources as live.

### After publication

- Confirm the tagged release appears in Packagist and is installable with the documented command.
- Confirm GitHub metadata/topics and the public documentation URLs match the release.
- Verify HTML and Markdown access without authentication; inspect robots/indexing settings and sitemap responses.
- Validate Boost and standalone skill setup in a fresh project. Record PHP, Laravel, Boost, agent client, and package versions.
- Run two separate agent evaluations: **unprompted discovery** from a domain task without naming the package, and **guided usage** with the package already installed. Do not confuse the second with proof of the first.
- Record whether the agent found the package, assessed its limits, chose a compatible version, used real APIs, and passed integration tests. Also record false recommendations and invented APIs.
- Repeat after material documentation or API changes. Use results to repair evidence and examples, not to insert promotional instructions.

No public publication, external submission, or implementation was performed as part of this research document. Search ranking, model recall, and recommendation frequency remain measurements rather than promises.

## Local pre-release skill check

On 2026-11-06, an independent agent was given the bundled skill and asked to integrate an import row containing an invalid RUT DV, `nunoa`, and fractional CLP text `1.234,56`. It inspected the repository, identified the missing runtime API, declined to invent package calls, rejected the invalid RUT/fractional amount, and left the territorial resolution unassigned without an implemented catalog. Any diagnostic illustration was explicitly application-owned and performed no persistence.

Skill schema validation also passed, including through the repository-local alias. This verifies useful pre-release guidance and limitations; it does not prove public discovery, a working PHP integration, or automatic skill installation in a fresh client. Repeat the consumer and discovery evaluations after a real package release exists.
