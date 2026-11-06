#!/usr/bin/env python3
"""Validate an observation proposal locally; a pinned action owns the PR lifecycle."""
from __future__ import annotations

import argparse
import base64
import json
import os
from pathlib import Path
import re
import subprocess
import sys
from urllib.parse import quote

from territory_source_monitor import (ROOT, MANIFEST_PATH, SourceError, load_config,
                                     read_manifest, report, serialize, validate_manifest)

BRANCH = "automation/territory-source-observations"
MARKER = "<!-- laravel-chile:official-source-observation:v1 -->"


def api(endpoint, missing_ok=False, paginate=False):
    """Read GitHub metadata; this helper has no remote mutation capability."""
    args = ["gh", "api", "--method", "GET", endpoint]
    if paginate:
        args.extend(["--paginate", "--slurp"])
    result = subprocess.run(args, text=True, capture_output=True, check=False)
    if result.returncode:
        if missing_ok and "HTTP 404" in result.stderr:
            return None
        raise SourceError(f"GitHub metadata request failed: {result.stderr.strip()}")
    data = json.loads(result.stdout)
    if paginate:
        if not isinstance(data, list) or any(not isinstance(page, list) for page in data):
            raise SourceError("Unexpected paginated GitHub response")
        return [item for page in data for item in page]
    return data


def assert_observation_only(files):
    if not isinstance(files, list) or any(item.get("filename") != MANIFEST_PATH for item in files):
        raise SourceError("Automation branch contains unrelated changes; refusing to modify it")


def assert_owned_pr(pr, repo, base):
    if (MARKER not in (pr.get("body") or "") or pr.get("user", {}).get("login") != "github-actions[bot]"
            or pr.get("head", {}).get("ref") != BRANCH
            or pr.get("head", {}).get("repo", {}).get("full_name") != repo
            or pr.get("base", {}).get("ref") != base):
        raise SourceError("Existing PR is not an owned source-observation PR")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("candidate")
    parser.add_argument("--body", type=Path, default=Path("source-review/body.md"))
    args = parser.parse_args()
    config = load_config()
    candidate = read_manifest(args.candidate, config)
    repo = os.environ.get("GITHUB_REPOSITORY", "")
    expected_sha = os.environ.get("GITHUB_SHA", "")
    if not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repo) or not re.fullmatch(r"[0-9a-f]{40}", expected_sha):
        raise SourceError("Expected trusted GitHub repository and workflow SHA")
    prefix = f"repos/{repo}"
    base = api(prefix)["default_branch"]
    if not isinstance(base, str) or not re.fullmatch(r"[A-Za-z0-9._/-]+", base):
        raise SourceError("Unexpected default branch name")
    base_sha = api(f"{prefix}/git/ref/heads/{quote(base, safe='')}")["object"]["sha"]
    if base_sha != expected_sha:
        raise SourceError("Default branch advanced during inspection; rerun the workflow")
    baseline_file = api(f"{prefix}/contents/{MANIFEST_PATH}?ref={base_sha}")
    baseline = validate_manifest(json.loads(base64.b64decode(baseline_file["content"])), config)
    head_filter = quote(f"{repo.split('/')[0]}:{BRANCH}", safe="")
    pulls = api(f"{prefix}/pulls?head={head_filter}&state=all&per_page=100", paginate=True)
    open_prs = [p for p in pulls if p["state"] == "open"]
    if len(open_prs) > 1:
        raise SourceError("Multiple source-observation PRs exist")
    pr = open_prs[0] if open_prs else None
    if baseline == candidate and pr is None:
        print("ready=false")
        return
    branch = api(f"{prefix}/git/ref/heads/{quote(BRANCH, safe='')}", missing_ok=True)
    if pr:
        assert_owned_pr(pr, repo, base)
        files = api(f"{prefix}/pulls/{pr['number']}/files?per_page=100", paginate=True)
        assert_observation_only(files)
    if branch:
        comparison = api(f"{prefix}/compare/{base_sha}...{branch['object']['sha']}")
        assert_observation_only(comparison["files"])
        if not pr:
            if pulls and not pulls[0].get("merged_at"):
                raise SourceError("Previous source-review PR was closed without merge; manual review required")
            if comparison["status"] not in ("behind", "identical"):
                commit = api(f"{prefix}/commits/{branch['object']['sha']}")
                if (commit.get("committer") or {}).get("login") != "github-actions[bot]":
                    raise SourceError("Orphan automation branch has an unexpected committer")
        existing = api(f"{prefix}/contents/{MANIFEST_PATH}?ref={quote(BRANCH, safe='')}")
        existing_manifest = validate_manifest(json.loads(base64.b64decode(existing["content"])), config)
        if pr and existing_manifest == candidate and baseline != candidate:
            print("ready=false")
            return
    elif pr:
        raise SourceError("Open source-review PR has no matching branch")
    body = MARKER + "\n\n" + report(baseline, candidate)
    body += "\nAutomated PR checks may require a maintainer to approve workflow runs when using GITHUB_TOKEN.\n"
    # Only these two local files are prepared; the established action handles Git/PR state.
    (ROOT / MANIFEST_PATH).write_text(serialize(candidate))
    args.body.parent.mkdir(parents=True, exist_ok=True)
    args.body.write_text(body)
    print(f"base={base}")
    print("ready=true")


if __name__ == "__main__":
    try:
        main()
    except (SourceError, OSError, ValueError, KeyError) as exc:
        print(f"Source proposal failed: {exc}", file=sys.stderr)
        sys.exit(1)
