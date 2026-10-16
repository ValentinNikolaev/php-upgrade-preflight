"""Offline host tests; real Docker checks require R4_TEST_REPO explicitly."""
import contextlib
import hashlib
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import unittest
import uuid
from unittest.mock import patch

import run


class HostTests(unittest.TestCase):
    def receipt(self, root):
        canonical = {'resolution': {'status': 'unknown'}, 'staged_resolution': {'status': 'unknown', 'execution_state': 'skipped', 'stop_reason': None}, 'transition': {'framework_guidance': []}}
        (root / 'report.json').write_text(json.dumps(canonical))
        (root / 'report.md').write_text('# Upgrade report\n')
        collector = {'status': 'completed', 'report_states': {'resolution': 'unknown', 'staged_resolution': 'unknown', 'staged_execution_state': 'skipped', 'staged_stop_reason': None, 'framework_guidance': []}, 'report_artifacts': {}}
        for name in ('report.json', 'report.md'):
            content = (root / name).read_bytes()
            collector['report_artifacts'][name] = {'bytes': len(content), 'sha256': hashlib.sha256(content).hexdigest()}
        container = {'status': 'completed', 'collector_exit_code': 0, 'scratch_leftovers_before_container_exit': {'files': 0}, 'sanitized_stderr_bytes': 0}
        return collector, container

    def test_completion_requires_both_exact_renderings_and_finalization(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name in ('report.json', 'report.md'):
                for content in (None, b'', b'{', b'# truncated'):
                    collector, container = self.receipt(root)
                    self.assertTrue(run.report_evidence(root, collector, container))
                    if content is None:
                        (root / name).unlink()
                    else:
                        (root / name).write_bytes(content)
                    self.assertFalse(run.report_evidence(root, collector, container))
            collector, container = self.receipt(root)
            for missing in ('status', 'collector_exit_code', 'scratch_leftovers_before_container_exit', 'sanitized_stderr_bytes'):
                partial = container.copy()
                partial.pop(missing)
                self.assertFalse(run.report_evidence(root, collector, partial))
            collector['report_states']['resolution'] = 'made_up'
            self.assertFalse(run.report_evidence(root, collector, container))
            collector, container = self.receipt(root)
            (root / 'report.json').write_bytes(b'{}')
            collector['report_artifacts']['report.json'] = {'bytes': 2, 'sha256': hashlib.sha256(b'{}').hexdigest()}
            self.assertFalse(run.report_evidence(root, collector, container))

    def test_zero_exit_with_partial_reports_is_a_failed_full_attempt(self):
        with tempfile.TemporaryDirectory() as directory:
            args = self.args(directory)
            def process(command, **kwargs):
                (args.output / 'composer').mkdir(exist_ok=True)
                (args.output / 'composer/1.start').write_text('')
                (args.output / 'composer/2.start').write_text('123.45\nupdate\nnormal\n')
                collector, container = self.receipt(args.output)
                (args.output / 'report.json').write_text('{')
                run.write_record(args.output / 'collector.json', collector)
                run.write_record(args.output / 'container.json', container)
                return subprocess.CompletedProcess(command, 0)
            inspected = {'Id': 'a' * 64, 'State': {'ExitCode': 0, 'Running': False, 'OOMKilled': False}}
            with patch.object(run, 'docker_call', return_value=0), patch.object(run, 'provenance', return_value={'image_id': 'sha256:test'}), patch.object(run, 'owned_container', side_effect=[inspected, inspected, inspected, None]), patch.object(run.subprocess, 'run', side_effect=process):
                self.assertEqual(run.run(args), 1)
            result = run.read_json(args.output / 'run.json')
            self.assertEqual(result['status'], 'failed')
            self.assertEqual(result['composer']['started_count'], 2)
            self.assertEqual(result['composer']['invocations'][0]['start_status'], 'malformed')
            self.assertEqual((args.output / 'report.json').read_text(), '{')
            self.assertTrue(result['cleanup']['container_removed'])
            self.assertTrue(result['cleanup']['host_scratch_removed'])

    def owner(self, root):
        token = uuid.uuid4().hex
        name = 'preflight-r4-' + token
        state = Path(tempfile.gettempdir()).resolve() / name
        state.mkdir()
        self.addCleanup(lambda: state.exists() and run.shutil.rmtree(state))
        owner = {'version': 1, 'task_token': token, 'container_name': name, 'state_path': str(state), 'host_pid': os.getpid(), 'lifecycle': 'prepared', 'creation_started': False, 'create_settled': False, 'cleanup': {'container_removed': False, 'host_scratch_removed': False}}
        path = root / 'ownership.json'
        run.atomic_record(path, owner)
        return path, owner, state

    def test_recovery_refuses_live_host_and_uncertain_create(self):
        with tempfile.TemporaryDirectory() as directory:
            path, owner, state = self.owner(Path(directory))
            with patch.object(run, 'owned_container') as inspect:
                self.assertEqual(run.recover(path), 1)
                inspect.assert_not_called()
            self.assertTrue(state.exists())
            owner.update(lifecycle='creating', creation_started=True)
            run.atomic_record(path, owner)
            with patch.object(run, 'host_exited', return_value=True), patch.object(run, 'owned_container', return_value=None), patch.object(run, 'docker_call') as mutate:
                self.assertEqual(run.recover(path), 1)
                mutate.assert_not_called()
            result = run.read_json(path)
            self.assertEqual(result['lifecycle'], 'unknown')
            self.assertFalse(result['cleanup']['container_removed'])
            self.assertEqual(result['cleanup']['retained_state_path'], str(state))

    def test_recovery_refuses_foreign_name_label_and_unsafe_temp_path(self):
        with tempfile.TemporaryDirectory() as directory:
            path, owner, state = self.owner(Path(directory))
            foreign = [{'Name': '/' + owner['container_name'], 'Id': 'a' * 64, 'Config': {'Labels': {'preflight.r4.task': 'foreign'}}}]
            with patch.object(run, 'host_exited', return_value=True), patch.object(run.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0, json.dumps(foreign).encode())), patch.object(run, 'docker_call') as mutate:
                self.assertEqual(run.recover(path), 1)
                mutate.assert_not_called()
            owner['state_path'] = str(Path(directory).resolve())
            run.atomic_record(path, owner)
            with patch.object(run, 'docker_call') as mutate:
                self.assertEqual(run.recover(path), 1)
                mutate.assert_not_called()
            self.assertTrue(state.exists())
            self.assertTrue(Path(directory).exists())

    def test_ownership_precedes_snapshot_and_failed_setup_is_recoverable(self):
        with tempfile.TemporaryDirectory() as directory:
            args = self.args(directory)
            original = run.snapshot
            def snapshot(root):
                owner = run.read_json(args.output / 'ownership.json')
                self.assertEqual(owner['host_pid'], os.getpid())
                self.assertIn(owner['lifecycle'], ('prepared', 'cleaning'))
                return original(root)
            with patch.object(run, 'snapshot', side_effect=snapshot), patch.object(run, 'provenance', side_effect=ValueError('setup failed')), patch.object(run, 'owned_container', return_value=None):
                self.assertEqual(run.run(args), 1)
            owner = run.read_json(args.output / 'ownership.json')
            self.assertEqual(owner['lifecycle'], 'cleaned')
            self.assertTrue(owner['cleanup']['host_scratch_removed'])

    def test_partial_starts_keep_all_invocations_and_unknown_fields(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'composer').mkdir()
            for name, text in {'empty': '', 'timestamp': '123.45\n', 'nonfloat': 'oops\nupdate\nnormal\n', 'valid': '123.45\nupdate\nnormal\n'}.items():
                (root / ('composer/' + name + '.start')).write_text(text)
            run.write_record(root / 'composer/valid.metrics.json', {'scope': 'composer_php_process', 'elapsed_seconds': 1.25, 'max_rss_kib': 2048})
            result = run.composer_records(root)
            self.assertEqual(result['started_count'], 4)
            self.assertEqual(result['timed_count'], 1)
            records = {row['id']: row for row in result['invocations']}
            for name in ('empty', 'timestamp', 'nonfloat'):
                self.assertEqual(records[name]['start_status'], 'malformed')
                self.assertIsNone(records[name]['resources']['elapsed_seconds'])
            self.assertIsNone(records['empty']['kind'])
            self.assertIsNone(records['empty']['started_unix_seconds'])
            self.assertIsNone(records['timestamp']['kind'])
            self.assertIsNone(records['nonfloat']['started_unix_seconds'])
            self.assertEqual(records['valid']['start_status'], 'complete')
            self.assertEqual(records['valid']['resources']['elapsed_seconds'], 1.25)
            self.assertEqual(records['valid']['resources']['max_rss_kib'], 2048)

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
            with patch.object(run, "docker_call", return_value=1), patch.object(run, 'owned_container', return_value=None), patch.object(run, "provenance", return_value={"image_id": "sha256:test"}):
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
            container = {'Id': 'a' * 64, 'State': {'ExitCode': 137, 'Running': False, 'OOMKilled': False}}
            with patch.object(run, "docker_call", return_value=0) as docker, patch.object(run, 'owned_container', side_effect=[container, container, container, None]), patch.object(run, "provenance", return_value={"image_id": "sha256:test"}), patch.object(run.subprocess, "run", side_effect=process):
                self.assertEqual(run.run(args), 1)
            mutations = [item.args[0] for item in docker.call_args_list]
            names = [command[-1] for command in mutations if command[0] == 'kill']
            self.assertEqual(len(set(names)), 1)
            self.assertEqual(names[0], 'a' * 64)
            self.assertIn(['rm', '--force', 'a' * 64], mutations)
            result = json.loads((args.output / "run.json").read_text())
            self.assertTrue(result["watchdog"])
            self.assertTrue(result["immutable"])
            self.assertIsNone(result["analyzer_resources"]["max_rss_kib"])


@unittest.skipUnless(os.environ.get("R4_TEST_REPO"), "set R4_TEST_REPO for opt-in offline Docker tests")
class DockerTests(unittest.TestCase):
    def test_real_runner_kill_recovery_at_setup_create_and_cleanup(self):
        script = '''import json, sys, time
from pathlib import Path
import run
args = run.arguments(json.loads(sys.argv[1]))
phase = sys.argv[2]
def pause():
    (args.output / 'checkpoint').write_text(phase)
    time.sleep(60)
if phase == 'setup':
    original = run.snapshot
    def snapshot(root):
        if root == args.app:
            pause()
        return original(root)
    run.snapshot = snapshot
elif phase in ('create', 'calibration'):
    original = run.docker_call
    def docker(command, timeout=30):
        code = original(command, timeout)
        if command[0] == 'create' and code == 0:
            pause()
        return code
    run.docker_call = docker
else:
    original = run.remove_owned_state
    def remove(path, owner):
        pause()
        return original(path, owner)
    run.remove_owned_state = remove
sys.exit(run.run(args))
'''
        harness = Path(__file__).resolve().parent
        for phase in ('setup', 'create', 'cleanup', 'calibration'):
            with self.subTest(phase=phase), tempfile.TemporaryDirectory() as directory:
                args = HostTests().args(directory)
                args.repo = Path(os.environ['R4_TEST_REPO'])
                args.image = os.environ.get('R4_TEST_IMAGE', 'php-upgrade-preflight-r4:8.3')
                args.overall_timeout = 30
                args.calibrate = phase == 'calibration'
                (args.app / 'composer.json').write_text('invalid JSON')
                argv = []
                for key, value in vars(args).items():
                    if key != 'calibrate':
                        argv.extend(['--' + key.replace('_', '-'), str(value)])
                if args.calibrate:
                    argv.append('--calibrate')
                child = subprocess.Popen([sys.executable, '-B', '-c', script, json.dumps(argv), phase], cwd=harness, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
                ownership = args.output / 'ownership.json'
                try:
                    deadline = time.monotonic() + 45
                    while not (args.output / 'checkpoint').exists() and child.poll() is None and time.monotonic() < deadline:
                        time.sleep(0.05)
                    self.assertTrue((args.output / 'checkpoint').exists(), 'child failed before checkpoint')
                    child.kill()
                    child.wait(timeout=5)
                    before = run.read_json(ownership)
                    state = Path(before['state_path'])
                    self.assertTrue(state.exists())
                    recovery = subprocess.run([sys.executable, '-B', str(harness / 'run.py'), 'recover', '--ownership', str(ownership.resolve())], capture_output=True, timeout=45)
                    self.assertEqual(recovery.returncode, 0, recovery.stderr.decode())
                    after = run.read_json(ownership)
                    self.assertEqual(after['lifecycle'], 'cleaned')
                    self.assertTrue(after['cleanup']['container_removed'])
                    self.assertTrue(after['cleanup']['host_scratch_removed'])
                    self.assertFalse(state.exists())
                    self.assertIsNone(run.owned_container(after))
                    self.assertEqual(run.recover(ownership), 0)
                finally:
                    if child.poll() is None:
                        child.kill()
                        child.wait(timeout=5)
                    child.stderr.close()
                    if ownership.exists():
                        run.recover(ownership)

    def test_checked_php_writes_reject_real_short_and_failed_writes(self):
        harness = Path(__file__).resolve().parent
        image = os.environ.get('R4_TEST_IMAGE', 'php-upgrade-preflight-r4:8.3')
        with tempfile.TemporaryDirectory() as directory:
            code = 'require "/harness/runtime.php"; try { @r4Write("/output", "invalid"); exit(3); } catch (RuntimeException $expected) {} try { @r4Write("/output/short", str_repeat("x", 4096)); exit(4); } catch (RuntimeException $expected) {}'
            command = ['docker', 'run', '--rm', '--network', 'none', '--entrypoint', '/bin/sh', '--mount', f'type=bind,source={harness},target=/harness,readonly', '--mount', f'type=bind,source={directory},target=/output', image, '-c', 'trap "" 25; ulimit -f 1; exec php -r "$1"', 'r4-write', code]
            result = subprocess.run(command, capture_output=True, timeout=15)
            self.assertEqual(result.returncode, 0, (result.stderr + result.stdout).decode())
            size = (Path(directory) / 'short').stat().st_size
            self.assertGreater(size, 0)
            self.assertLess(size, 4096)

    def test_inline_calibration_uses_durable_owner_and_separate_metrics(self):
        with tempfile.TemporaryDirectory() as directory:
            args = HostTests().args(directory)
            args.repo = Path(os.environ['R4_TEST_REPO'])
            args.image = os.environ.get('R4_TEST_IMAGE', 'php-upgrade-preflight-r4:8.3')
            args.overall_timeout = 30
            args.calibrate = True
            (args.app / 'composer.json').write_text('invalid JSON')
            with contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(run.run(args), 0)
            record = run.read_json(args.output / 'run.json')
            self.assertEqual(record['calibration']['composer']['started_count'], 3)
            self.assertEqual(record['calibration']['composer']['timed_count'], 2)
            self.assertEqual(record['composer']['started_count'], 0)
            self.assertEqual(run.read_json(args.output / 'ownership.json')['lifecycle'], 'cleaned')

    def test_installed_symfony_timeout_and_both_stop_paths(self):
        repo = Path(os.environ['R4_TEST_REPO'])
        harness = Path(__file__).resolve().parent
        image = os.environ.get('R4_TEST_IMAGE', 'php-upgrade-preflight-r4:8.3')
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'composer').mkdir()
            (root / 'legacy.sh').write_bytes(b'#!/bin/sh\nexec /usr/bin/time -o /output/legacy.time -f "elapsed_seconds=%e" sleep 3600\n')
            command = ['docker', 'run', '--rm', '--init', '--network', 'none', '--tmpfs', '/scratch:rw,exec,mode=1777', '--entrypoint', '/bin/sh', '--mount', f'type=bind,source={repo},target=/app,readonly', '--mount', f'type=bind,source={harness},target=/harness,readonly', '--mount', f'type=bind,source={root},target=/output', image, '-c']
            for legacy in (True, False):
                wrapper = '/output/legacy.sh' if legacy else '/harness/composer.sh'
                script = f'mkdir -p /scratch; cp {wrapper} /scratch/wrapper; chmod 700 /scratch/wrapper; php /harness/test-cancellation.php /scratch/wrapper' + (' legacy' if legacy else '')
                result = subprocess.run(command + [script], capture_output=True, timeout=30)
                self.assertEqual(result.returncode, 0, (result.stderr + result.stdout).decode())
                rows = json.loads(result.stdout)
                self.assertEqual(set(rows), {'timeout', 'stop-zero', 'stop-normal'})
                for row in rows.values():
                    self.assertEqual(bool(row['survivors']), legacy)
            interrupted = [row for row in run.composer_records(root)['invocations'] if row['probe_applied'] == 'timeout']
            self.assertEqual(len(interrupted), 3)
            self.assertTrue(all(row['resources']['elapsed_seconds'] is None and row['resources']['max_rss_kib'] is None for row in interrupted))

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
        self.check_scenario_probe('child-failure')

    def test_real_scenario_timeouts_keep_unknown_metrics_and_clean_scratch(self):
        self.check_scenario_probe('timeout')

    def check_scenario_probe(self, probe):
        with tempfile.TemporaryDirectory() as directory:
            args = HostTests().args(directory)
            args.repo = Path(os.environ["R4_TEST_REPO"])
            args.image = os.environ.get("R4_TEST_IMAGE", "php-upgrade-preflight-r4:8.3")
            args.overall_timeout = 30
            args.probe = probe
            fixture = {"name": "r4/probe", "require": {"php": ">=8.0"}, "repositories": [{"packagist.org": False}]}
            (args.app / "composer.json").write_text(json.dumps(fixture))
            lock = {"packages": [], "packages-dev": [], "aliases": [], "minimum-stability": "stable", "stability-flags": {}, "prefer-stable": False, "prefer-lowest": False, "platform": {"php": ">=8.0"}, "platform-dev": {}}
            (args.app / "composer.lock").write_text(json.dumps(lock))
            self.assertEqual(run.run(args), 0)
            record = json.loads((args.output / "run.json").read_text())
            invocations = record["composer"]["invocations"]
            metadata = [item for item in invocations if item["kind"] == "--version"]
            failures = [item for item in invocations if item['probe_applied'] == probe]
            self.assertTrue(metadata)
            self.assertTrue(all(item['resources']['available'] and item['resources']['exit_code'] is None for item in metadata))
            self.assertTrue(failures)
            self.assertTrue(all(item['resources']['exit_code'] is None for item in failures))
            if probe == 'timeout':
                self.assertTrue(all(not item['resources']['available'] and item['resources']['max_rss_kib'] is None and item['resources']['elapsed_seconds'] is None for item in failures))
            else:
                self.assertTrue(all(item['resources']['available'] for item in failures))
            self.assertEqual(record['container']['scratch_leftovers_before_container_exit']['files'], 0)
            self.assertTrue(record['cleanup']['container_removed'])
            self.assertTrue(record['cleanup']['host_scratch_removed'])
            self.assertTrue(record["immutable"])


if __name__ == "__main__":
    unittest.main()
