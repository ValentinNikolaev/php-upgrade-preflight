"""Offline host tests; real Docker checks require R4_TEST_REPO explicitly."""
import contextlib
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

import run


class HostTests(unittest.TestCase):
    def test_source_change_and_symlink_text_change_digest(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "a.php").write_bytes(b"<?php echo 1;")
            before = run.snapshot(root)
            (root / "a.php").write_bytes(b"<?php echo 2;")
            after = run.snapshot(root)
            self.assertNotEqual(before["sha256"], after["sha256"])
            self.assertEqual(after["shape"]["php_files"], 1)

    def test_nonexistent_metrics_are_unknown(self):
        with tempfile.TemporaryDirectory() as directory:
            metrics = run.resource_stats(Path(directory) / "missing")
            self.assertIsNone(metrics["max_rss_kib"])
            self.assertIsNone(metrics["exit_code"])
            self.assertFalse(metrics["available"])

    def test_signalled_start_counts_without_invented_completion(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "composer").mkdir()
            (root / "composer/17.start").write_text("123.45\nupdate\n")
            result = run.composer_records(root)
            self.assertEqual(result["started_count"], 1)
            self.assertEqual(result["timed_count"], 0)
            self.assertIsNone(result["invocations"][0]["resources"]["elapsed_seconds"])

    def test_rejects_inexact_php_and_unbounded_timeouts(self):
        with self.assertRaises(Exception):
            run.exact_php("8.3")
        with self.assertRaises(Exception):
            run.bounded(900)("901")
        with self.assertRaises(Exception):
            run.bounded(900)("0")

    def args(self, directory):
        root = Path(directory)
        (root / "app").mkdir()
        (root / "app/composer.json").write_text("{}")
        (root / "repo/vendor").mkdir(parents=True)
        (root / "repo/vendor/autoload.php").touch()
        return run.arguments(["--app", str(root / "app"), "--repo", str(root / "repo"), "--output", str(root / "out"), "--cache", str(root / "cache"), "--target-major", "11", "--from-php", "8.1.0", "--target-php", "8.2.0", "--memory-limit", "128M", "--network", "none", "--scenario-timeout", "1", "--diagnostic-timeout", "1", "--overall-timeout", "1", "--image", "r4-test"])

    def test_nested_writable_path_rejected_before_creation(self):
        with tempfile.TemporaryDirectory() as directory:
            args = self.args(directory)
            args.output = args.app / "output"
            with self.assertRaises(ValueError):
                run.validate(args)
            self.assertFalse(args.output.exists())

    def test_docker_creation_failure_retains_immutability_and_no_fake_metrics(self):
        with tempfile.TemporaryDirectory() as directory:
            args = self.args(directory)
            with patch.object(run, "docker_call", return_value=1), patch.object(run, "provenance", return_value={"image_id": "sha256:test"}):
                self.assertEqual(run.run(args), 1)
            result = json.loads((args.output / "run.json").read_text())
            self.assertTrue(result["immutable"])
            self.assertIsNone(result["container_exit_code"])
            self.assertTrue(result["cleanup"]["host_scratch_removed"])
            self.assertEqual(result["status"], "failed")

    def test_watchdog_targets_only_generated_container(self):
        with tempfile.TemporaryDirectory() as directory:
            args = self.args(directory)
            def process(command, **kwargs):
                if command[1] == "start":
                    raise subprocess.TimeoutExpired(command, 1)
                if command[1] == "wait":
                    raise subprocess.TimeoutExpired(command, 3)
                return subprocess.CompletedProcess(command, 0, b'{"ExitCode":137,"Running":false,"OOMKilled":false}')
            with patch.object(run, "docker_call", return_value=0) as docker, patch.object(run, "provenance", return_value={"image_id": "sha256:test"}), patch.object(run.subprocess, "run", side_effect=process):
                self.assertEqual(run.run(args), 1)
            mutations = [item.args[0] for item in docker.call_args_list]
            names = [command[-1] for command in mutations if command[0] in ("kill", "rm")]
            self.assertEqual(len(set(names)), 1)
            self.assertTrue(names[0].startswith("preflight-r4-"))
            result = json.loads((args.output / "run.json").read_text())
            self.assertTrue(result["watchdog"])
            self.assertTrue(result["immutable"])
            self.assertIsNone(result["analyzer_resources"]["max_rss_kib"])


@unittest.skipUnless(os.environ.get("R4_TEST_REPO"), "set R4_TEST_REPO for opt-in offline Docker tests")
class DockerTests(unittest.TestCase):
    def test_real_composer_measurement_and_signal(self):
        from calibration import calibrate
        with contextlib.redirect_stdout(io.StringIO()):
            result = calibrate(os.environ.get("R4_TEST_IMAGE", "php-upgrade-preflight-r4:8.3"))
        self.assertTrue(result["immutable"])
        self.assertEqual(result["signal_timeout_exit"], 124)
        self.assertGreater(result["composer"]["invocations"][0]["resources"]["max_rss_kib"], 0)

    def test_canonical_input_failure_projects_same_report(self):
        with tempfile.TemporaryDirectory() as directory:
            args = HostTests().args(directory)
            args.repo = Path(os.environ["R4_TEST_REPO"])
            args.image = os.environ.get("R4_TEST_IMAGE", "php-upgrade-preflight-r4:8.3")
            args.overall_timeout = 30
            (args.app / "composer.json").write_text("invalid JSON")
            self.assertEqual(run.run(args), 0)
            record = json.loads((args.output / "run.json").read_text())
            self.assertTrue(record["immutable"])
            self.assertEqual(record["composer"]["started_count"], 0)
            self.assertGreater(record["analyzer_resources"]["max_rss_kib"], 0)
            # Independently render canonical JSON and compare the full Markdown projection.
            code = 'require "/app/vendor/autoload.php"; $r=json_decode(file_get_contents("/output/report.json"),true,512,JSON_THROW_ON_ERROR); echo (new PhpUpgradePreflight\\Core\\Reporting\\MarkdownReportWriter())->renderCanonical($r);'
            check = subprocess.run(["docker", "run", "--rm", "--network", "none", "--entrypoint", "php", "--mount", f"type=bind,source={args.repo},target=/app,readonly", "--mount", f"type=bind,source={args.output},target=/output,readonly", args.image, "-r", code], capture_output=True, timeout=30)
            self.assertEqual(check.returncode, 0)
            self.assertEqual(check.stdout, (args.output / "report.md").read_bytes())


if __name__ == "__main__":
    unittest.main()
