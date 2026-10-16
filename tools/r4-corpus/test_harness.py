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

    def test_signal_evidence_overrides_gnu_time_zero_exit_status(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "signal.time"
            path.write_text("Command terminated by signal 15\nelapsed_seconds=1.00\nmax_rss_kib=1024\nexit_code=0\n")
            result = run.resource_stats(path)
            self.assertEqual(result["termination_kind"], "signalled")
            self.assertEqual(result["termination_signal"], 15)
            self.assertIsNone(result["exit_code"])
            self.assertEqual(result["elapsed_seconds"], 1.0)

    def test_truncated_signal_and_measurements_remain_unknown(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "signal.time"
            for signal in ("Command terminated by sig", "Command terminated by signal", "Command terminated by signal ?\n", "Command terminated by signal 1"):
                path.write_text("elapsed_seconds=1.25\nexit_code=0\n" + signal)
                result = run.resource_stats(path)
                self.assertEqual(result["termination_kind"], "unknown")
                self.assertIsNone(result["exit_code"])
                self.assertIsNone(result["termination_signal"])
            path.write_text("elapsed_seconds=1.25\nmax_rss_kib=12")
            result = run.resource_stats(path)
            self.assertIsNone(result["max_rss_kib"])
            self.assertIsNone(result["exit_code"])

    def test_default_transport_preserves_empty_home_and_public_policy_is_explicit(self):
        with tempfile.TemporaryDirectory() as directory:
            home = Path(directory)
            default = run.prepare_composer_home(home, "default")
            self.assertEqual(default["home_before_policy"], default["home_after_policy"])
            self.assertEqual(list(home.iterdir()), [])
            self.assertIsNone(default["git_environment"])
            public = run.prepare_composer_home(home, "public-https")
            config = json.loads((home / "config.json").read_text())
            self.assertEqual(config, {"config": {"github-protocols": ["https"], "use-github-api": False}})
            self.assertEqual(public["git_environment"]["GIT_CONFIG_COUNT"], "1")
            self.assertEqual(public["home_before_policy"]["shape"]["files"], 0)
            self.assertEqual(public["home_after_policy"]["shape"]["files"], 1)

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
    def test_real_gnu_time_signal_is_never_an_ordinary_zero_exit(self):
        image = os.environ.get("R4_TEST_IMAGE", "php-upgrade-preflight-r4:8.3")
        with tempfile.TemporaryDirectory() as directory:
            command = ["docker", "run", "--rm", "--network", "none", "--entrypoint", "/bin/sh", "--mount", f"type=bind,source={directory},target=/output", image, "-c", 'LC_ALL=C /usr/bin/time -o /output/signal.time -f "elapsed_seconds=%e\\nmax_rss_kib=%M\\nexit_code=%x" /bin/sh -c \'kill -TERM $$\'']
            result = subprocess.run(command, capture_output=True, timeout=30)
            self.assertEqual(result.returncode, 143)
            stats = run.resource_stats(Path(directory) / "signal.time")
            self.assertEqual(stats["termination_signal"], 15)
            self.assertIsNone(stats["exit_code"])

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

    def test_public_transport_reaches_collector_without_composer_configuration_process(self):
        with tempfile.TemporaryDirectory() as directory:
            args = HostTests().args(directory)
            args.repo = Path(os.environ["R4_TEST_REPO"])
            args.image = os.environ.get("R4_TEST_IMAGE", "php-upgrade-preflight-r4:8.3")
            args.overall_timeout = 30
            args.github_transport = "public-https"
            (args.app / "composer.json").write_text("invalid JSON")
            self.assertEqual(run.run(args), 0)
            record = json.loads((args.output / "run.json").read_text())
            self.assertEqual(record["request"]["github_transport"], "public-https")
            self.assertEqual(record["github_transport"]["home_after_policy"]["shape"]["files"], 1)
            self.assertEqual(record["composer_home_final"], record["github_transport"]["home_after_policy"])
            self.assertTrue(record["collector"]["github_https_git_environment_active"])
            self.assertEqual(record["composer"]["started_count"], 0)
            self.assertTrue(record["immutable"])
            # Git resolves the SSH-style spelling locally; --get-url does not contact GitHub.
            command = ["docker", "run", "--rm", "--network", "none", "--entrypoint", "git"]
            for key, value in record["github_transport"]["git_environment"].items():
                command += ["--env", key + "=" + value]
            command += [args.image, "ls-remote", "--get-url", "git@github.com:r4/offline.git"]
            rewrite = subprocess.run(command, capture_output=True, timeout=30)
            self.assertEqual(rewrite.returncode, 0)
            self.assertEqual(rewrite.stdout.strip(), b"https://github.com/r4/offline.git")

    def test_child_failure_probe_reaches_scenarios_after_real_metadata(self):
        with tempfile.TemporaryDirectory() as directory:
            args = HostTests().args(directory)
            args.repo = Path(os.environ["R4_TEST_REPO"])
            args.image = os.environ.get("R4_TEST_IMAGE", "php-upgrade-preflight-r4:8.3")
            args.overall_timeout = 30
            args.probe = "child-failure"
            fixture = {"name": "r4/probe", "require": {"php": ">=8.0"}, "repositories": [{"packagist.org": False}]}
            (args.app / "composer.json").write_text(json.dumps(fixture))
            lock = {"packages": [], "packages-dev": [], "aliases": [], "minimum-stability": "stable", "stability-flags": {}, "prefer-stable": False, "prefer-lowest": False, "platform": {"php": ">=8.0"}, "platform-dev": {}}
            (args.app / "composer.lock").write_text(json.dumps(lock))
            self.assertEqual(run.run(args), 0)
            record = json.loads((args.output / "run.json").read_text())
            invocations = record["composer"]["invocations"]
            metadata = [item for item in invocations if item["kind"] == "--version"]
            failures = [item for item in invocations if item["probe_applied"] == "child-failure"]
            self.assertTrue(metadata)
            self.assertTrue(all(item["resources"]["exit_code"] == 0 for item in metadata))
            self.assertTrue(failures)
            self.assertTrue(all(item["resources"]["exit_code"] == 42 for item in failures))
            self.assertTrue(record["immutable"])


if __name__ == "__main__":
    unittest.main()
