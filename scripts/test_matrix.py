#!/usr/bin/env python3
"""Run the PHP/Laravel/dependency matrix using real, explicitly selected PHP binaries."""

import argparse
from concurrent.futures import ThreadPoolExecutor, as_completed
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[1]
FRAMEWORKS = {"12": ("^12.24", "^10.5"), "13": ("^13.0", "^11.0")}
FOLDERS = ("src", "tests", "resources", "examples", "scripts", "benchmarks")
FILES = ("composer.json", "composer.lock", "phpunit.xml.dist", "phpstan.neon.dist", "pint.json")


def snapshot_hash() -> str:
    paths = [ROOT / name for name in FILES]
    paths += [path for name in FOLDERS for path in (ROOT / name).rglob("*")
              if path.is_file() and "__pycache__" not in path.parts and path.suffix != ".pyc"]
    digest = hashlib.sha256()
    for path in sorted(paths):
        digest.update(str(path.relative_to(ROOT)).encode() + b"\0" + path.read_bytes() + b"\0")
    return digest.hexdigest()


def execute_case(php: Path, version: str, framework: str, dependencies: str, output: Path, composer: Path) -> dict:
    name = f"php-{version}-laravel-{framework}-{dependencies}"
    target = output / name
    target.mkdir()
    for folder in FOLDERS:
        shutil.copytree(ROOT / folder, target / folder, ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
    for file in FILES:
        shutil.copy2(ROOT / file, target / file)
    env = {**os.environ, "PATH": str(php.parent) + os.pathsep + os.environ["PATH"],
           "COMPOSER_ROOT_VERSION": "dev-main", "NO_COLOR": "1"}
    result = {"case": name, "php_binary": str(php), "php_version": version,
              "framework_major": framework, "dependencies": dependencies, "steps": [], "passed": False}

    def run(label: str, command: list[str]) -> bool:
        log_path = target / (label + ".log")
        with log_path.open("w") as log:
            process = subprocess.run(command, cwd=target, env=env, stdout=log, stderr=subprocess.STDOUT)
        # Bootstrap diagnostics can occur before PHPUnit installs its error handler.
        diagnostics = re.findall(r"^(?:PHP )?(?:Deprecated|Warning|Fatal error):.*$", log_path.read_text(errors="replace"), re.MULTILINE)
        result["steps"].append({"name": label, "command": command, "exit_code": process.returncode, "diagnostics": diagnostics})
        return process.returncode == 0 and not diagnostics

    composer_command = [str(php), str(composer)]
    if not run("validate", composer_command + ["validate", "--strict"]):
        return result
    metadata = json.loads((target / "composer.json").read_text())
    minimum, harness = FRAMEWORKS[framework]
    metadata["require-dev"].update({"laravel/framework": minimum, "orchestra/testbench": harness})
    (target / "composer.json").write_text(json.dumps(metadata, indent=4) + "\n")
    update = composer_command + ["update", "--prefer-stable", "--prefer-dist", "--no-interaction"]
    if dependencies == "lowest":
        update.append("--prefer-lowest")
    if not run("resolve", update):
        return result
    lock = json.loads((target / "composer.lock").read_text())
    selected = {"laravel/framework", "orchestra/testbench", "orchestra/testbench-core", "pestphp/pest", "phpunit/phpunit", "phpstan/phpstan", "laravel/pint"}
    result["resolved_versions"] = {p["name"]: p["version"] for p in lock["packages"] + lock["packages-dev"] if p["name"] in selected}
    checks = [
        ("platform", composer_command + ["check-platform-reqs"]),
        ("tests", [str(php), "vendor/bin/pest", "--colors=never", "--display-deprecations"]),
        ("examples", [str(php), "examples/quickstart.php"]),
    ]
    if dependencies == "highest":
        checks += [
            ("analysis", [str(php), "vendor/bin/phpstan", "analyse", "--memory-limit=1G"]),
            ("format", [str(php), "vendor/bin/pint", "--test"]),
            ("audit", composer_command + ["audit"]),
        ]
    statuses = [run(label, command) for label, command in checks]
    result["passed"] = all(statuses)
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", type=Path, action="append", required=True, help="Repeat for each real PHP executable.")
    parser.add_argument("--composer", type=Path, default=shutil.which("composer"))
    parser.add_argument("--output", type=Path, required=True, help="New directory for isolated installations and evidence.")
    parser.add_argument("--jobs", type=int, choices=range(1, 5), default=2)
    args = parser.parse_args()
    if args.composer is None:
        parser.error("Composer was not found; supply --composer.")
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=False)
    before = snapshot_hash()
    runtimes = []
    for executable in args.php:
        php = executable.resolve(strict=True)
        details = json.loads(subprocess.check_output([str(php), "-r", "echo json_encode(['version'=>PHP_VERSION,'bits'=>PHP_INT_SIZE*8,'icu'=>INTL_ICU_VERSION]);"], text=True))
        if details["bits"] != 64 or ".".join(details["version"].split(".")[:2]) not in {"8.3", "8.4", "8.5"}:
            parser.error("Matrix requires a 64-bit PHP 8.3, 8.4, or 8.5 executable.")
        runtimes.append((php, details))
    results = []
    with ThreadPoolExecutor(max_workers=args.jobs) as pool:
        pending = {pool.submit(execute_case, php, details["version"], framework, mode, output, args.composer.resolve()):
                   f"php-{details['version']}-laravel-{framework}-{mode}"
                   for php, details in runtimes for framework in FRAMEWORKS for mode in ("lowest", "highest")}
        for future in as_completed(pending):
            name = pending[future]
            try:
                result = future.result()
            except Exception as error:
                result = {"case": name, "passed": False, "error": str(error)}
            finally:
                vendor = output / name / "vendor"
                if vendor.exists():
                    shutil.rmtree(vendor)
            results.append(result)
            (output / name / "result.json").write_text(json.dumps(result, indent=2) + "\n")
            print(("PASS " if result["passed"] else "FAIL ") + result["case"], flush=True)
    report = {"source_sha256": before, "source_unchanged": before == snapshot_hash(),
              "runtimes": [{**details, "binary_sha256": hashlib.sha256(php.read_bytes()).hexdigest()} for php, details in runtimes],
              "cases": sorted(results, key=lambda row: row["case"])}
    (output / "results.json").write_text(json.dumps(report, indent=2) + "\n")
    return 0 if report["source_unchanged"] and all(row["passed"] for row in results) else 1


if __name__ == "__main__":
    sys.exit(main())
