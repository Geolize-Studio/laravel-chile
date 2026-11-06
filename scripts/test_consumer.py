#!/usr/bin/env python3
"""Verify a local archive or public Packagist release in a disposable Laravel app."""

import json
import argparse
import os
from pathlib import Path
import shutil
import subprocess
import tempfile


ROOT = Path(__file__).resolve().parents[1]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--with-boost", action="store_true", help="Also verify project-local Codex guideline and skill installation/update.")
    parser.add_argument("--laravel", choices=("12", "13"), default="13")
    parser.add_argument("--package-version", help="Install this version from public Packagist instead of a local archive.")
    parser.add_argument("--expected-reference", help="Require the installed public package to match this Git commit.")
    parser.add_argument("--php", type=Path, default=shutil.which("php"))
    parser.add_argument("--composer", type=Path, default=shutil.which("composer"))
    arguments = parser.parse_args()
    if arguments.php is None or arguments.composer is None:
        parser.error("PHP and Composer are required; supply their executable paths.")
    if arguments.expected_reference and not arguments.package_version:
        parser.error("--expected-reference requires --package-version.")
    php = arguments.php.resolve(strict=True)
    composer = arguments.composer.resolve(strict=True)

    def run(command: list[str], directory: Path) -> None:
        if command[0] == "composer":
            command = [str(php), str(composer), *command[1:]]
        elif command[0] == "php":
            command = [str(php), *command[1:]]
        environment = {**os.environ, "COMPOSER_ROOT_VERSION": "dev-main",
                       "PATH": str(php.parent) + os.pathsep + os.environ["PATH"]}
        subprocess.run(command, cwd=directory, check=True, env=environment)

    with tempfile.TemporaryDirectory(prefix="laravel-chile-consumer-") as temporary:
        work = Path(temporary)
        metadata = json.loads((ROOT / "composer.json").read_text())
        if not arguments.package_version:
            archive = work / "archive"
            archive.mkdir()
            run(["composer", "archive", "--format=zip", "--dir", str(archive), "--file=laravel-chile", "--no-interaction"], ROOT)
            metadata.update({"version": "dev-main", "dist": {"type": "zip", "url": (archive / "laravel-chile.zip").as_uri()}})
        for field in ("require-dev", "autoload-dev", "scripts", "config"):
            metadata.pop(field, None)
        app = work / "app"
        for relative in ("bootstrap/cache", "config", "storage/framework/cache", "storage/framework/views", "storage/framework/sessions", "storage/logs"):
            (app / relative).mkdir(parents=True, exist_ok=True)
        consumer_metadata = {
            "name": "chile-tests/consumer", "type": "project",
            "require": {"laravel/framework": "^12.24" if arguments.laravel == "12" else "^13.0", metadata["name"]: arguments.package_version or "dev-main"},
            "minimum-stability": "stable", "prefer-stable": True,
            "config": {"allow-plugins": False},
        }
        if not arguments.package_version:
            consumer_metadata["repositories"] = [{"type": "package", "package": metadata}]
        (app / "composer.json").write_text(json.dumps(consumer_metadata, indent=2) + "\n")
        (app / "bootstrap/app.php").write_text("<?php\nreturn Illuminate\\Foundation\\Application::configure(basePath: dirname(__DIR__))->create();\n")
        (app / "bootstrap/providers.php").write_text("<?php\nreturn [];\n")
        (app / "artisan").write_text("<?php\nrequire __DIR__.'/vendor/autoload.php';\n$app = require __DIR__.'/bootstrap/app.php';\nexit($app->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput));\n")
        run(["composer", "install", "--no-dev", "--no-scripts", "--no-interaction", "--prefer-dist"], app)
        installed = app / "vendor" / metadata["name"]
        forbidden = ("vendor", ".git", ".agents", ".github", ".omx", ".source-review", "tests", "scripts", "composer.lock")
        unexpected = [path for path in forbidden if (installed / path).exists()]
        if unexpected:
            raise RuntimeError("The distribution includes development or local-only files: " + repr(unexpected))
        if arguments.expected_reference:
            lock = json.loads((app / "composer.lock").read_text())
            package = next(package for package in lock["packages"] if package["name"] == metadata["name"])
            if package.get("source", {}).get("reference") != arguments.expected_reference or package.get("dist", {}).get("reference") != arguments.expected_reference:
                raise RuntimeError("The published package does not match the expected Git commit.")
            print(f"Published package {package['version']} verified at {arguments.expected_reference}.")
        run(["composer", "check-platform-reqs", "--no-dev"], app)
        run(["php", "artisan", "package:discover"], app)
        (app / "smoke.php").write_text((ROOT / "scripts/consumer-smoke.php").read_text())
        run(["php", "smoke.php"], app)
        run(["php", "artisan", "config:cache"], app)
        run(["php", "smoke.php"], app)
        run(["php", "artisan", "config:clear"], app)
        print("Production consumer archive, discovery, examples, and config cache passed.")
        if arguments.with_boost:
            run(["composer", "require", "--dev", "laravel/boost:^2.10", "--no-scripts", "--no-interaction", "--prefer-dist"], app)
            (app / "config/app.php").write_text("<?php\nreturn ['name' => 'Chile consumer', 'env' => 'local', 'url' => 'http://localhost'];\n")
            (app / "config/boost.php").write_text("<?php\nreturn ['browser_logs_watcher' => false, 'enforce_tests' => false, 'rules' => ['enabled' => false]];\n")
            (app / "boost.json").write_text(json.dumps({
                "agents": ["codex"], "packages": [metadata["name"]],
                "cloud": False, "nightwatch": False, "sail": False, "mcp": False,
            }, indent=2) + "\n")
            (app / "AGENTS.md").write_text("Consumer-owned guidance.\n")
            (app / "boost-smoke.php").write_text((ROOT / "scripts/boost-smoke.php").read_text())
            # Explicit flags avoid MCP registration; every output lives in this disposable app.
            run(["php", "artisan", "package:discover"], app)
            run(["php", "artisan", "boost:install", "--guidelines", "--skills", "--no-interaction"], app)
            source_skill = app / "vendor/geolize-studio/laravel-chile/resources/boost/skills/chile-development/SKILL.md"
            source_guideline = app / "vendor/geolize-studio/laravel-chile/resources/boost/guidelines/core.blade.php"
            run(["php", "boost-smoke.php"], app)
            originals = {path: path.read_bytes() for path in (source_skill, source_guideline)}
            for path, contents in originals.items():
                path.write_bytes(contents + b"\nConsumer update verification marker.\n")
            run(["php", "artisan", "boost:update", "--no-discover", "--no-interaction"], app)
            run(["php", "boost-smoke.php"], app)
            if "Consumer update verification marker." not in (app / "AGENTS.md").read_text():
                raise RuntimeError("Boost update did not propagate the guideline change.")
            for path, contents in originals.items():
                path.write_bytes(contents)
            run(["php", "artisan", "boost:update", "--no-discover", "--no-interaction"], app)
            run(["php", "boost-smoke.php"], app)
            if "Consumer update verification marker." in (app / "AGENTS.md").read_text():
                raise RuntimeError("Boost update retained a removed guideline marker.")
            print("Boost installed and updated the package guideline and formatted Codex skill locally.")


if __name__ == "__main__":
    main()
