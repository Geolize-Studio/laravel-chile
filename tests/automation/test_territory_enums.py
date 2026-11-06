"""Offline checks for reviewed enum symbols and deterministic generated PHP."""

import copy
import importlib.util
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("generate_territory_enums", ROOT / "scripts/generate_territory_enums.py")
enums = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(enums)


class TerritoryEnumsTest(unittest.TestCase):
    def setUp(self):
        self.catalog = enums.read_json(ROOT / "resources/territory/catalog.json")
        self.mapping = enums.read_json(ROOT / "resources/territory/enum-cases.json")

    def test_committed_enums_reproduce_the_accepted_catalog(self):
        self.assertEqual(len(enums.generate(ROOT, check=True)), 3)
        self.assertEqual({table: len(self.mapping[table]) for table in enums.TABLES}, {"regions": 16, "provinces": 56, "comunas": 346})

    def test_record_and_mapping_order_do_not_affect_generated_bytes(self):
        expected = enums.render(self.catalog, self.mapping)
        for table in enums.TABLES:
            self.catalog[table].reverse()
            self.mapping[table] = dict(reversed(list(self.mapping[table].items())))
        self.assertEqual(enums.render(self.catalog, self.mapping), expected)

    def test_label_change_does_not_silently_rename_public_symbols(self):
        self.catalog["comunas"][0]["name"] = "Reviewed new label"
        output = enums.render(self.catalog, self.mapping)["src/Comuna.php"]
        self.assertIn("case Iquique = '01101';", output)
        self.assertIn("self::Iquique => 'Reviewed new label',", output)

    def test_rejects_missing_and_stale_case_map_entries(self):
        for change in (lambda mapping: mapping["comunas"].pop("01101"), lambda mapping: mapping["comunas"].update({"99999": "Unknown"})):
            mapping = copy.deepcopy(self.mapping)
            change(mapping)
            with self.assertRaises(enums.GenerationError):
                enums.render(self.catalog, mapping)

    def test_rejects_invalid_reserved_or_colliding_case_symbols(self):
        for symbol in ("Iquique", "IQUIQUE", "class", "Class", "Bad-name", "Ñuñoa", "1Case", "", None):
            with self.subTest(symbol=symbol):
                mapping = copy.deepcopy(self.mapping)
                mapping["comunas"]["01107"] = symbol
                with self.assertRaises(enums.GenerationError):
                    enums.render(self.catalog, mapping)

    def test_rejects_duplicate_unpadded_and_malformed_catalog_records(self):
        changes = [
            lambda data: data["comunas"].append(data["comunas"][0].copy()),
            lambda data: data["comunas"][0].update(code="1101"),
            lambda data: data["regions"][0].update(code=1),
            lambda data: data["comunas"][0].update(name="Broken\nlabel"),
            lambda data: data["comunas"][0].update(name="Broken\u200blabel"),
            lambda data: data["comunas"][0].update(name=""),
            lambda data: data["comunas"][0].update(unknown="field"),
            lambda data: data.update(dataset_version="bad\nversion"),
            lambda data: data.update(schema_version=2),
        ]
        for change in changes:
            data = copy.deepcopy(self.catalog)
            change(data)
            with self.assertRaises(enums.GenerationError):
                enums.render(data, self.mapping)

    def test_rejects_inconsistent_or_missing_parents(self):
        changes = [
            lambda data: data["provinces"][0].update(region_code="99"),
            lambda data: data["provinces"][0].update(region_code="13"),
            lambda data: data["comunas"][0].update(region_code="13"),
            lambda data: data["comunas"][0].update(region_code=1),
            lambda data: data["comunas"][0].update(province_code="999"),
            lambda data: data["comunas"][0].update(province_code="014"),
            lambda data: data["comunas"][0].update(province_code=[]),
        ]
        for change in changes:
            data = copy.deepcopy(self.catalog)
            change(data)
            with self.assertRaises(enums.GenerationError):
                enums.render(data, self.mapping)

    def test_rejects_parents_without_children(self):
        for table, code, record in (
            ("regions", "99", {"code": "99", "name": "Empty"}),
            ("provinces", "019", {"code": "019", "name": "Empty", "region_code": "01"}),
        ):
            data, mapping = copy.deepcopy(self.catalog), copy.deepcopy(self.mapping)
            data[table].append(record)
            mapping[table][code] = "Empty"
            with self.assertRaises(enums.GenerationError):
                enums.render(data, mapping)

    def fixture_root(self, directory):
        root = Path(directory)
        (root / "resources/territory").mkdir(parents=True)
        (root / "src").mkdir()
        for name in ("catalog.json", "catalog-manifest.json", "enum-cases.json"):
            shutil.copyfile(ROOT / "resources/territory" / name, root / "resources/territory" / name)
        return root

    def test_check_detects_missing_and_modified_files_without_writing(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.fixture_root(directory)
            with self.assertRaises(enums.GenerationError):
                enums.generate(root, check=True)
            self.assertEqual(list((root / "src").iterdir()), [])
            enums.generate(root)
            enums.generate(root, check=True)
            path = root / "src/Region.php"
            path.write_text("edited", encoding="utf-8")
            with self.assertRaises(enums.GenerationError):
                enums.generate(root, check=True)
            self.assertEqual(path.read_text(), "edited")
            enums.generate(root)
            enums.generate(root, check=True)

    def test_rejects_unaccepted_catalog_before_any_generated_file_is_written(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.fixture_root(directory)
            path = root / "resources/territory/catalog.json"
            path.write_bytes(path.read_bytes() + b"\n")
            with self.assertRaisesRegex(enums.GenerationError, "hash"):
                enums.generate(root)
            self.assertEqual(list((root / "src").iterdir()), [])

    def test_rejects_duplicate_json_keys(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "duplicate.json"
            path.write_text('{"schema_version": 1, "schema_version": 1}')
            with self.assertRaisesRegex(enums.GenerationError, "Duplicate JSON key"):
                enums.read_json(path)

    def test_check_command_is_available_without_php_or_external_dependencies(self):
        result = subprocess.run(["python3", str(ROOT / "scripts/generate_territory_enums.py"), "--check"], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("Verified 3 territory enums", result.stdout)


if __name__ == "__main__":
    unittest.main()
