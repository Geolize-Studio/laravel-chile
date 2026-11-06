#!/usr/bin/env python3
"""Build the reviewed SUBDERE CUT baseline. Never downloads or adopts new sources."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import hashlib
import io
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import unicodedata

SOURCE_SHA256 = "d1b7fc3abb93cea3d115861579297d8bbdc9a7c762c958e646cd01b804df1ea0"
SOURCE_URL = "https://www.subdere.gov.cl/sites/default/files/documentos/CUT_2018_v04.xls"
LEGAL_URL = "https://www.subdere.gov.cl/sites/default/files/documentos/DTO-1115-SEPT_2018.pdf"
LIBREOFFICE_VERSION = "LibreOffice 25.8.2.2 d401f2107ccab8f924a8e2df40f573aab7605b6f"
DATASET_VERSION = "subdere-cut-2018-v04"
HEADERS = ["Código Región", "Nombre Región", "Abreviatura Región", "Código Provincia", "Nombre Provincia", "Código Comuna 2018", "Nombre Comuna"]
COUNTS = {"regions": 16, "provinces": 56, "comunas": 346}
CSV_FILTER = "csv:Text - txt - csv (StarCalc):44,34,76,1,,0,false,true,true,false,false,0"


class CatalogError(ValueError):
    """A source or generated catalog failed a required acceptance check."""


def canonical_json(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, indent=2) + "\n").encode("utf-8")


def validate_name(value: str) -> None:
    if not value or value != value.strip() or "\ufffd" in value or any(unicodedata.category(c).startswith("C") for c in value):
        raise CatalogError("Names must be nonempty, unpadded Unicode without control or replacement characters.")


def parse_csv(text: str) -> dict:
    try:
        rows = iter(list(csv.reader(io.StringIO(text), strict=True)))
    except csv.Error as error:
        raise CatalogError("The converted source is not valid CSV.") from error
    if next(rows, None) != HEADERS:
        raise CatalogError("Unexpected SUBDERE column schema.")
    regions: dict[str, dict] = {}
    provinces: dict[str, dict] = {}
    comunas: dict[str, dict] = {}
    abbreviations: dict[str, str] = {}
    for line, row in enumerate(rows, start=2):
        if len(row) != 7:
            raise CatalogError(f"Expected seven columns on row {line}.")
        region, region_name, abbreviation, province, province_name, comuna, comuna_name = row
        if not re.fullmatch(r"[0-9]{2}", region) or not re.fullmatch(r"[0-9]{3}", province) or not re.fullmatch(r"[0-9]{5}", comuna):
            raise CatalogError(f"Invalid string CUT on row {line}.")
        if not province.startswith(region) or not comuna.startswith(province):
            raise CatalogError(f"Inconsistent CUT parent prefixes on row {line}.")
        for name in (region_name, province_name, comuna_name, abbreviation):
            validate_name(name)
        region_record = {"code": region, "name": region_name}
        province_record = {"code": province, "name": province_name, "region_code": region}
        if region in regions and regions[region] != region_record:
            raise CatalogError(f"Conflicting region records on row {line}.")
        if province in provinces and provinces[province] != province_record:
            raise CatalogError(f"Conflicting province records on row {line}.")
        if region in abbreviations and abbreviations[region] != abbreviation:
            raise CatalogError(f"Conflicting region abbreviations on row {line}.")
        if comuna in comunas:
            raise CatalogError(f"Duplicate comuna CUT on row {line}.")
        regions[region] = region_record
        provinces[province] = province_record
        abbreviations[region] = abbreviation
        comunas[comuna] = {"code": comuna, "name": comuna_name, "province_code": province, "region_code": region}
    tables = {"regions": regions, "provinces": provinces, "comunas": comunas}
    if {key: len(table) for key, table in tables.items()} != COUNTS:
        raise CatalogError("Record counts differ from the reviewed 16/56/346 baseline.")
    return {"schema_version": 1, "dataset_version": DATASET_VERSION, **{key: [table[code] for code in sorted(table)] for key, table in tables.items()}}


def convert_xls(source_bytes: bytes, executable: str) -> str:
    version = subprocess.run([executable, "--version"], check=True, capture_output=True, text=True, timeout=30).stdout.strip()
    if version != LIBREOFFICE_VERSION:
        raise CatalogError(f"Build requires {LIBREOFFICE_VERSION}; found {version}.")
    with tempfile.TemporaryDirectory(prefix="chile-cut-") as directory:
        root = Path(directory)
        profile = root / "profile"
        user = profile / "user"
        user.mkdir(parents=True)
        # Isolated profile: never reuse the contributor's settings or permit macros/link updates.
        (user / "registrymodifications.xcu").write_text('''<?xml version="1.0" encoding="UTF-8"?>
<oor:items xmlns:oor="http://openoffice.org/2001/registry">
<item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop><prop oor:name="DisableMacrosExecution" oor:op="fuse"><value>true</value></prop><prop oor:name="DisableActiveContent" oor:op="fuse"><value>true</value></prop></item>
<item oor:path="/org.openoffice.Office.Calc/Content/Update"><prop oor:name="Link" oor:op="fuse"><value>1</value></prop></item>
</oor:items>
''', encoding="utf-8")
        source = root / "source.xls"
        source.write_bytes(source_bytes)
        subprocess.run([executable, "-env:UserInstallation=" + profile.as_uri(), "--headless", "--convert-to", CSV_FILTER, "--outdir", str(root), str(source)], check=True, capture_output=True, text=True, timeout=90)
        output = root / "source.csv"
        if not output.is_file() or output.stat().st_size > 1_000_000:
            raise CatalogError("LibreOffice did not produce a bounded CSV export.")
        return output.read_text(encoding="utf-8", errors="strict")


def build(source: Path, expected_sha256: str, retrieved_on: str, executable: str = "soffice") -> tuple[bytes, bytes]:
    try:
        if dt.date.fromisoformat(retrieved_on).isoformat() != retrieved_on:
            raise ValueError
    except ValueError as error:
        raise CatalogError("Retrieval date must use YYYY-MM-DD.") from error
    if expected_sha256 != SOURCE_SHA256:
        raise CatalogError("Only the explicitly reviewed source hash is accepted; new sources require code and legal review.")
    if source.stat().st_size != 69_120:
        raise CatalogError("Source size differs from the reviewed workbook.")
    data = source.read_bytes()
    if hashlib.sha256(data).hexdigest() != expected_sha256:
        raise CatalogError("Source SHA-256 does not match the approved snapshot.")
    catalog = parse_csv(convert_xls(data, executable))
    encoded = canonical_json(catalog)
    manifest = {
        "schema_version": 1,
        "dataset_version": DATASET_VERSION,
        "catalog_sha256": hashlib.sha256(encoded).hexdigest(),
        "counts": COUNTS,
        "source": {"url": SOURCE_URL, "sha256": SOURCE_SHA256, "bytes": len(data), "retrieved_on": retrieved_on, "declared_reference_date": "2018-09-06"},
        "legal_basis": {"name": "Decreto Exento N° 1.115", "url": LEGAL_URL, "published_on": "2018-09-21", "effective_on": None, "note": "Publication and retrieval dates are not asserted as legal effective dates. Later legal changes have not been exhaustively reviewed."},
        "generator": {"script": "scripts/build_territory_catalog.py", "version": 1, "converter": LIBREOFFICE_VERSION, "csv_filter": CSV_FILTER},
        "reuse": {"scope": "Factual CUT codes, official labels and parent relationships extracted from the SUBDERE operational workbook, with the linked legal act as the coding basis.", "original_document_license": "No explicit reuse license identified on the SUBDERE publication. The original workbook and decree are not bundled; the package code license does not relicense those source documents.", "attribution": "Subsecretaría de Desarrollo Regional y Administrativo (SUBDERE), Chile, CUT_2018_v04."},
    }
    return encoded, canonical_json(manifest)


def write_new_output(directory: Path, catalog: bytes, manifest: bytes) -> None:
    """Publish a complete new candidate directory; never overwrite accepted data."""
    if directory.exists():
        raise CatalogError("Output directory already exists; generate a new candidate and review its diff.")
    directory.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix=".chile-catalog-", dir=directory.parent) as temporary:
        stage = Path(temporary) / "candidate"
        stage.mkdir()
        (stage / "catalog.json").write_bytes(catalog)
        (stage / "catalog-manifest.json").write_bytes(manifest)
        os.rename(stage, directory)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source", type=Path, help="Local, reviewed CUT_2018_v04.xls")
    parser.add_argument("--expected-sha256", required=True)
    parser.add_argument("--retrieved-on", required=True, help="Actual source retrieval date, YYYY-MM-DD")
    parser.add_argument("--output", required=True, type=Path, help="New candidate directory; existing directories are rejected")
    parser.add_argument("--libreoffice", default="soffice")
    args = parser.parse_args()
    try:
        catalog, manifest = build(args.source, args.expected_sha256, args.retrieved_on, args.libreoffice)
        write_new_output(args.output, catalog, manifest)
    except (CatalogError, OSError, subprocess.SubprocessError, UnicodeError, csv.Error) as error:
        parser.exit(1, f"Catalog build failed: {error}\n")
    print(f"Created reviewed baseline candidate in {args.output} (16 regions, 56 provinces, 346 comunas).")


if __name__ == "__main__":
    main()
