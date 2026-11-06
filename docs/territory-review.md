# Territorial final review — 2026-11-06

## Decision

**The committed CUT identity and hierarchy are accepted for this release.** The
review found no added, removed, re-parented, or re-coded region, province, or
comuna. The catalog remains an explicitly versioned `subdere-cut-2018-v04`
snapshot; it is not relabelled from other operational datasets because those
datasets disagree on names and typography.

This conclusion applies to the package's code/name/parent hierarchy. The package
does not ship geometry, so it is not a certification of every present or future
intercommunal boundary line.

## Legal and authoritative status

[Decreto N° 1.115 (2018)](https://www.bcn.cl/leychile/navegar?i=1123248)
systematizes the CUT and requires its use by State bodies for the listed
territorial units. Its current Ley Chile record states a single version dated
21 September 2018. The record's text expressly leaves the older CUT decree and
its amendments without effect. The linked [official PDF](https://www.subdere.gov.cl/sites/default/files/documentos/DTO-1115-SEPT_2018.pdf)
is the legal basis recorded by the package.

The [BCN modification index for Decreto N° 1.439](https://www.bcn.cl/leychile/Consulta/nav_vinc_modificacion?clase_vinculacion=MODIFICACION&fechaVigencia=2018-09-06&idNorma=168185)
shows the 2018 systematization as its last listed CUT action. Searches of Ley
Chile, Diario Oficial, SUBDERE, and the Chamber's enacted-law index for
2019–2026 found no later decree changing the CUT system and no enacted law
creating a new region, province, or comuna.

That is a review of the current official indexes and the controlling CUT decree,
not a claim that no proposed bill or local naming convention exists. For example,
area-metropolitan decrees create administrative arrangements but do not create
or re-code comunas; see [Decreto N° 85 (Gran Valparaíso)](https://www.bcn.cl/leychile/navegar?idNorma=1220533)
and [Decreto N° 326 (Gran Concepción)](https://www.bcn.cl/leychile/navegar?i=1206146).

## Record-level cross-check

Two independent current government products were downloaded and compared by CUT
rather than by row position. The downloaded artifacts live only under the ignored
`.source-review/territorial-review/` directory.

| Product | Source and retrieval | Check | Result |
| --- | --- | --- | --- |
| INE Censo 2024 download index | [INE announced the Censo 2024 tools](https://www.ine.gob.cl/sala-de-prensa/prensa/general/noticia/2026/02/09/ine-pone-a-disposici%C3%B3n-nuevas-herramientas-para-el-acceso-y-an%C3%A1lisis-de-la-información-del-censo-2024); the retrieved [official `datos.json`](https://brave-grass-0c5df3c0f.3.azurestaticapps.net/datos.json) is linked directly for reproducibility | Region and comuna codes | 16/16 region CUTs and 346/346 comuna CUTs match exactly. No added or removed code. It does not expose province relationships, so it cannot test them. |
| DPA 2023 shapefile | [IDE Chile announcement](https://www.ide.cl/noticias/2023-09-actualizacion-de-la-cartografia-de-division-politica-administrativa-en/) and the retrieved [official DPA ZIP](https://www.geoportal.cl/geoportal/catalog/download/912598ad-ac92-35f6-8045-098f214bd9c2) | Code, parent, and label fields in `REGIONES_v1.dbf`, `PROVINCIAS_v1.dbf`, and `COMUNAS_v1.dbf` | 16/16 regions and 56/56 provinces have the same CUT and parent relation. 345 comuna polygons have the same CUT and parent relation. `12202` Antártica is absent from that geometry DBF; it is present in the INE 2024 product and package catalog. |

The DPA file's SHA-256 is
`8ac1ce872230b645c3cf04db2d980c1968e78f602144347f4513119dc9b945db`.
The retrieved INE `datos.json` SHA-256 is
`ae9f19505e9ea9ce9dda1b7a4dc02e9e7ef994a027eab35c0c2f08e15a8aec39`.
The locally downloaded Decreto N° 1.115 PDF matches the package manifest's
SHA-256:
`88002cd5a52fbdcda90899611631e315eb3759da78b0bbba148cda605a543377`.

The 2023 DPA release is a joint product of SUBDERE, IGM, DIFROL, and INE; IDE
Chile says it covers the national administrative division and was approved by
DIFROL for circulation. It is strong cross-check evidence, but it is not a
replacement CUT decree. The Censo 2024 visualizer likewise describes its
boundaries as census-operational rather than a pronouncement of official legal
delimitations. See the [visualizer's limitation](https://visualizador.ine.gob.cl/).

## Names: reviewed conflicts and handling

No code or parent relationship changed. Names differ among official operational
products, so a bulk relabel would be unsafe.

- The DPA 2023 product uses `Coihaique` / `Aisén` for province and comuna
  labels where the package's CUT snapshot uses `Coyhaique` / `Aysén`.
- The INE Censo 2024 index has 22 label-string differences from the package:
  most are title casing; it also has `Romerel`, `Hualańe`, `Trehuaco`,
  `Paihuano`, accented `Los Álamos` / `Los Ángeles`, and `Ránquil`.
- Other current State material conflicts too. For example, Servel's territorial
  page uses `Paihuano` and `Ránquil` but `Treguaco`; its records also use
  `Coyhaique` and `Aysén`. [Servel territorial listing](https://www.servel.cl/sistema-electoral/territorios-electorales/)
  The current [BCN communal report for CUT 04105](https://www.bcn.cl/siit/reportescomunales/comunas_v.html?anno=2026&idcom=4105)
  displays `Paihuano`, while legal/financial material still uses `Paiguano`:
  [Decreto N° 491 (2026)](https://www.bcn.cl/leychile/navegar?idNorma=1220061)
  and the historical delimitation text in [DFL 3-18.715](https://www.bcn.cl/leychile/navegar?idNorma=239073).

The complete 22-comuna comparison is below. This is a string-difference table,
not evidence of a CUT or hierarchy difference. The INE region labels also differ
only in capitalization for CUTs `06`, `11`, and `12`.

| CUT | Package CUT snapshot | INE Censo 2024 `datos.json` |
| --- | --- | --- |
| 02203 | San Pedro de Atacama | San Pedro De Atacama |
| 03202 | Diego de Almagro | Diego De Almagro |
| 03302 | Alto del Carmen | Alto Del Carmen |
| 04105 | Paiguano | Paihuano |
| 05109 | Viña del Mar | Viña Del Mar |
| 05201 | Isla de Pascua | Isla De Pascua |
| 06114 | Quinta de Tilcoco | Quinta De Tilcoco |
| 07302 | Hualañé | Hualańe |
| 07306 | Romeral | Romerel |
| 08108 | San Pedro de la Paz | San Pedro De La Paz |
| 08206 | Los Alamos | Los Álamos |
| 08301 | Los Angeles | Los Ángeles |
| 09208 | Purén | Puréń |
| 10204 | Curaco de Vélez | Curaco De Vélez |
| 10306 | San Juan de la Costa | San Juan De La Costa |
| 12201 | Cabo de Hornos | Cabo De Hornos |
| 12402 | Torres del Paine | Torres Del Paine |
| 13203 | San José de Maipo | San José De Maipo |
| 13403 | Calera de Tango | Calera De Tango |
| 13603 | Isla de Maipo | Isla De Maipo |
| 16206 | Ranquil | Ránquil |
| 16207 | Treguaco | Trehuaco |

The package already separates identity (CUT) from name matching and has no
implicit aliases. Retaining the labels from its pinned legal-operational source
is the least surprising policy. Do **not** replace labels merely to follow a
different presentation dataset. A future CUT decree or an official replacement
CUT workbook can justify a snapshot update; then compare all records by CUT and
document every label change.

`Ránquil` already resolves without an alias because the default matching
normalizes diacritics (verified against CUT `16206`). Variants that change
letters or articles, such as `Paihuano` and `La Calera`, need explicit
per-instance aliases until a reviewed source policy says otherwise. They must
not change the canonical record or imply a new CUT.

## Boundary scope

A visual review of rendered page 3 of Decreto N° 1.115 cross-checked the
`04105 Paiguano` and `05502 Calera` rows against the catalog. The decree is a
raster table and its remaining pages were not OCR'd or compared record by
record; this is a targeted visual confirmation, not a second full-table
dataset validation.

Historical laws can alter lines without changing a CUT. For example, [Law
20.721](https://www.bcn.cl/leychile/navegar?idNorma=1058812) changes the Vicuña /
Paihuano boundary. Such acts matter to a geometry or address-containment product,
but this package neither stores polygons nor resolves addresses. The 2023 DPA
cross-check is therefore evidence that the known hierarchy is still represented;
it is not a substitute for a boundary-law audit in a future geometry module.

## Maintenance outcome

The catalog is release-ready for the stated hierarchy/CUT scope. Keep the weekly
source monitor, and make a draft review proposal whenever its approved SUBDERE
landing page or attachments change. A future adoption must require all of:

1. a controlling legal or official CUT source with an effective date;
2. a stable-CUT diff covering added/removed/re-parented/renamed records;
3. a second official cross-check appropriate to the source date;
4. an explicit decision for label-only conflicts; and
5. tests for every affected code and accepted alias.

No runtime catalog, manifest, or source-monitor baseline was modified by this
review.
