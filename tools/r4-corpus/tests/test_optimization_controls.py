"""Offline checks for explicit optimization measurement controls and mount isolation."""
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import run
import test_harness


class OptimizationControls(unittest.TestCase):
    def test_nested_source_cache_is_rejected_before_any_writable_creation(self):
        with tempfile.TemporaryDirectory() as directory:
            args = test_harness.HostTests().args(directory)
            for parent in (args.app, args.repo, args.output, args.cache):
                args.source_cache_dir = parent / 'source-facts'
                with self.subTest(parent=parent), self.assertRaises(ValueError):
                    run.validate(args)
                self.assertFalse(args.source_cache_dir.exists())
            self.assertFalse(args.output.exists())

    def test_source_cache_cannot_be_a_file(self):
        with tempfile.TemporaryDirectory() as directory:
            args = test_harness.HostTests().args(directory)
            args.source_cache_dir = Path(directory) / 'source-facts'
            args.source_cache_dir.write_text('user diagnostic', encoding='utf-8')
            with self.assertRaises(ValueError):
                run.validate(args)
            self.assertEqual(args.source_cache_dir.read_text(), 'user diagnostic')

    def test_requested_mode_and_cache_reach_only_the_explicit_child_mount(self):
        with tempfile.TemporaryDirectory() as directory:
            args = test_harness.HostTests().args(directory)
            args.analysis_mode = 'full'
            args.source_profile = True
            args.runtime_vendor = Path(directory) / 'dependencies'
            args.runtime_vendor.mkdir()
            (args.runtime_vendor / 'autoload.php').touch()
            args.source_cache_dir = Path(directory) / 'source-facts'
            commands = []

            def docker(command, **kwargs):
                commands.append(command)
                return 0

            def process(command, **kwargs):
                collector, container = test_harness.HostTests().receipt(args.output)
                run.write_record(args.output / 'collector.json', collector)
                run.write_record(args.output / 'container.json', container)
                return subprocess.CompletedProcess(command, 0)

            inspected = {'Id': 'a' * 64, 'State': {'ExitCode': 0, 'Running': False, 'OOMKilled': False}}
            with patch.object(run, 'docker_call', side_effect=docker), patch.object(run, 'provenance', return_value={'image_id': 'sha256:test'}), patch.object(run, 'owned_container', side_effect=[inspected, inspected, inspected, None]), patch.object(run.subprocess, 'run', side_effect=process):
                self.assertEqual(run.run(args), 0)
            create = next(command for command in commands if command[0] == 'create')
            self.assertIn('R4_ANALYSIS_MODE=full', create)
            self.assertIn('R4_SOURCE_PROFILE=1', create)
            self.assertIn(f'type=bind,source={args.runtime_vendor.resolve()},target=/app/vendor,readonly', create)
            self.assertIn('R4_SOURCE_CACHE_DIR=/source-cache', create)
            self.assertIn(f'type=bind,source={args.source_cache_dir.resolve()},target=/source-cache', create)
            self.assertNotIn('R4_DISABLE_SOURCE_CACHE=1', create)
            receipt = run.read_json(args.output / 'run.json')
            self.assertTrue(receipt['immutable'])
            self.assertTrue(receipt['cleanup']['container_removed'])
            self.assertEqual(receipt['source_cache_before'], receipt['source_cache_after'])
            self.assertNotIn(str(args.source_cache_dir), json.dumps(receipt['request']))
            self.assertNotIn(str(args.runtime_vendor), json.dumps(receipt['request']))

    def test_runtime_vendor_cannot_overlap_a_writable_cache(self):
        with tempfile.TemporaryDirectory() as directory:
            args = test_harness.HostTests().args(directory)
            args.runtime_vendor = args.cache
            args.cache.mkdir(exist_ok=True)
            (args.cache / 'autoload.php').touch()
            with self.assertRaisesRegex(ValueError, 'non-nested'):
                run.validate(args)
            self.assertFalse(args.output.exists())

    def test_disabled_source_cache_does_not_create_a_shared_mount(self):
        with tempfile.TemporaryDirectory() as directory:
            args = test_harness.HostTests().args(directory)
            args.disable_source_cache = True
            commands = []

            def docker(command, **kwargs):
                commands.append(command)
                return 1

            with patch.object(run, 'docker_call', side_effect=docker), patch.object(run, 'provenance', return_value={'image_id': 'sha256:test'}), patch.object(run, 'owned_container', return_value=None):
                self.assertEqual(run.run(args), 1)
            create = next(command for command in commands if command[0] == 'create')
            self.assertIn('R4_DISABLE_SOURCE_CACHE=1', create)
            self.assertFalse(any('target=/source-cache' in value for value in create))
            self.assertNotIn('R4_ANALYSIS_MODE=standard', create)


if __name__ == '__main__':
    unittest.main()
