#!/usr/bin/env python3
"""Generate native PHP enums from the accepted CUT catalog and reviewed case map."""

import argparse
import hashlib
import json
from pathlib import Path
import re
import sys
import unicodedata

ROOT = Path(__file__).resolve().parents[1]
TABLES = {"regions": ("Region", 2), "provinces": ("Province", 3), "comunas": ("Comuna", 5)}
# Enum symbols are public API. Do not infer or rename them from source labels.
RESERVED = set("class trait interface enum extends implements function const public protected private static abstract final readonly var new clone instanceof self parent true false null case default match switch return throw yield list array callable iterable object string int float bool void never mixed resource __class__ __trait__ __method__ __function__ __namespace__ __file__ __dir__ __line__ __compiler_halt_offset__".split())


class GenerationError(ValueError):
    """The accepted catalog and reviewed case map cannot generate valid enums."""


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise GenerationError(f"Duplicate JSON key: {key}")
        result[key] = value
    return result


def read_json(path):
    return json.loads(path.read_bytes(), object_pairs_hook=unique_object)


def validate(catalog, mapping):
    if not isinstance(catalog, dict) or not isinstance(mapping, dict):
        raise GenerationError("Catalog and enum case map must be JSON objects.")
    if catalog.get("schema_version") != 1 or mapping.get("schema_version") != 1:
        raise GenerationError("Unsupported catalog or enum case map schema.")
    if set(mapping) != {"schema_version", *TABLES}:
        raise GenerationError("Enum case map must contain exactly the three territory tables.")
    if not isinstance(catalog.get("dataset_version"), str) or not re.fullmatch(r"[a-zA-Z0-9._-]+", catalog["dataset_version"]):
        raise GenerationError("Invalid dataset version.")
    indexes = {}
    for table, (_, width) in TABLES.items():
        records, cases = catalog.get(table), mapping.get(table)
        if not isinstance(records, list) or not records or not isinstance(cases, dict):
            raise GenerationError(f"Invalid {table} records or case map.")
        index = {}
        expected = {"code", "name"}
        if table != "regions":
            expected.add("region_code")
        if table == "comunas":
            expected.add("province_code")
        for row in records:
            if not isinstance(row, dict) or set(row) != expected:
                raise GenerationError(f"Invalid {table} record schema.")
            code, name = row["code"], row["name"]
            if not isinstance(code, str) or not re.fullmatch(rf"[0-9]{{{width}}}", code) or code in index:
                raise GenerationError(f"Invalid or duplicate {table} CUT: {code}")
            if not isinstance(name, str) or not name or name.strip() != name or any(unicodedata.category(c).startswith("C") or c == "\ufffd" for c in name):
                raise GenerationError(f"Invalid {table} label for {code}.")
            for field, parent_width in (("region_code", 2), ("province_code", 3)):
                if field in row and (not isinstance(row[field], str) or not re.fullmatch(rf"[0-9]{{{parent_width}}}", row[field])):
                    raise GenerationError(f"Invalid parent CUT for {table} {code}.")
            index[code] = row
        if set(cases) != set(index):
            raise GenerationError(f"Review the {table} case map: its CUT codes must exactly match the accepted catalog.")
        seen = set()
        for code, symbol in cases.items():
            if not isinstance(symbol, str) or not re.fullmatch(r"[A-Z][A-Za-z0-9]*", symbol) or symbol.lower() in RESERVED:
                raise GenerationError(f"Invalid PHP enum case for {table} CUT {code}.")
            if symbol.lower() in seen:
                raise GenerationError(f"Colliding PHP enum case in {table}: {symbol}")
            seen.add(symbol.lower())
        indexes[table] = index
    for code, row in indexes["provinces"].items():
        if row["region_code"] not in indexes["regions"] or not code.startswith(row["region_code"]):
            raise GenerationError(f"Invalid province parent: {code}")
    for code, row in indexes["comunas"].items():
        parent = indexes["provinces"].get(row["province_code"])
        if parent is None or parent["region_code"] != row["region_code"] or not code.startswith(row["province_code"]):
            raise GenerationError(f"Invalid comuna parent: {code}")
    if {r["region_code"] for r in indexes["provinces"].values()} != set(indexes["regions"]):
        raise GenerationError("Every region must have a province.")
    if {r["province_code"] for r in indexes["comunas"].values()} != set(indexes["provinces"]):
        raise GenerationError("Every province must have a comuna.")
    return indexes


def php_string(value):
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def match_method(name, return_type, rows, cases, expression):
    entries = "\n".join(f"            self::{cases[row['code']]} => {expression(row)}," for row in rows)
    return f"""    public function {name}(): {return_type}
    {{
        return match ($this) {{
{entries}
        }};
    }}
"""


def render(catalog, mapping):
    indexes = validate(catalog, mapping)
    output = {}
    for table, (enum, _) in TABLES.items():
        rows = [indexes[table][code] for code in sorted(indexes[table])]
        cases = mapping[table]
        declarations = "\n".join(f"    case {cases[row['code']]} = {php_string(row['code'])};" for row in rows)
        source = f"""<?php

declare(strict_types=1);

namespace GeolizeStudio\\Chile;

/**
 * Generated from the accepted {catalog['dataset_version']} catalog.
 * Regenerate with python3 scripts/generate_territory_enums.py; do not edit manually.
 */
enum {enum}: string
{{
{declarations}

    public function code(): string
    {{
        return $this->value;
    }}

"""
        source += match_method("label", "string", rows, cases, lambda row: php_string(row["name"]))
        if table != "regions":
            source += "\n" + match_method("region", "Region", rows, cases, lambda row: "Region::" + mapping["regions"][row["region_code"]])
        if table == "comunas":
            source += "\n" + match_method("province", "Province", rows, cases, lambda row: "Province::" + mapping["provinces"][row["province_code"]])
        if table == "regions":
            source += """
    /** @return list<Province> */
    public function provinces(): array
    {
        return array_values(array_filter(Province::cases(), fn (Province $province): bool => $province->region() === $this));
    }

    /** @return list<Comuna> */
    public function comunas(): array
    {
        return array_values(array_filter(Comuna::cases(), fn (Comuna $comuna): bool => $comuna->region() === $this));
    }
"""
        elif table == "provinces":
            source += """
    /** @return list<Comuna> */
    public function comunas(): array
    {
        return array_values(array_filter(Comuna::cases(), fn (Comuna $comuna): bool => $comuna->province() === $this));
    }
"""
        parameters = "" if table == "regions" else "?Region $region = null"
        if table == "comunas":
            parameters += ", ?Province $province = null"
        source += f"""
    /** @return array<int|string, string> */
    public static function options({parameters}): array
    {{
        $options = [];

        foreach (self::cases() as $case) {{
"""
        if table != "regions":
            condition = "$region !== null && $case->region() !== $region"
            if table == "comunas":
                condition = f"({condition}) || ($province !== null && $case->province() !== $province)"
            source += f"""            if ({condition}) {{
                continue;
            }}

"""
        source += """            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
"""
        output[f"src/{enum}.php"] = source
    return output


def generate(root, check=False):
    catalog_path = root / "resources/territory/catalog.json"
    manifest = read_json(root / "resources/territory/catalog-manifest.json")
    if hashlib.sha256(catalog_path.read_bytes()).hexdigest() != manifest.get("catalog_sha256"):
        raise GenerationError("Catalog hash does not match the accepted manifest.")
    output = render(read_json(catalog_path), read_json(root / "resources/territory/enum-cases.json"))
    stale = [path for path, source in output.items() if not (root / path).is_file() or (root / path).read_bytes() != source.encode("utf-8")]
    if check and stale:
        raise GenerationError("Generated territory enums are stale: " + ", ".join(stale))
    if not check:
        for path, source in output.items():
            (root / path).write_text(source, encoding="utf-8")
    return list(output)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="Verify generated files without changing them.")
    args = parser.parse_args()
    try:
        paths = generate(ROOT, check=args.check)
    except (GenerationError, OSError, ValueError, TypeError, KeyError) as error:
        print(f"Territory enum generation failed: {error}", file=sys.stderr)
        return 1
    print(("Verified" if args.check else "Generated") + f" {len(paths)} territory enums.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
