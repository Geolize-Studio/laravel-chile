import base64
import copy
import os
from email.message import Message
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
from urllib.request import Request

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts"))
import territory_source_monitor as monitor
import propose_source_review as proposal


class SourcePolicyTests(unittest.TestCase):
    def setUp(self):
        self.config = monitor.load_config()
        self.sources = self.config["sources"]
        self.links = [s["url"] for s in self.sources[1:]]

    def html(self, links=None, noise=""):
        anchors = "".join(f'<a href="{link}">document</a>' for link in (self.links if links is None else links))
        return f'<html><body>{noise}<div class="field field--name-field-archivo"><div>{anchors}</div></div></body></html>'.encode()

    def fetch(self, source, config):
        bodies = {
            "document-links": ("text/html", self.html()),
            "xls": ("application/vnd.ms-excel", b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1workbook"),
            "pdf": ("application/pdf", b"%PDF-1.4\nfixture"),
        }
        mime, body = bodies[source["kind"]]
        return source["url"], mime, body

    def candidate(self):
        return monitor.observe(self.config, self.fetch)

    def test_valid_candidate_matches_config(self):
        candidate = self.candidate()
        self.assertEqual(monitor.validate_manifest(candidate, self.config), candidate)

    def test_volatile_page_markup_does_not_change_observation(self):
        first = monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", self.html(noise="checked today")))
        second = monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", self.html(noise="checked tomorrow")))
        self.assertEqual(first, second)

    def test_unrelated_sidebar_links_are_ignored(self):
        body = self.html(noise='<a href="http://different.example/news.pdf">News</a>')
        actual = monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", body))
        self.assertEqual(actual["document_links"], sorted(self.links))

    def test_document_order_and_duplicates_do_not_create_change(self):
        body = self.html(list(reversed(self.links)) + self.links)
        actual = monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", body))
        self.assertEqual(actual, self.candidate()["sources"][0])

    def test_new_official_link_changes_digest(self):
        extra = "https://www.subdere.gov.cl/sites/default/files/documentos/CUT_2027.xlsx"
        body = self.html(self.links + [extra])
        actual = monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", body))
        self.assertIn(extra, actual["document_links"])
        self.assertNotEqual(actual["sha256"], self.candidate()["sources"][0]["sha256"])

    def test_missing_document_is_failure_not_deletion(self):
        with self.assertRaises(monitor.SourceError):
            monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", self.html(self.links[:1])))

    def test_changed_attachment_section_is_schema_failure(self):
        body = self.html().replace(b"field--name-field-archivo", b"new-section")
        with self.assertRaises(monitor.SourceError):
            monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", body))

    def test_new_external_attachment_fails_closed(self):
        with self.assertRaises(monitor.SourceError):
            monitor.inspect_source(self.sources[0], self.config, lambda s,c: (s["url"], "text/html", self.html(self.links + ["https://evil.example/source.xls"])))

    def test_bad_url_variants_are_rejected(self):
        for url in ("http://www.subdere.gov.cl/a", "https://www.subdere.gov.cl.evil.example/a", "https://user@www.subdere.gov.cl/a", "https://www.subdere.gov.cl:444/a", "https://www.subdere.gov.cl/a#x", "https://www.subdere.gov.cl/\na"):
            with self.subTest(url=url), self.assertRaises(monitor.SourceError):
                monitor.approved_url(url, self.config["allowed_hosts"])

    def test_redirect_to_other_host_is_rejected_before_request(self):
        handler = monitor.ApprovedRedirects(self.config["allowed_hosts"])
        with self.assertRaises(monitor.SourceError):
            handler.redirect_request(Request(self.sources[0]["url"]), None, 302, "Found", {}, "https://evil.example/a")

    def test_invalid_signature_is_rejected(self):
        for source in self.sources[1:]:
            with self.subTest(kind=source["kind"]), self.assertRaises(monitor.SourceError):
                monitor.inspect_source(source, self.config, lambda s,c: (s["url"], "application/octet-stream", b"<html>upstream error</html>"))

    def test_empty_and_oversize_body_are_rejected(self):
        config = copy.deepcopy(self.config)
        config["max_bytes"] = 8
        for body in (b"", b"x" * 9):
            with self.subTest(body=body), self.assertRaises(monitor.SourceError):
                monitor.inspect_source(self.sources[1], config, lambda s,c: (s["url"], "application/octet-stream", body))

    def test_invalid_content_type_is_rejected(self):
        with self.assertRaises(monitor.SourceError):
            monitor.inspect_source(self.sources[1], self.config, lambda s,c: (s["url"], "text/html", b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"))

    def test_bytes_change_is_observed(self):
        source = self.sources[2]
        a = monitor.inspect_source(source, self.config, self.fetch)
        b = monitor.inspect_source(source, self.config, lambda s,c: (s["url"], "application/pdf", b"%PDF-1.4\nchanged"))
        self.assertNotEqual(a["sha256"], b["sha256"])

    def test_candidate_cannot_add_source_or_arbitrary_fields(self):
        for change in (lambda d: d.update(path="../../runtime.json"), lambda d: d["sources"].append(copy.deepcopy(d["sources"][0])), lambda d: d["sources"][1].update(url="https://evil.example/a.xls")):
            data = self.candidate()
            change(data)
            with self.assertRaises(monitor.SourceError):
                monitor.validate_manifest(data, self.config)

    def test_candidate_link_digest_must_be_consistent(self):
        data = self.candidate()
        data["sources"][0]["sha256"] = "0" * 64
        with self.assertRaises(monitor.SourceError):
            monitor.validate_manifest(data, self.config)

    def test_candidate_size_is_bounded_and_not_boolean(self):
        for size in (True, 0, self.config["max_bytes"] + 1):
            data = self.candidate()
            data["sources"][1]["size_bytes"] = size
            with self.subTest(size=size), self.assertRaises(monitor.SourceError):
                monitor.validate_manifest(data, self.config)

    def test_network_failure_does_not_replace_output(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "candidate.json"
            output.write_text("keep me")
            with patch.object(sys, "argv", ["monitor", "--output", str(output)]), patch.object(monitor, "observe", side_effect=OSError("unavailable")):
                with self.assertRaises(OSError):
                    monitor.main()
            self.assertEqual(output.read_text(), "keep me")

    def test_report_marks_semantic_adoption_as_pending(self):
        before, after = self.candidate(), self.candidate()
        after["sources"][1]["sha256"] = "f" * 64
        text = monitor.report(before, after)
        self.assertIn("territorial changes not yet interpreted", text)
        self.assertIn(before["sources"][1]["sha256"], text)
        self.assertIn("f" * 64, text)

    def test_existing_committed_baseline_validates(self):
        monitor.read_manifest(monitor.ROOT / monitor.MANIFEST_PATH, self.config)


class ProposalPolicyTests(unittest.TestCase):
    def test_unrelated_branch_changes_are_rejected(self):
        with self.assertRaises(monitor.SourceError):
            proposal.assert_observation_only([{"filename": "src/Territory.php"}])

    def test_observation_path_is_allowed(self):
        proposal.assert_observation_only([{"filename": monitor.MANIFEST_PATH}])

    def owned_pr(self):
        return {"body": proposal.MARKER, "user": {"login": "github-actions[bot]"},
                "head": {"ref": proposal.BRANCH, "repo": {"full_name": "example/package"}},
                "base": {"ref": "main"}}

    def test_owned_pr_is_recognized(self):
        proposal.assert_owned_pr(self.owned_pr(), "example/package", "main")

    def test_other_author_or_fork_or_missing_marker_is_rejected(self):
        for change in (lambda p: p.update(body="ordinary PR"), lambda p: p["user"].update(login="human"), lambda p: p["head"]["repo"].update(full_name="fork/package")):
            pr = self.owned_pr()
            change(pr)
            with self.assertRaises(monitor.SourceError):
                proposal.assert_owned_pr(pr, "example/package", "main")


    def test_no_changes_requires_no_mutating_api_calls(self):
        config = monitor.load_config()
        baseline = monitor.read_manifest(monitor.ROOT / monitor.MANIFEST_PATH, config)
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / "candidate.json"
            candidate.write_text(monitor.serialize(baseline))
            replies = [
                {"default_branch": "main"},
                {"object": {"sha": "a" * 40}},
                {"content": base64.b64encode(monitor.serialize(baseline).encode()).decode()},
                [],
            ]
            with patch.dict(os.environ, {"GITHUB_REPOSITORY": "example/package", "GITHUB_SHA": "a" * 40}), patch.object(sys, "argv", ["propose", str(candidate)]), patch.object(proposal, "api", side_effect=replies) as api, patch("sys.stdout", new=io.StringIO()):
                proposal.main()
            self.assertEqual(api.call_count, 4)
            self.assertTrue(all(len(call.args) == 1 for call in api.call_args_list))

    def test_duplicate_candidate_makes_no_commit_comment_or_pr(self):
        config = monitor.load_config()
        baseline = monitor.read_manifest(monitor.ROOT / monitor.MANIFEST_PATH, config)
        candidate_data = copy.deepcopy(baseline)
        candidate_data["sources"][1]["sha256"] = "f" * 64
        pr = self.owned_pr()
        pr.update(state="open", number=7)
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / "candidate.json"
            candidate.write_text(monitor.serialize(candidate_data))
            replies = [
                {"default_branch": "main"}, {"object": {"sha": "a" * 40}},
                {"content": base64.b64encode(monitor.serialize(baseline).encode()).decode()},
                [pr], {"object": {"sha": "b" * 40}},
                [{"filename": monitor.MANIFEST_PATH}],
                {"status": "ahead", "files": [{"filename": monitor.MANIFEST_PATH}]},
                {"content": base64.b64encode(monitor.serialize(candidate_data).encode()).decode(), "sha": "c" * 40},
            ]
            with patch.dict(os.environ, {"GITHUB_REPOSITORY": "example/package", "GITHUB_SHA": "a" * 40}), patch.object(sys, "argv", ["propose", str(candidate)]), patch.object(proposal, "api", side_effect=replies) as api, patch("sys.stdout", new=io.StringIO()):
                proposal.main()
            self.assertEqual(api.call_count, 8)
            self.assertTrue(all(len(call.args) == 1 for call in api.call_args_list))

    def test_file_checks_include_later_github_pages(self):
        pages = [[{"filename": monitor.MANIFEST_PATH}], [{"filename": "src/unrelated.php"}]]
        result = unittest.mock.Mock(returncode=0, stdout=json.dumps(pages), stderr="")
        with patch.object(proposal.subprocess, "run", return_value=result) as run:
            files = proposal.api("repos/example/package/pulls/7/files?per_page=100", paginate=True)
        self.assertIn("--paginate", run.call_args.args[0])
        self.assertIn("--slurp", run.call_args.args[0])
        self.assertIn("GET", run.call_args.args[0])
        with self.assertRaises(monitor.SourceError):
            proposal.assert_observation_only(files)

    def test_valid_new_candidate_prepares_only_manifest_and_body(self):
        config = monitor.load_config()
        baseline = monitor.read_manifest(monitor.ROOT / monitor.MANIFEST_PATH, config)
        candidate_data = copy.deepcopy(baseline)
        candidate_data["sources"][1]["sha256"] = "f" * 64
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            candidate = root / "candidate.json"
            candidate.write_text(monitor.serialize(candidate_data))
            destination = root / monitor.MANIFEST_PATH
            destination.parent.mkdir(parents=True)
            destination.write_text(monitor.serialize(baseline))
            body = root / "body.md"
            replies = [
                {"default_branch": "main"}, {"object": {"sha": "a" * 40}},
                {"content": base64.b64encode(monitor.serialize(baseline).encode()).decode()},
                [], None,
            ]
            with patch.dict(os.environ, {"GITHUB_REPOSITORY": "example/package", "GITHUB_SHA": "a" * 40}), patch.object(sys, "argv", ["prepare", str(candidate), "--body", str(body)]), patch.object(proposal, "api", side_effect=replies) as api, patch.object(proposal, "ROOT", root), patch("sys.stdout", new=io.StringIO()) as output:
                proposal.main()
            self.assertEqual(json.loads(destination.read_text()), candidate_data)
            self.assertIn(proposal.MARKER, body.read_text())
            self.assertIn("territorial changes not yet interpreted", body.read_text())
            self.assertEqual(output.getvalue(), "base=main\nready=true\n")
            self.assertEqual(api.call_count, 5)
            self.assertTrue(all(len(call.args) == 1 for call in api.call_args_list))

    def test_upstream_rollback_reconciles_existing_owned_pr(self):
        config = monitor.load_config()
        accepted = monitor.read_manifest(monitor.ROOT / monitor.MANIFEST_PATH, config)
        obsolete = copy.deepcopy(accepted)
        obsolete["sources"][1]["sha256"] = "f" * 64
        pr = self.owned_pr()
        pr.update(state="open", number=7)
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            candidate = root / "candidate.json"
            candidate.write_text(monitor.serialize(accepted))
            destination = root / monitor.MANIFEST_PATH
            destination.parent.mkdir(parents=True)
            destination.write_text(monitor.serialize(accepted))
            body = root / "body.md"
            replies = [
                {"default_branch": "main"}, {"object": {"sha": "a" * 40}},
                {"content": base64.b64encode(monitor.serialize(accepted).encode()).decode()},
                [pr], {"object": {"sha": "b" * 40}},
                [{"filename": monitor.MANIFEST_PATH}],
                {"status": "ahead", "files": [{"filename": monitor.MANIFEST_PATH}]},
                {"content": base64.b64encode(monitor.serialize(obsolete).encode()).decode()},
            ]
            with patch.dict(os.environ, {"GITHUB_REPOSITORY": "example/package", "GITHUB_SHA": "a" * 40}), patch.object(sys, "argv", ["prepare", str(candidate), "--body", str(body)]), patch.object(proposal, "api", side_effect=replies), patch.object(proposal, "ROOT", root), patch("sys.stdout", new=io.StringIO()) as output:
                proposal.main()
            # The established action must run despite an empty diff against main.
            self.assertEqual(output.getvalue(), "base=main\nready=true\n")
            self.assertEqual(json.loads(destination.read_text()), accepted)
            self.assertIn("reconcile any obsolete candidate PR", body.read_text())

    def test_workflow_reconciles_after_every_successful_inspection(self):
        workflow = (monitor.ROOT / ".github/workflows/territory-sources.yml").read_text()
        self.assertNotIn("steps.monitor.outputs.changed", workflow)
        self.assertNotIn("needs.inspect.outputs.changed", workflow)
        self.assertIn("needs: inspect", workflow)
        self.assertIn("if: steps.prepare.outputs.ready == 'true'", workflow)

    def test_advanced_default_branch_stops_before_any_write(self):
        config = monitor.load_config()
        baseline = monitor.read_manifest(monitor.ROOT / monitor.MANIFEST_PATH, config)
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / "candidate.json"
            candidate.write_text(monitor.serialize(baseline))
            with patch.dict(os.environ, {"GITHUB_REPOSITORY": "example/package", "GITHUB_SHA": "a" * 40}), patch.object(sys, "argv", ["propose", str(candidate)]), patch.object(proposal, "api", side_effect=[{"default_branch": "main"}, {"object": {"sha": "b" * 40}}]) as api:
                with self.assertRaises(monitor.SourceError):
                    proposal.main()
            self.assertEqual(api.call_count, 2)


class DownloadBoundaryTests(unittest.TestCase):
    def setUp(self):
        self.config = monitor.load_config()
        self.source = self.config["sources"][1]

    def response(self, body, length=None, status=200):
        response = unittest.mock.MagicMock()
        response.__enter__.return_value = response
        response.status = status
        response.url = self.source["url"]
        response.headers = Message()
        response.headers["Content-Type"] = "application/vnd.ms-excel"
        if length is not None:
            response.headers["Content-Length"] = str(length)
        response.read.side_effect = lambda limit: body[:limit]
        return response

    def test_download_reads_with_explicit_limit(self):
        response = self.response(b"a" * 10)
        with patch.object(monitor, "build_opener") as opener:
            opener.return_value.open.return_value = response
            monitor.download(self.source, self.config)
        response.read.assert_called_once_with(self.config["max_bytes"] + 1)
        self.assertEqual(opener.return_value.open.call_args.kwargs["timeout"], 30)

    def test_oversize_declared_length_fails_without_read(self):
        response = self.response(b"a", length=self.config["max_bytes"] + 1)
        with patch.object(monitor, "build_opener") as opener:
            opener.return_value.open.return_value = response
            with self.assertRaises(monitor.SourceError):
                monitor.download(self.source, self.config)
        response.read.assert_not_called()

    def test_truncated_body_with_valid_signature_is_rejected(self):
        for kind, content_type, body in (
            ("xls", "application/vnd.ms-excel", b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1partial"),
            ("pdf", "application/pdf", b"%PDF-1.4\npartial"),
        ):
            source = next(s for s in self.config["sources"] if s["kind"] == kind)
            response = self.response(body, length=len(body) + 100)
            response.headers.replace_header("Content-Type", content_type)
            response.url = source["url"]
            with self.subTest(kind=kind), patch.object(monitor, "build_opener") as opener:
                opener.return_value.open.return_value = response
                with self.assertRaisesRegex(monitor.SourceError, "Content-Length mismatch"):
                    monitor.download(source, self.config)

    def test_complete_declared_body_is_accepted(self):
        body = b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1complete"
        response = self.response(body, length=len(body))
        with patch.object(monitor, "build_opener") as opener:
            opener.return_value.open.return_value = response
            self.assertEqual(monitor.download(self.source, self.config)[2], body)

    def test_http_failure_and_empty_body_are_rejected(self):
        for response in (self.response(b"error", status=503), self.response(b"")):
            with patch.object(monitor, "build_opener") as opener:
                opener.return_value.open.return_value = response
                with self.assertRaises(monitor.SourceError):
                    monitor.download(self.source, self.config)


if __name__ == "__main__":
    unittest.main()
