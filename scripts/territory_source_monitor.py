#!/usr/bin/env python3
"""Observe approved official source files; never interpret or adopt territory data."""
from __future__ import annotations

import argparse
import hashlib
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import sys
from urllib.parse import unquote, urljoin, urlsplit, urlunsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener

ROOT = Path(__file__).resolve().parents[1]
CONFIG_PATH = ROOT / "resources/territory/sources.json"
MANIFEST_PATH = "resources/territory/observed-sources.json"
MIME_TYPES = {
    "document-links": {"text/html", "application/xhtml+xml"},
    "xls": {"application/vnd.ms-excel", "application/octet-stream"},
    "pdf": {"application/pdf", "application/octet-stream"},
}


class SourceError(ValueError):
    """An upstream response or candidate is unsafe or incomplete."""


def exact_keys(value, expected, label):
    if not isinstance(value, dict) or set(value) != set(expected):
        raise SourceError(f"Unexpected {label} fields")


def approved_url(url, hosts):
    if not isinstance(url, str) or any(ord(c) < 33 for c in url):
        raise SourceError("Invalid source URL")
    parts = urlsplit(url)
    try:
        valid = (parts.scheme == "https" and parts.hostname in hosts
                 and parts.port in (None, 443) and not parts.username
                 and not parts.password and not parts.fragment)
    except ValueError:
        valid = False
    if not valid:
        raise SourceError(f"URL is outside the approved HTTPS source policy: {url}")
    return url


def load_config(path=CONFIG_PATH):
    config = json.loads(Path(path).read_text())
    exact_keys(config, ("schema_version", "allowed_hosts", "timeout_seconds", "max_bytes", "sources"), "configuration")
    if type(config["schema_version"]) is not int or config["schema_version"] != 1:
        raise SourceError("Unsupported configuration schema")
    hosts = config["allowed_hosts"]
    if not isinstance(hosts, list) or not hosts or any(not isinstance(h, str) or not re.fullmatch(r"[a-z0-9.-]+", h) for h in hosts):
        raise SourceError("Invalid approved hosts")
    for key, maximum in (("timeout_seconds", 60), ("max_bytes", 20_000_000)):
        if type(config[key]) is not int or not 1 <= config[key] <= maximum:
            raise SourceError(f"Invalid {key}")
    if not isinstance(config["sources"], list) or not config["sources"]:
        raise SourceError("No official sources configured")
    ids = []
    for source in config["sources"]:
        exact_keys(source, ("id", "label", "url", "kind"), "source")
        if not isinstance(source["id"], str) or not re.fullmatch(r"[a-z0-9-]+", source["id"]):
            raise SourceError("Invalid source ID")
        if not isinstance(source["label"], str) or not source["label"] or source["kind"] not in MIME_TYPES:
            raise SourceError("Invalid source label or kind")
        approved_url(source["url"], hosts)
        ids.append(source["id"])
    if len(ids) != len(set(ids)):
        raise SourceError("Duplicate source IDs")
    return config


class ApprovedRedirects(HTTPRedirectHandler):
    def __init__(self, hosts):
        super().__init__()
        self.hosts = hosts

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        approved_url(newurl, self.hosts)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def download(source, config):
    url = approved_url(source["url"], config["allowed_hosts"])
    opener = build_opener(ApprovedRedirects(config["allowed_hosts"]))
    request = Request(url, headers={"User-Agent": "LaravelChileSourceMonitor/1.0", "Accept-Encoding": "identity"})
    with opener.open(request, timeout=config["timeout_seconds"]) as response:
        if response.status != 200:
            raise SourceError(f"Unexpected HTTP status for {source['id']}: {response.status}")
        final_url = approved_url(response.url, config["allowed_hosts"])
        content_type = response.headers.get_content_type()
        if content_type not in MIME_TYPES[source["kind"]]:
            raise SourceError(f"Unexpected content type for {source['id']}: {content_type}")
        length = response.headers.get("Content-Length")
        if length is not None and (not length.isdigit() or int(length) > config["max_bytes"]):
            raise SourceError(f"Invalid or oversized Content-Length for {source['id']}")
        body = response.read(config["max_bytes"] + 1)
        if not body or len(body) > config["max_bytes"]:
            raise SourceError(f"Empty or oversized response for {source['id']}")
        if length is not None and len(body) != int(length):
            raise SourceError(f"Incomplete response for {source['id']}: Content-Length mismatch")
        return final_url, content_type, body


class DocumentLinks(HTMLParser):
    def __init__(self, base, hosts):
        super().__init__(convert_charrefs=True)
        self.base, self.hosts, self.links = base, hosts, set()
        self.section_depth = 0

    def handle_starttag(self, tag, attrs):
        if tag.lower() == "div":
            if self.section_depth:
                self.section_depth += 1
            elif "field--name-field-archivo" in dict(attrs).get("class", "").split():
                self.section_depth = 1
        if tag.lower() != "a" or not self.section_depth:
            return
        href = dict(attrs).get("href")
        if not href:
            return
        absolute = urljoin(self.base, href)
        parts = urlsplit(absolute)
        if not unquote(parts.path).lower().endswith((".xls", ".xlsx", ".pdf")):
            return
        absolute = urlunsplit(parts._replace(fragment=""))
        # A changed landing page cannot silently add a different source host.
        self.links.add(approved_url(absolute, self.hosts))

    def handle_endtag(self, tag):
        if tag.lower() == "div" and self.section_depth:
            self.section_depth -= 1


def digest(value):
    return hashlib.sha256(value).hexdigest()


def inspect_source(source, config, fetch=download):
    final_url, content_type, body = fetch(source, config)
    approved_url(final_url, config["allowed_hosts"])
    if not body or len(body) > config["max_bytes"] or content_type not in MIME_TYPES[source["kind"]]:
        raise SourceError(f"Invalid response for {source['id']}")
    record = {"id": source["id"], "kind": source["kind"], "url": source["url"], "final_url": final_url}
    if source["kind"] == "document-links":
        try:
            html = body.decode("utf-8-sig", errors="strict")
        except UnicodeDecodeError as exc:
            raise SourceError("Official source page is not valid UTF-8") from exc
        if not re.search(r"<(?:html|!doctype html)\b", html, re.I):
            raise SourceError("Official source page is not an HTML document")
        parser = DocumentLinks(final_url, config["allowed_hosts"])
        parser.feed(html)
        links = sorted(parser.links)
        expected = {s["url"] for s in config["sources"] if s["kind"] != "document-links"}
        if not links or not expected.issubset(links):
            raise SourceError("Official page no longer links all configured documents; review source configuration")
        record["document_links"] = links
        record["sha256"] = digest("\n".join(links).encode())
    else:
        signature = b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1" if source["kind"] == "xls" else b"%PDF-"
        if not body.startswith(signature):
            raise SourceError(f"Invalid {source['kind']} signature for {source['id']}")
        record["size_bytes"] = len(body)
        record["sha256"] = digest(body)
    return record


def observe(config, fetch=download):
    records = [inspect_source(source, config, fetch) for source in config["sources"]]
    return {"schema_version": 1, "purpose": "source-observation-only", "sources": records}


def validate_manifest(manifest, config):
    exact_keys(manifest, ("schema_version", "purpose", "sources"), "manifest")
    if type(manifest["schema_version"]) is not int or manifest["schema_version"] != 1 or manifest["purpose"] != "source-observation-only":
        raise SourceError("Unsupported observation manifest")
    if not isinstance(manifest["sources"], list) or len(manifest["sources"]) != len(config["sources"]):
        raise SourceError("Missing or extra observed sources")
    for record, source in zip(manifest["sources"], config["sources"]):
        extra = "document_links" if source["kind"] == "document-links" else "size_bytes"
        exact_keys(record, ("id", "kind", "url", "final_url", "sha256", extra), "observation")
        if any(record[key] != source[key] for key in ("id", "kind", "url")):
            raise SourceError("Observation does not match configured source")
        approved_url(record["final_url"], config["allowed_hosts"])
        if not isinstance(record["sha256"], str) or not re.fullmatch(r"[0-9a-f]{64}", record["sha256"]):
            raise SourceError("Invalid source digest")
        if extra == "size_bytes":
            if type(record[extra]) is not int or not 1 <= record[extra] <= config["max_bytes"]:
                raise SourceError("Invalid source size")
        else:
            links = record[extra]
            if not isinstance(links, list) or not links or any(not isinstance(link, str) for link in links):
                raise SourceError("Invalid document link list")
            if links != sorted(set(links)):
                raise SourceError("Document links must be unique and ordered")
            for link in links:
                approved_url(link, config["allowed_hosts"])
                if not unquote(urlsplit(link).path).lower().endswith((".xls", ".xlsx", ".pdf")):
                    raise SourceError("Unsupported document link")
            expected = {s["url"] for s in config["sources"] if s["kind"] != "document-links"}
            if not expected.issubset(links) or record["sha256"] != digest("\n".join(links).encode()):
                raise SourceError("Incomplete or inconsistent document links")
    return manifest


def read_manifest(path, config):
    path = Path(path)
    if path.is_symlink() or path.stat().st_size > 100_000:
        raise SourceError("Invalid observation file")
    return validate_manifest(json.loads(path.read_text()), config)


def serialize(manifest):
    return json.dumps(manifest, ensure_ascii=False, indent=2) + "\n"


def report(before, after):
    status = ("Source changed; territorial changes not yet interpreted." if before != after else
              "Official source observations match the accepted baseline; reconcile any obsolete candidate PR.")
    lines = ["## Official territorial source review", "", status,
             "This PR updates source observations only. It does not adopt a catalogue, establish legal effectiveness, or publish a release.", ""]
    old = {item["id"]: item for item in before["sources"]}
    for item in after["sources"]:
        previous = old[item["id"]]
        if previous != item:
            lines.extend([f"### {item['id']}", f"Source: {item['url']}",
                          f"Previous SHA-256: `{previous['sha256']}`", f"Observed SHA-256: `{item['sha256']}`"])
            if "size_bytes" in item:
                lines.append(f"Bytes: {previous['size_bytes']} → {item['size_bytes']}")
            else:
                lines.extend(f"Added document: {u}" for u in sorted(set(item['document_links']) - set(previous['document_links'])))
                lines.extend(f"Removed document: {u}" for u in sorted(set(previous['document_links']) - set(item['document_links'])))
            lines.append("")
    lines.extend(["Review the official legal instrument, effective date, source terms, and semantic data diff before a separate catalogue update.",
                  "Downloaded files were checked for size and container signature, not parsed as territorial records."])
    return "\n".join(lines) + "\n"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--config", type=Path, default=CONFIG_PATH)
    parser.add_argument("--baseline", type=Path, default=ROOT / MANIFEST_PATH)
    parser.add_argument("--output", type=Path)
    parser.add_argument("--report", type=Path)
    parser.add_argument("--validate", type=Path, help="Validate a candidate without network access")
    args = parser.parse_args()
    config = load_config(args.config)
    if args.validate:
        read_manifest(args.validate, config)
        print("Observation manifest is valid.")
        return
    candidate = validate_manifest(observe(config), config)
    baseline = read_manifest(args.baseline, config) if args.baseline.exists() else None
    # Network and validation complete before any output is replaced.
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(serialize(candidate))
    if args.report and baseline is not None:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(report(baseline, candidate))
    print("changed=" + str(candidate != baseline).lower())
    if not args.output:
        print(serialize(candidate), end="")


if __name__ == "__main__":
    try:
        main()
    except (SourceError, OSError, ValueError) as exc:
        print(f"Source review failed: {exc}", file=sys.stderr)
        sys.exit(1)
