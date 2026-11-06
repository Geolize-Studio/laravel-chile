# Territory and official-source maintenance

**Status: implemented locally, pre-release.** The offline runtime and reviewed SUBDERE baseline contain 16 regiones, 56 provincias and 346 comunas. A reproducible importer builds this specific pinned snapshot. The separate source monitor observes upstream documents and proposes review work; neither component establishes that all subsequent legal changes have been checked. No public release is implied.

The module is designed around the official región → provincia → comuna hierarchy and Código Único Territorial (CUT). Codes are identities; names are labels; aliases are search aids. They must remain separate.

## Source authority

| Source | Role | Limits |
| --- | --- | --- |
| Legal acts published in the Diario Oficial, with authoritative copies available through official services | Establish the legal change, its basis, and effective date | A source-file timestamp cannot substitute for the act or its effective date |
| [SUBDERE CUT publication](https://www.subdere.gov.cl/documentacion/c%C3%B3digos-%C3%BAnicos-territoriales-actualizados-al-06-de-septiembre-2018) and its [CUT spreadsheet](https://www.subdere.gov.cl/sites/default/files/documentos/CUT_2018_v04.xls) | Operational source for codes, names, and parent relationships | The observed publication describes a 2018 baseline; it is not proof that no subsequent act exists |
| [Decreto Exento N° 1.115](https://www.subdere.gov.cl/sites/default/files/documentos/DTO-1115-SEPT_2018.pdf), published 21 September 2018 | Legal basis for the observed coding baseline | Future amendments require a fresh source review |
| [INE DPA material](https://www.ine.gob.cl/docs/default-source/geodatos-abiertos/publicaciones/division-politica-administrativa/dpa-2018/presentaci%C3%B3n.pdf) | Independent technical cross-check | It must not override a governing legal act or authoritative CUT coding; verify the document's own reference date |
| Application aliases and encoding-repair fixtures | Resolve incoming names conservatively | They never change official names, codes, parent relationships, or legal boundaries |

The [final review on 2026-11-06](territory-review.md) checked the controlling legal record and compared codes and hierarchy with INE Censo 2024 and the joint DPA 2023 product. The catalog is accepted for its CUT/hierarchy scope. Official operational sources disagree on some labels, so the pinned CUT labels are retained. This review does not certify boundary geometry. For the automated monitor alone, report **“no change observed in monitored sources”**, never **“Chile's territorial division is unchanged.”**

## Catalog contract

- Store CUT codes as strings and preserve leading zeros. Keep region, province, and comuna records distinct.
- Preserve the official Unicode name and its source. Search-normalized text belongs to a separate index.
- Record package version, dataset version, source retrieval time, declared update date, and legal effective date separately.
- Generate deterministic canonical data from a pinned parser and reviewed source snapshot; changes in row order or spreadsheet formatting alone must not create a semantic data release.
- Include source URLs, SHA-256 hashes, legal instrument, parser version, schema version, record counts, and reuse terms in the accepted dataset manifest.
- Verify data reuse conditions before distribution. The observed SUBDERE page does not explicitly state a reuse license; [INE terms](https://www.ine.gob.cl/terminos-de-uso-y-licencia-de-datos-abiertos) impose separate attribution/share-alike obligations. Do not assume that the code's eventual license covers every upstream asset.
- Do not ship boundary geometry in v1. A hierarchy of names and codes is not a legal boundary map.

## Runtime API

```php
use GeolizeStudio\Chile\Territory;
use GeolizeStudio\Chile\Territory\ResolutionStatus;

$territory = Territory::loadDefault();
$territory->datasetVersion; // 'subdere-cut-2018-v04'
$territory->region('01')->name; // 'Tarapacá'
$territory->province('011')->regionCode; // '01'
$territory->comuna('01101')->name; // 'Iquique'
$territory->comuna('1101'); // null: leading zeros are part of the identity

$result = $territory->resolveComuna('  COMUNA DE ÑUÑOA  ', regionCode: '13');
if ($result->status === ResolutionStatus::Matched) {
    $result->match->code; // '13120'
    $result->match->name; // 'Ñuñoa', unchanged source label
}
$result->original; // exact caller input
$result->method->value; // 'normalized'
$result->transformations;
// ['trim_whitespace', 'lowercase', 'remove_diacritics', 'remove_comuna_prefix']
```

`region(string): ?Region`, `province(string): ?Province` and `comuna(string): ?Comuna` perform exact CUT lookup. Unknown or malformed codes return `null`; they are never padded or reassigned. Pass strings, including leading zeros. Each record is immutable. `Region` exposes `code` and `name`; `Province` adds `regionCode`; `Comuna` adds `provinceCode` and `regionCode`.

`regions(): list<Region>`, `provinces(?string $regionCode = null): list<Province>` and `comunas(?string $regionCode = null, ?string $provinceCode = null): list<Comuna>` return detached arrays sorted by CUT. Filters intersect; unknown or conflicting filters return an empty list.

`loadDefault(array $aliases = []): Territory` reads the bundled JSON and checks its SHA-256 against `catalog-manifest.json`, then validates the complete schema and hierarchy. `fromFile(string $path, array $aliases = []): Territory` accepts a caller-reviewed local snapshot using the same schema and hierarchy checks, without asserting source provenance. Remote URL wrappers and files larger than 2 MB are rejected. Custom snapshots may have different counts but require nonempty tables and children for every region/province. Invalid data or aliases throw `Exceptions\InvalidTerritoryData`; no partially validated instance is returned.

Loading is explicit; each direct call creates an independent instance without global caching. Keep the instance for repeated work. The Laravel provider lazily binds the default catalog as an application singleton. Domain operations need no database, HTTP, credentials or application models. Custom alias instances are created explicitly rather than mutating that singleton.

## Explainable comuna resolution

`resolveComuna(string $input, ?string $regionCode = null): ComunaResolution` returns:

| Property | Contract |
| --- | --- |
| `status` | `ResolutionStatus::Matched`, `Ambiguous` or `NotFound` |
| `match` | One `Comuna` only for `Matched`; otherwise `null` |
| `candidates` | All matching records in CUT order, empty for `NotFound` |
| `original` | Unmodified input, including whitespace |
| `method` | `MatchMethod::Code`, `Exact`, `Normalized`, `Alias`, or `null` when no name matched |
| `transformations` | Ordered names of transformations actually applied to the input |

Precedence is exact five-digit CUT, exact official name, normalized official name, then configured alias. A match at an earlier stage takes precedence over aliases; callers cannot override an official name through an alias. Region context applies to every stage. An unknown region context or invalid UTF-8 input throws `InvalidArgumentException`; a valid unknown name/code returns `NotFound`. An unknown five-digit CUT retains method `Code`.

Normalization applies Unicode NFC, Unicode whitespace trimming/collapsing, lowercase, removal of combining diacritics, and one leading `comuna de ` prefix, in that order. The trace labels are `unicode_nfc`, `trim_whitespace`, `collapse_whitespace`, `lowercase`, `remove_diacritics`, and `remove_comuna_prefix`. Accent-insensitive matching includes `ñ` → `n`; collisions remain ambiguous. Normalization changes only search text, never a source record. Articles are preserved: `La Serena` is not `Serena`, and the source label `Calera` is not silently renamed `La Calera`.

Aliases map a name to a nonempty list of existing comuna CUT strings. Repeated targets are deduplicated; aliases with the same normalized label combine their candidates. No aliases ship implicitly.

```php
$territory = Territory::loadDefault([
    'Port' => ['01101', '13101'],
    'La Calera' => ['05502'],
]);

$territory->resolveComuna('port')->status; // ResolutionStatus::Ambiguous
$territory->resolveComuna('port')->match; // null
$territory->resolveComuna('port', '01')->match->code; // '01101'
$territory->resolveComuna('La Calera')->method->value; // 'alias'
Territory::loadDefault()->resolveComuna('port')->status; // ResolutionStatus::NotFound
```

Encoding repair and fuzzy suggestions are **not implemented**. Mojibake, approximate spellings and unknown/retired codes must be reviewed by the application. There is no confidence score, inferred region, history rewrite or replacement identity.

## Rebuilding the reviewed baseline

The committed catalog preserves the operational workbook's exact labels, including `Los Angeles`, `Los Alamos` and `Calera`. It does not silently standardize their spelling. Source attribution and the original-document license status are in [the manifest](../resources/territory/catalog-manifest.json). The original XLS/PDF documents are not bundled and the package license does not relicense them. The extracted records are factual codes, labels and parent relationships from the official publication; no third-party dataset is used. The decree supplies the coding basis, but its raster tables have not been independently machine-compared with the workbook. The completed [territorial review](territory-review.md) accepts the CUT/hierarchy scope and records the limits of the decree-page comparison.

The build-only tool is **LibreOffice 25.8.2.2**, build `d401f2107ccab8f924a8e2df40f573aab7605b6f`; Python uses only its standard library. Neither LibreOffice nor Python is a PHP runtime dependency. The importer checks the converter version and the approved workbook SHA-256 before opening the file. It uses a temporary user profile with macros and active content disabled and Calc link updates set to “never”; the setting is defined in the [LibreOffice Calc schema](https://github.com/LibreOffice/core/blob/master/officecfg/registry/schema/org/openoffice/Office/Calc.xcs). It does not alter a contributor's existing profile.

Download the reviewed source separately using the approved official URL, retain its actual retrieval date, then generate a **new candidate directory**:

```sh
python3 scripts/build_territory_catalog.py /path/to/CUT_2018_v04.xls \
  --expected-sha256 d1b7fc3abb93cea3d115861579297d8bbdc9a7c762c958e646cd01b804df1ea0 \
  --retrieved-on 2026-11-06 \
  --output /tmp/chile-cut-candidate \
  --libreoffice /path/to/soffice
```

The date above documents the bundled snapshot's retrieval; use the actual date of your own retrieval. The importer never downloads data, accepts a different source hash, overwrites an existing output directory, updates accepted runtime files, creates PRs or releases data. On conversion/validation failure, no candidate directory is published. A complete validated candidate is atomically renamed into place.

The CSV schema, code widths, duplicate identities, Unicode labels, consistent names and parent prefixes must all pass, with exactly 16/56/346 records for this baseline. Records are sorted by CUT and emitted as deterministic UTF-8 JSON. Reordering source rows leaves catalog bytes unchanged. With the same source, pinned converter and retrieval date, both output files reproduce exactly; changing only the retrieval date changes provenance, not catalog content. A converter upgrade requires a reviewed pin change and record comparison.

The catalog hash is `4b19a360f5e0f54033a5a5fc37f8d9ed7844a29e9c696d70754607a97dcc99a5`. The manifest separates the source reference date (`2018-09-06`), decree publication date (`2018-09-21`) and retrieval date (`2026-11-06`); `effective_on` is deliberately `null`. None is silently substituted for legal effect. The package version lives in release metadata, separately from the dataset identifier.

```sh
vendor/bin/pest tests/Unit/TerritoryTest.php
python3 -m unittest discover -s tests/automation -p test_territory_catalog.py -v
```

Runtime tests cover every bundled code/name, hierarchy integrity, immutable records, aliases, ambiguity, normalization traces and malformed custom snapshots. Offline importer tests cover deterministic ordering, hash/version drift, schema corruption, converter isolation and atomic candidate publication. Actual LibreOffice conversion is a maintainer rebuild check, not a requirement for ordinary test runs.

## Automated source review

The first automation layer tracks **source observations**, separately from the **accepted runtime dataset**:

```text
Approved official URLs
        ↓ bounded HTTPS download
Format and provenance checks
        ↓ deterministic link/hash comparison
One source-review branch and PR
        ↓ human interpretation + reviewed importer changes
Validated dataset proposal
        ↓ reviewed merge and separate release
Versioned offline package catalog
```

The initial monitor uses only Python's standard library. It watches approved document URLs and the official landing page's relevant artifact links. It checks download limits, redirects, file signatures, and deterministic observation metadata. It does not execute or parse spreadsheet macros, interpret legal PDFs, or claim that matching magic bytes validate the content's territorial meaning.

A failed download, unexpected HTML response, missing source, or invalid format must fail the check without replacing the accepted observation. A source change opens review work; it never means an empty or deleted territorial catalog.

The GitHub Actions design runs weekly and through manual dispatch. Inspection uses read-only repository permissions. A separate proposal job receives only the permissions needed to update its dedicated branch and PR. The proposal lists changed source URLs, sizes, hashes, and the fact that territorial changes have not yet been interpreted. Identical observations should not create repeat commits or PRs.

The workflow is a local scaffold until it is committed to a real repository, repository permissions are configured, and an end-to-end test succeeds. No GitHub PR has been opened from this workspace.

Local verification covers 36 cases, including truncated downloads, schema drift, redirects, pagination, duplicate observations, ownership, and upstream rollback. Successful observations are reconciled even when they match the accepted baseline, so an obsolete open proposal can be handled. Actual remote PR creation and closure still require an end-to-end repository test. Local check commands are documented in [CONTRIBUTING.md](../CONTRIBUTING.md).

## Semantic update gates

The current importer deliberately accepts only the reviewed baseline. Adopting a future source requires extending it under review, comparing records by stable CUT rather than source row position, and producing a record-level diff. Each future dataset update must pass:

1. Expected schema, code syntax, unique codes, and complete parent relationships.
2. Parent-prefix consistency where defined by the source coding scheme.
3. No accidental control characters, replacement characters, lost leading zeros, or duplicate generated identities.
4. A record-level report of added/removed codes, renamed labels, re-parenting, and alias changes.
5. Provenance, reuse terms, legal basis, and effective-date review for substantive changes.
6. Independent cross-checks against suitable official material of a compatible date; disagreement blocks adoption rather than triggering automatic source selection.
7. Tests for affected examples, negative matches, and downstream compatibility.

Changes to codes, parent relationships, or code reuse require explicit review. A new source filename with identical semantic records is provenance maintenance, not a new territorial release. An unknown legal basis produces a review report and leaves runtime data unchanged.

## Operational limits

GitHub schedules are best effort, can be delayed, and can be disabled after prolonged inactivity in public repositories. Keep manual dispatch and visible workflow history; do not manufacture commits to keep a schedule alive. [GitHub schedule behavior](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule)

With the default `GITHUB_TOKEN`, PR events created by automation currently produce approval-required workflow runs. A repository-scoped GitHub App installation token can enable normal downstream checks without that manual start; it needs deliberate repository setup. The organization must also permit Actions to create PRs. No token setup is implied by the local files. [GitHub token behavior](https://docs.github.com/en/actions/concepts/security/github_token)

The current ownership preflight trusts `github-actions[bot]`. A different App bot also needs an explicit, tested trusted-actor policy; supplying its token alone is not a supported switch. The workflow pins its actions to reviewed full commit SHAs. Dependabot is configured to propose weekly GitHub Actions updates for review, with no automatic merge.

Monitoring known sources does not discover every future legal act or moved publication automatically. Review source coverage periodically, especially when downloads fail, an official page changes its links, or an independent official source disagrees. Automation should reduce routine maintenance while making uncertainty visible.

## Repository automation status

The public repository retains read-only default workflow permissions. The organization permits repository opt-in for Actions-created PRs, and this repository enables it. Only the proposal job requests `contents: write` and `pull-requests: write`; it creates drafts without approval, automatic merge, or release. GitHub exposes creation and approval through one repository setting, so the workflow itself deliberately performs no approval operation.

The manual `Verify draft PR permissions` workflow uses the same pinned PR action and ephemeral `GITHUB_TOKEN` with a synthetic single-file fixture. It verifies the bot identity, draft state, base, and changed path, then closes the PR and removes its run-specific branch. It never modifies territorial data. This tests permission and lifecycle plumbing independently of whether official sources changed; a successful unchanged-source observation is not evidence that PR creation ran.

Organization policy changes can propagate to repositories that inherit them. Preserve and verify other repositories' existing settings when enabling an exception. New repositories inherit the organization's creation/approval setting and should explicitly disable it unless required. Do not replace this arrangement with a broad personal token.

The initial permission exercise [passed on 2026-11-06](https://github.com/Geolize-Studio/laravel-chile/actions/runs/42308727712): draft [PR #1](https://github.com/Geolize-Studio/laravel-chile/pull/1) was closed without merging and its temporary branch was removed. The [official-source run](https://github.com/Geolize-Studio/laravel-chile/actions/runs/42308731016) also passed with no source changes.
