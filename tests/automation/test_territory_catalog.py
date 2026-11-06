"""Offline acceptance tests for the reviewed CUT importer and bundled catalog."""

import csv
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
from urllib.parse import unquote, urlparse
import xml.etree.ElementTree as ET


ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("build_territory_catalog", ROOT / "scripts/build_territory_catalog.py")
catalog = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(catalog)


class TerritoryCatalogTest(unittest.TestCase):
    def setUp(self):
        self.catalog_bytes = (ROOT / "resources/territory/catalog.json").read_bytes()
        self.data = json.loads(self.catalog_bytes)
        self.manifest = json.loads((ROOT / "resources/territory/catalog-manifest.json").read_bytes())
        regions = {row["code"]: row for row in self.data["regions"]}
        provinces = {row["code"]: row for row in self.data["provinces"]}
        self.rows = [catalog.HEADERS.copy()] + [
            [row["region_code"], regions[row["region_code"]]["name"], "TEST", row["province_code"], provinces[row["province_code"]]["name"], row["code"], row["name"]]
            for row in self.data["comunas"]
        ]

    def csv_text(self, rows=None):
        buffer = io.StringIO()
        csv.writer(buffer).writerows(self.rows if rows is None else rows)
        return buffer.getvalue()

    def test_manifest_matches_complete_canonical_catalog(self):
        self.assertEqual(catalog.canonical_json(self.data), self.catalog_bytes)
        self.assertEqual(hashlib.sha256(self.catalog_bytes).hexdigest(), self.manifest["catalog_sha256"])
        self.assertEqual(catalog.SOURCE_SHA256, self.manifest["source"]["sha256"])
        self.assertEqual(catalog.LIBREOFFICE_VERSION, self.manifest["generator"]["converter"])
        self.assertEqual(catalog.COUNTS, {key: len(self.data[key]) for key in catalog.COUNTS})
        self.assertEqual(self.data, catalog.parse_csv(self.csv_text()))
        self.assertIsNone(self.manifest["legal_basis"]["effective_on"])

    def test_source_row_order_does_not_change_output(self):
        reversed_rows = [self.rows[0], *reversed(self.rows[1:])]
        self.assertEqual(catalog.canonical_json(catalog.parse_csv(self.csv_text(reversed_rows))), self.catalog_bytes)

    def test_invalid_csv_fails_without_repair(self):
        mutations = {
            "headers": lambda rows: rows[0].__setitem__(0, "Region"),
            "columns": lambda rows: rows[1].pop(),
            "missing row": lambda rows: rows.pop(),
            "duplicate code": lambda rows: rows.append(rows[1].copy()),
            "unpadded CUT": lambda rows: rows[1].__setitem__(5, "1101"),
            "unicode digit": lambda rows: rows[1].__setitem__(5, "０1101"),
            "bad parent": lambda rows: rows[1].__setitem__(5, "13101"),
            "region conflict": lambda rows: rows[1].__setitem__(1, "Different"),
            "province conflict": lambda rows: rows[1].__setitem__(4, "Different"),
            "abbreviation conflict": lambda rows: rows[1].__setitem__(2, "DIFFERENT"),
            "blank name": lambda rows: rows[1].__setitem__(6, ""),
            "padded name": lambda rows: rows[1].__setitem__(6, " Iquique"),
            "control": lambda rows: rows[1].__setitem__(6, "Iqui\x00que"),
            "invisible": lambda rows: rows[1].__setitem__(6, "Iqui\u200bque"),
            "replacement": lambda rows: rows[1].__setitem__(6, "Iqui\ufffdque"),
        }
        for name, mutate in mutations.items():
            with self.subTest(name=name):
                rows = [row.copy() for row in self.rows]
                mutate(rows)
                with self.assertRaises(catalog.CatalogError):
                    catalog.parse_csv(self.csv_text(rows))

    def test_rejects_unreviewed_hash_before_conversion(self):
        with patch.object(catalog, "convert_xls") as convert:
            with self.assertRaises(catalog.CatalogError):
                catalog.build(Path("does-not-exist"), "0" * 64, "2026-11-06")
            convert.assert_not_called()

    def test_rejects_source_size_or_hash_before_conversion(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "source.xls"
            for contents in (b"bad source", b"x" * 69_120):
                source.write_bytes(contents)
                with patch.object(catalog, "convert_xls") as convert:
                    with self.assertRaises(catalog.CatalogError):
                        catalog.build(source, catalog.SOURCE_SHA256, "2026-11-06")
                    convert.assert_not_called()

    def test_rejects_invalid_retrieval_date(self):
        for date in ("2026-11-31", "20261106", "today", "2026-1-1"):
            with self.subTest(date=date), self.assertRaises(catalog.CatalogError):
                catalog.build(Path("unused"), catalog.SOURCE_SHA256, date)

    def test_build_is_deterministic_and_separates_retrieval_from_legal_effect(self):
        data = b"x" * 69_120
        source_hash = hashlib.sha256(data).hexdigest()
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "source.xls"
            source.write_bytes(data)
            with patch.object(catalog, "SOURCE_SHA256", source_hash), patch.object(catalog, "convert_xls", return_value=self.csv_text()):
                first = catalog.build(source, source_hash, "2026-11-06")
                second = catalog.build(source, source_hash, "2026-11-06")
                later = catalog.build(source, source_hash, "2026-11-07")
            self.assertEqual(first, second)
            self.assertEqual(first[0], later[0])
            self.assertNotEqual(first[1], later[1])
            self.assertIsNone(json.loads(first[1])["legal_basis"]["effective_on"])

    def test_converter_version_drift_fails_before_opening_source(self):
        with patch.object(catalog.subprocess, "run", return_value=subprocess.CompletedProcess([], 0, "LibreOffice unknown\n")) as run:
            with self.assertRaises(catalog.CatalogError):
                catalog.convert_xls(b"source", "soffice")
            self.assertEqual(1, run.call_count)

    def test_converter_isolates_profile_and_disables_macros_active_content_and_links(self):
        def run(args, **kwargs):
            if "--version" in args:
                return subprocess.CompletedProcess(args, 0, catalog.LIBREOFFICE_VERSION + "\n")
            self.assertIn("--headless", args)
            self.assertEqual(catalog.CSV_FILTER, args[args.index("--convert-to") + 1])
            profile_url = next(arg.split("=", 1)[1] for arg in args if arg.startswith("-env:UserInstallation="))
            profile = Path(unquote(urlparse(profile_url).path))
            tree = ET.parse(profile / "user/registrymodifications.xcu")
            properties = {p.attrib["{http://openoffice.org/2001/registry}name"]: p.findtext("value") for p in tree.iter("prop")}
            self.assertEqual("true", properties["DisableMacrosExecution"])
            self.assertEqual("true", properties["DisableActiveContent"])
            self.assertEqual("3", properties["MacroSecurityLevel"])
            self.assertEqual("1", properties["Link"])
            source = Path(args[-1])
            self.assertEqual(b"reviewed source", source.read_bytes())
            source.with_suffix(".csv").write_text(self.csv_text(), encoding="utf-8")
            return subprocess.CompletedProcess(args, 0, "converted")

        with patch.object(catalog.subprocess, "run", side_effect=run):
            self.assertEqual(self.csv_text().replace("\r\n", "\n"), catalog.convert_xls(b"reviewed source", "soffice"))

    def test_missing_conversion_output_fails(self):
        results = [subprocess.CompletedProcess([], 0, catalog.LIBREOFFICE_VERSION), subprocess.CompletedProcess([], 0, "nothing")]
        with patch.object(catalog.subprocess, "run", side_effect=results), self.assertRaises(catalog.CatalogError):
            catalog.convert_xls(b"source", "soffice")

    def test_publishes_complete_new_candidate_and_never_overwrites_existing_output(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "candidate"
            catalog.write_new_output(output, b"catalog", b"manifest")
            self.assertEqual(b"catalog", (output / "catalog.json").read_bytes())
            self.assertEqual(b"manifest", (output / "catalog-manifest.json").read_bytes())
            with self.assertRaises(catalog.CatalogError):
                catalog.write_new_output(output, b"changed", b"changed")
            self.assertEqual(b"catalog", (output / "catalog.json").read_bytes())

    def test_write_failure_leaves_no_partial_candidate(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "candidate"
            with patch.object(Path, "write_bytes", side_effect=OSError("disk full")), self.assertRaises(OSError):
                catalog.write_new_output(output, b"catalog", b"manifest")
            self.assertFalse(output.exists())
            self.assertEqual([], list(Path(directory).iterdir()))


if __name__ == "__main__":
    unittest.main()
