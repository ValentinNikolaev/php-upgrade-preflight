"""Offline adversarial archives and campaign evidence failure/resume checks."""
import copy
import hashlib
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import acquire
import campaign
import summarize


class Archives(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='r4-evidence-test-')
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.project = {'id': 'synthetic', 'composer_json_sha256': hashlib.sha256(b'{}').hexdigest(),
                        'composer_lock_sha256': hashlib.sha256(b'{}').hexdigest(), 'skipped_archive_links': []}

    def archive(self, extra=()):
        path = self.root / 'source.tar.gz'
        with tarfile.open(path, 'w:gz') as stream:
            for name, kind, data in [('root/composer.json', tarfile.REGTYPE, b'{}'),
                                     ('root/composer.lock', tarfile.REGTYPE, b'{}'), *extra]:
                entry = tarfile.TarInfo(name)
                entry.type = kind
                entry.size = len(data) if kind == tarfile.REGTYPE else 0
                if kind in (tarfile.SYMTYPE, tarfile.LNKTYPE):
                    entry.linkname = '../../outside'
                stream.addfile(entry, io.BytesIO(data) if entry.size else None)
        self.project['archive_sha256'] = acquire.sha256(path)
        return path

    def test_traversal_windows_alias_and_absolute_paths_are_rejected(self):
        for name in ('root/../../outside', '/absolute', 'root/C:escape', 'root/dir\\escape',
                     'root/CON.txt', 'root/trailing.', 'root/a/../b'):
            with self.subTest(name=name):
                archive = self.archive([(name, tarfile.REGTYPE, b'bad')])
                with self.assertRaises(ValueError):
                    acquire.extract(archive, self.root / ('out-' + str(len(list(self.root.iterdir())))), self.project)
        self.assertFalse((self.root.parent / 'outside').exists())

    def test_duplicate_case_alias_and_hardlinks_are_rejected(self):
        for entry in [('root/COMPOSER.JSON', tarfile.REGTYPE, b'{}'),
                      ('root/hardlink', tarfile.LNKTYPE, b''), ('root/fifo', tarfile.FIFOTYPE, b'')]:
            with self.subTest(entry=entry):
                with self.assertRaises(ValueError):
                    acquire.extract(self.archive([entry]), self.root / ('out-' + str(len(list(self.root.iterdir())))), self.project)

    def test_only_exact_recorded_symlinks_are_omitted(self):
        path = self.archive([('root/public/storage', tarfile.SYMTYPE, b'')])
        with self.assertRaises(ValueError):
            acquire.extract(path, self.root / 'unrecorded', self.project)
        self.project['skipped_archive_links'] = ['public/storage']
        result = acquire.extract(path, self.root / 'recorded', self.project)
        self.assertEqual(['public/storage'], result['omitted_symlinks'])
        self.assertFalse((self.root / 'recorded/public/storage').exists())

    def test_missing_declared_omission_and_limits_fail(self):
        path = self.archive()
        self.project['skipped_archive_links'] = ['missing']
        with self.assertRaises(ValueError):
            acquire.extract(path, self.root / 'missing', self.project)
        self.project['skipped_archive_links'] = []
        with self.assertRaises(ValueError):
            acquire.extract(path, self.root / 'too-large', self.project, max_total=3)
        with self.assertRaises(ValueError):
            acquire.extract(path, self.root / 'too-many', self.project, max_members=1)

    def test_hashes_are_checked_and_existing_output_survives(self):
        path = self.archive()
        self.project['composer_lock_sha256'] = '0' * 64
        with self.assertRaises(ValueError):
            acquire.extract(path, self.root / 'bad-lock', self.project)
        original = self.root / 'existing'
        original.mkdir()
        (original / 'sentinel').write_text('user data')
        with self.assertRaises(FileExistsError):
            acquire.extract(path, original, self.project)
        self.assertEqual('user data', (original / 'sentinel').read_text())
        self.project['archive_sha256'] = '0' * 64
        with self.assertRaises(ValueError):
            acquire.extract(path, self.root / 'bad-archive', self.project)
        self.assertFalse((self.root / 'bad-archive').exists())

    def test_member_cannot_replace_omitted_symlink_with_directory(self):
        archive = self.archive([('root/public/storage', tarfile.SYMTYPE, b''),
                                ('root/public/storage', tarfile.DIRTYPE, b'')])
        self.project['skipped_archive_links'] = ['public/storage']
        with self.assertRaises(ValueError):
            acquire.extract(archive, self.root / 'out', self.project)

    def test_implicit_parent_case_collision_is_rejected(self):
        archive = self.archive([('root/tests/Fixtures/a.php', tarfile.REGTYPE, b'a'),
                                ('root/tests/fixtures/b.php', tarfile.REGTYPE, b'b')])
        with self.assertRaisesRegex(ValueError, 'case-collision'):
            acquire.extract(archive, self.root / 'out', self.project)

    def test_declared_original_case_variants_preserve_every_file(self):
        for label, directory, entries in (
            ('koel', 'tests/songs/subdir', [('tests/songs/subdir/no-name.MP3', b'upper'),
                                           ('tests/songs/subdir/no-name.mp3', b'lower')]),
            ('monica', 'tests', [('tests/Fixtures/a.yaml', b'upper'), ('tests/fixtures/b.yaml', b'lower')]),
        ):
            with self.subTest(label=label):
                archive = self.archive([('root/' + name, tarfile.REGTYPE, data) for name, data in entries])
                self.project['case_sensitive_directories'] = [directory]
                destination = self.root / label
                result = acquire.extract(archive, destination, self.project)
                self.assertEqual(4, result['files'])
                for name, data in entries:
                    self.assertEqual(data, (destination / name).read_bytes())
                names = {p.relative_to(destination).as_posix() for p in destination.rglob('*') if p.is_file()}
                self.assertEqual({'composer.json', 'composer.lock', *(name for name, _ in entries)}, names)

    @unittest.skipUnless(os.name == 'nt', 'Windows-specific fsutil failure contract')
    def test_case_sensitivity_failure_is_actionable_and_does_not_extract_files(self):
        self.project['case_sensitive_directories'] = ['tests']
        archive = self.archive([('root/tests/Fixtures/a', tarfile.REGTYPE, b'a')])
        with patch('acquire.subprocess.run', return_value=subprocess.CompletedProcess([], 1)):
            with self.assertRaisesRegex(ValueError, 'case-sensitive filesystem'):
                acquire.extract(archive, self.root / 'out', self.project)
        self.assertFalse((self.root / 'out/composer.json').exists())

    def test_unsafe_or_missing_case_sensitive_declarations_are_rejected(self):
        for declaration in ('../escape', '/absolute', 'tests\\bad', 'tests/', 'CON'):
            with self.subTest(declaration=declaration):
                self.project['case_sensitive_directories'] = [declaration]
                with self.assertRaises(ValueError):
                    acquire.extract(self.archive(), self.root / ('out-' + str(len(list(self.root.iterdir())))), self.project)
        self.project['case_sensitive_directories'] = ['absent']
        with self.assertRaisesRegex(ValueError, 'absent from archive'):
            acquire.extract(self.archive(), self.root / 'missing-declaration', self.project)

    def test_acquisition_records_full_tree_and_verifies_supplied_pin(self):
        archive = self.archive([('root/app.php', tarfile.REGTYPE, b'<?php'),
                                ('root/empty', tarfile.DIRTYPE, b'')])
        result = acquire.extract(archive, self.root / 'out', self.project)
        snapshot = acquire.tree_snapshot(self.root / 'out')
        self.assertEqual('r4-tree-v1', result['source_snapshot_algorithm'])
        self.assertEqual(snapshot['sha256'], result['source_snapshot_sha256'])
        self.assertEqual({'files': 3, 'directories': 1, 'symlinks': 0, 'bytes': 9,
                          'php_files': 1, 'php_bytes': 5}, snapshot['shape'])
        self.project.update(source_snapshot_algorithm='r4-tree-v1', source_snapshot_sha256=snapshot['sha256'])
        acquire.verify_app(self.root / 'out', self.project, require_source=True)
        (self.root / 'out/app.php').write_bytes(b'<?php changed')
        with self.assertRaisesRegex(ValueError, 'source snapshot mismatch'):
            acquire.verify_app(self.root / 'out', self.project, require_source=True)
        self.project['source_snapshot_sha256'] = '0' * 64
        with self.assertRaisesRegex(ValueError, 'source snapshot mismatch'):
            acquire.extract(archive, self.root / 'wrong-pin', self.project)

    def test_tree_digest_format_and_symlink_text_without_following(self):
        root = self.root / 'tree'
        (root / 'z').mkdir(parents=True)
        (root / 'a').write_bytes(b'bytes')
        (root / 'z/b.php').write_bytes(b'<?php')
        entries = [('a', 'file', hashlib.sha256(b'bytes').digest()), ('z', 'directory', b''),
                   ('z/b.php', 'file', hashlib.sha256(b'<?php').digest())]
        expected = hashlib.sha256(b''.join(json.dumps([name, kind], ensure_ascii=True).encode() + b'\0' + data + b'\0'
                                            for name, kind, data in entries)).hexdigest()
        self.assertEqual(expected, acquire.tree_snapshot(root)['sha256'])
        link = root / 'link'
        try:
            link.symlink_to('../missing')
        except OSError:
            self.skipTest('host does not permit symlink creation')
        entries.insert(1, ('link', 'link', b'../missing'))
        expected = hashlib.sha256(b''.join(json.dumps([name, kind], ensure_ascii=True).encode() + b'\0' + data + b'\0'
                                            for name, kind, data in entries)).hexdigest()
        self.assertEqual(expected, acquire.tree_snapshot(root)['sha256'])
        self.assertEqual(1, acquire.tree_snapshot(root)['shape']['symlinks'])


class CampaignEvidence(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='r4-evidence-test-')
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.apps = self.root / 'apps'
        (self.apps / 'lychee').mkdir(parents=True)
        for filename in ('composer.json', 'composer.lock'):
            (self.apps / 'lychee' / filename).write_bytes(b'{}')
        project = {'id': 'lychee', 'repository': 'test/synthetic', 'commit': 'a' * 40,
                   'archive_sha256': 'a' * 64, 'composer_json_sha256': hashlib.sha256(b'{}').hexdigest(),
                   'composer_lock_sha256': hashlib.sha256(b'{}').hexdigest(),
                   'requests': {key: {'target_major': 13, 'from_php': '8.3.0', 'target_php': '8.3.0'} for key in ('nearest', 'longest')}}
        project.update(source_snapshot_algorithm='r4-tree-v1',
                       source_snapshot_sha256=acquire.tree_snapshot(self.apps / 'lychee')['sha256'])
        self.plan = {'campaign_version': 1, 'manifest': {'projects': [project]},
                     'projects_sha256': 'a' * 64, 'apps': str(self.apps), 'repo': str(self.root / 'repo'),
                     'harness': str(self.root / 'synthetic-run.py'), 'image': 'sha256:' + 'a' * 64,
                     'budget_seconds': 32400, 'scenario_timeout_seconds': 45, 'diagnostic_timeout_seconds': 15,
                     'overall_timeout_seconds': 540, 'composer_memory_limit': '1024M', 'github_transport': 'default',
                     'repo_commit': 'a' * 40, 'dependency_lock_sha256': 'a' * 64,
                     'tool_runtime_tree_sha256': 'a' * 64, 'harness_files_sha256': {}}
        self.plan['runs'] = campaign.matrix(self.plan['manifest'])[:2]
        self.output = self.root / 'campaign'
        self.output.mkdir()
        acquire.write_new(self.output / 'plan.json', self.plan)

    def runner(self, argv, **kwargs):
        raw = Path(argv[argv.index('--output') + 1])
        raw.mkdir()
        spec = next(s for s in self.plan['runs'] if s['id'] in raw.parts)
        record = self.record(spec)
        acquire.write_new(raw / 'run.json', record)
        acquire.write_new(raw / 'report.json', self.canonical())
        record['report_bytes']['report.json'] = (raw / 'report.json').stat().st_size
        (raw / 'run.json').write_text(json.dumps(record), encoding='utf-8')
        return subprocess.CompletedProcess(argv, 0)

    def record(self, spec):
        snapshot = acquire.tree_snapshot(self.apps / 'lychee')
        return {'status': 'completed', 'immutable': True, 'input_before': snapshot, 'input_after': snapshot,
                'request': {**spec['request'], 'memory_limit': spec['memory_limit'], 'network': spec['network'],
                            'probe': spec['probe'], 'image': self.plan['image'], 'composer_memory_limit': '1024M',
                            'scenario_timeout': 45, 'diagnostic_timeout': 15, 'overall_timeout': 540, 'github_transport': 'default'},
                'provenance': {'tool_git_head': 'a' * 40, 'tool_composer_lock_sha256': 'a' * 64,
                               'tool_runtime_tree_sha256': 'a' * 64, 'harness_files_sha256': {}, 'image_id': self.plan['image']},
                'cleanup': {'container_removed': True, 'host_scratch_removed': True},
                'report_bytes': {'report.json': None, 'report.md': None},
                'collector': {'status': 'completed', 'report_states': {'resolution': 'blocked', 'staged_resolution': 'unknown'}},
                'composer': {'started_count': 2, 'timed_count': 1,
                             'invocations': [{'id': 'one', 'resources': {'available': True, 'max_rss_kib': 123}},
                                             {'id': 'two', 'resources': {'available': False, 'max_rss_kib': None}}]}}

    def canonical(self):
        return {'resolution': {'status': 'blocked', 'scenarios': []},
                'staged_resolution': {'status': 'unknown', 'stages': []}, 'uncertainties': []}

    def test_real_manifest_predeclares30_primary6_probe_and_supplemental(self):
        manifest = acquire.load_json(Path(__file__).resolve().parents[3] / 'docs/readiness/corpus-2026-10/projects.json')
        runs = campaign.matrix(manifest)
        self.assertEqual(30, sum(r['category'] == 'primary' for r in runs))
        self.assertEqual(6, sum(r['category'] == 'probe' for r in runs))
        self.assertEqual(1, sum(r['category'] == 'supplemental' for r in runs))
        lychee = next(r for r in runs if r['id'] == 'lychee-longest-cold')
        self.assertTrue(lychee['cold_repeat'])
        self.assertEqual(37, len({r['id'] for r in runs}))

    def test_completed_blocked_runs_resume_without_invocation_and_nulls_remain_null(self):
        with patch('campaign.subprocess.run', side_effect=self.runner) as run:
            self.assertEqual(0, campaign.execute(self.output, self.plan))
            self.assertEqual(2, run.call_count)
        with patch('campaign.subprocess.run', side_effect=AssertionError('must not rerun')):
            self.assertEqual(0, campaign.execute(self.output, self.plan))
        summary = summarize.summarize(self.output)
        self.assertEqual('completed', summary['status'])
        metrics = summary['latest'][0]['metrics']
        self.assertIsNone(metrics['php_heap_peak_bytes'])
        self.assertIsNone(metrics['analysis_seconds'])
        self.assertFalse(metrics['composer_rss_complete'])
        self.assertEqual(123, metrics['composer_max_individual_rss_kib'])
        self.assertIn('null', summarize.markdown(summary))

    def test_failure_preserves_attempt_and_counts_even_without_report(self):
        def fail(argv, **kwargs):
            raw = Path(argv[argv.index('--output') + 1]); raw.mkdir()
            spec = self.plan['runs'][0]
            record = self.record(spec)
            record.update(status='failed', collector=None, composer=None)
            acquire.write_new(raw / 'run.json', record)
            return subprocess.CompletedProcess(argv, 1)
        with patch('campaign.subprocess.run', side_effect=fail):
            self.assertEqual(1, campaign.execute(self.output, self.plan))
        first = self.output / 'runs/lychee-nearest-cold/attempt-0001/completion.json'
        before = first.read_bytes()
        with patch('campaign.subprocess.run', side_effect=self.runner):
            self.assertEqual(0, campaign.execute(self.output, self.plan))
        self.assertEqual(before, first.read_bytes())
        result = summarize.summarize(self.output)
        self.assertEqual(3, result['attempt_count'])
        self.assertEqual(1, result['failed_attempt_count'])
        self.assertIsNone(result['attempts'][0]['metrics']['composer_starts'])

    def test_hash_tampering_and_changed_provenance_refuse_resume(self):
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan)
        report = self.output / 'runs/lychee-nearest-cold/attempt-0001/raw/report.json'
        report.write_text('{}')
        with self.assertRaisesRegex(ValueError, 'hash inventory'):
            summarize.summarize(self.output)
        plan = copy.deepcopy(self.plan); plan['image'] = 'sha256:' + 'b' * 64
        with self.assertRaisesRegex(ValueError, 'declared plan'):
            campaign.execute(self.output, plan)

    def test_timeout_retained_and_blocks_unverified_retry(self):
        with patch('campaign.subprocess.run', side_effect=subprocess.TimeoutExpired('synthetic', 600)):
            self.assertEqual(1, campaign.execute(self.output, self.plan))
        self.assertEqual(1, summarize.summarize(self.output)['failed_attempt_count'])
        with self.assertRaisesRegex(ValueError, 'safety review'):
            campaign.execute(self.output, self.plan)

    def test_mutated_cache_prevents_warm_reuse(self):
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan, only=['lychee-nearest-cold'])
        cache = next((self.output / 'caches').iterdir())
        (cache / 'unattributed').write_text('changed')
        with self.assertRaisesRegex(ValueError, 'warm cache changed'):
            campaign.execute(self.output, self.plan)
        self.assertEqual([], campaign.attempt_dirs(self.output, 'lychee-nearest-warm'))
        # Repair the known test mutation: the same next attempt remains usable.
        (cache / 'unattributed').unlink()
        with patch('campaign.subprocess.run', side_effect=self.runner):
            self.assertEqual(0, campaign.execute(self.output, self.plan))

    def test_unsupported_probe_is_partial_and_never_pass(self):
        spec = copy.deepcopy(self.plan['runs'][0]); spec.update(category='probe', probe='child-failure')
        self.plan['runs'] = [spec]
        (self.output / 'plan.json').write_text(json.dumps(self.plan))
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan)
        result = summarize.summarize(self.output)
        self.assertEqual('partial', result['status'])
        self.assertEqual('unavailable', result['latest'][0]['probe_outcome'])

    def test_inconsistent_counts_rejected_even_after_rehashing(self):
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan)
        attempt = self.output / 'runs/lychee-nearest-cold/attempt-0001'
        raw = acquire.load_json(attempt / 'raw/run.json'); raw['composer']['started_count'] = 100
        (attempt / 'raw/run.json').write_text(json.dumps(raw))
        receipt = acquire.load_json(attempt / 'completion.json')
        receipt['files_sha256'] = campaign.fingerprint(attempt)
        del receipt['files_sha256']['completion.json']
        (attempt / 'completion.json').write_text(json.dumps(receipt))
        with self.assertRaisesRegex(ValueError, 'Composer starts'):
            summarize.summarize(self.output)

    def test_missing_completed_evidence_is_never_sealed_completed(self):
        for field in ('input_before', 'input_after', 'collector', 'composer'):
            with self.subTest(field=field):
                output = self.root / ('missing-' + field)
                output.mkdir()
                acquire.write_new(output / 'plan.json', self.plan)
                def malformed(argv, **kwargs):
                    result = self.runner(argv, **kwargs)
                    path = Path(argv[argv.index('--output') + 1]) / 'run.json'
                    record = acquire.load_json(path)
                    record[field] = None
                    if field.startswith('input_'):
                        record['input_before'] = record['input_after'] = None
                    path.write_text(json.dumps(record))
                    return result
                with patch('campaign.subprocess.run', side_effect=malformed):
                    self.assertEqual(1, campaign.execute(output, self.plan))
                receipt = acquire.load_json(output / 'runs/lychee-nearest-cold/attempt-0001/completion.json')
                self.assertEqual('failed', receipt['status'])

    def test_empty_canonical_report_is_never_sealed_completed(self):
        def malformed(argv, **kwargs):
            result = self.runner(argv, **kwargs)
            raw = Path(argv[argv.index('--output') + 1])
            (raw / 'report.json').write_text('{}')
            record = acquire.load_json(raw / 'run.json')
            record['collector']['report_states'] = {}
            record['report_bytes']['report.json'] = 2
            (raw / 'run.json').write_text(json.dumps(record))
            return result
        with patch('campaign.subprocess.run', side_effect=malformed):
            self.assertEqual(1, campaign.execute(self.output, self.plan))
        self.assertEqual('partial', summarize.summarize(self.output)['status'])

    def test_source_change_blocks_warm_before_allocating_attempt(self):
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan, only=['lychee-nearest-cold'])
        (self.apps / 'lychee/changed.php').write_text('<?php // changed')
        with self.assertRaisesRegex(ValueError, 'source.*mismatch'):
            campaign.execute(self.output, self.plan)
        self.assertEqual([], campaign.attempt_dirs(self.output, 'lychee-nearest-warm'))

    def test_failed_warm_cache_write_does_not_poison_retry(self):
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan, only=['lychee-nearest-cold'])
        cold = self.output / 'runs/lychee-nearest-cold/attempt-0001'
        receipt = acquire.load_json(cold / 'completion.json')
        cache = Path(receipt['cache_path'])
        before = campaign.fingerprint(cache)
        def fail_warm(argv, **kwargs):
            result = self.runner(argv, **kwargs)
            (Path(argv[argv.index('--cache') + 1]) / 'warm-write').write_text('partial')
            raw = Path(argv[argv.index('--output') + 1]) / 'run.json'
            record = acquire.load_json(raw)
            record['status'] = 'failed'
            raw.write_text(json.dumps(record))
            return subprocess.CompletedProcess(argv, 1)
        with patch('campaign.subprocess.run', side_effect=fail_warm):
            self.assertEqual(1, campaign.execute(self.output, self.plan))
        self.assertEqual(before, campaign.fingerprint(cache))
        failed = self.output / 'runs/lychee-nearest-warm/attempt-0001/completion.json'
        preserved = failed.read_bytes()
        with patch('campaign.subprocess.run', side_effect=self.runner):
            self.assertEqual(0, campaign.execute(self.output, self.plan))
        self.assertEqual(preserved, failed.read_bytes())
        self.assertEqual(before, campaign.fingerprint(cache))
        self.assertEqual('completed', summarize.summarize(self.output)['status'])

    def test_truncated_attempt_does_not_hide_intact_evidence(self):
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan, only=['lychee-nearest-cold'])
        interrupted = self.output / 'runs/lychee-nearest-warm/attempt-0001'
        interrupted.mkdir(parents=True)
        (interrupted / 'invocation.json').write_text('{"plan_sha256":')
        summary = summarize.summarize(self.output)
        self.assertEqual('partial', summary['status'])
        self.assertEqual('completed', summary['latest'][0]['status'])
        self.assertEqual('interrupted', summary['latest'][1]['status'])

    def test_snapshot_and_canonical_shapes_are_required_for_completion(self):
        spec = self.plan['runs'][0]
        raw = self.root / 'raw'
        raw.mkdir()
        acquire.write_new(raw / 'report.json', self.canonical())
        record = self.record(spec)
        record['report_bytes']['report.json'] = (raw / 'report.json').stat().st_size
        for key, value in (('sha256', 'not-a-hash'), ('sha256', 'b' * 64), ('shape', {}),
                           ('composer.json_sha256', None), ('composer.lock_sha256', 'b' * 64)):
            with self.subTest(key=key, value=value):
                malformed = copy.deepcopy(record)
                malformed['input_before'][key] = malformed['input_after'][key] = value
                self.assertIsNotNone(summarize.record_error(malformed, raw, self.plan, spec))
        for section in ('resolution', 'staged_resolution'):
            for replacement in (None, {}, {'status': None}, {'status': 'bogus'}):
                with self.subTest(section=section, replacement=replacement):
                    report = self.canonical()
                    report[section] = replacement
                    (raw / 'report.json').write_text(json.dumps(report))
                    record['report_bytes']['report.json'] = (raw / 'report.json').stat().st_size
                    self.assertIsNotNone(summarize.record_error(record, raw, self.plan, spec))

    def test_legacy_unpinned_source_is_not_promoted_by_controller(self):
        for key in ('source_snapshot_algorithm', 'source_snapshot_sha256'):
            del self.plan['manifest']['projects'][0][key]
        with patch('campaign.subprocess.run', side_effect=AssertionError('must not launch')):
            with self.assertRaisesRegex(ValueError, 'source snapshot pin'):
                campaign.execute(self.output, self.plan)
        self.assertFalse((self.output / 'runs').exists())

    def test_run_budget_reserves_external_timeout_and_recovery_before_launch(self):
        self.plan['budget_seconds'] = 650
        with patch('campaign.subprocess.run', side_effect=AssertionError('must reserve cleanup before launch')):
            self.assertEqual(1, campaign.execute(self.output, self.plan))
        self.assertFalse((self.output / 'runs').exists())

    def test_both_harness_snapshots_must_match_intake_even_when_equal(self):
        def wrong_source(argv, **kwargs):
            result = self.runner(argv, **kwargs)
            path = Path(argv[argv.index('--output') + 1]) / 'run.json'
            record = acquire.load_json(path)
            for key in ('input_before', 'input_after'):
                record[key]['sha256'] = 'b' * 64
            path.write_text(json.dumps(record))
            return result
        with patch('campaign.subprocess.run', side_effect=wrong_source):
            self.assertEqual(1, campaign.execute(self.output, self.plan))
        receipt = acquire.load_json(self.output / 'runs/lychee-nearest-cold/attempt-0001/completion.json')
        self.assertEqual('failed', receipt['status'])
        self.assertIn('pinned manifest', receipt['validation_error'])

    def test_direct_staged_and_diagnostic_counts_are_separate(self):
        report = self.canonical()
        report['resolution']['scenarios'] = [{'outcome': 'blocked', 'diagnostics': [1, 2]},
                                              {'outcome': 'unknown', 'diagnostics': []}]
        report['staged_resolution']['stages'] = [
            {'attempts': [{'scenario': {'outcome': 'feasible', 'diagnostics': [1, 2, 3]}}, {'scenario': None}]},
            {'attempts': [{'scenario': {'outcome': 'blocked', 'diagnostics': [1]}}]}]
        report['uncertainties'] = ['one', 'two']
        with patch.object(self, 'canonical', return_value=report), patch('campaign.subprocess.run', side_effect=self.runner):
            self.assertEqual(0, campaign.execute(self.output, self.plan))
        counts = summarize.summarize(self.output)['latest'][0]['report_counts']
        self.assertEqual({'direct_scenarios': 2, 'staged_scenarios': 2, 'stages': 2,
                          'direct_diagnostic_calls': 2, 'staged_diagnostic_calls': 4, 'diagnostic_calls': 6,
                          'scenario_outcomes': {'blocked': 2, 'unknown': 1, 'feasible': 1}, 'uncertainties': 2}, counts)

    def test_timeout_recovery_precedes_cache_fingerprint_and_allows_safe_retry(self):
        active = {'value': False}
        fingerprint = campaign.fingerprint
        def checked_fingerprint(path):
            if Path(path).parent.name == 'caches':
                self.assertFalse(active['value'], 'cache inspected before owned container stopped')
            return fingerprint(path)
        def timeout_then_recover(argv, **kwargs):
            if 'recover' in argv:
                self.assertTrue(active['value'])
                ownership = Path(argv[argv.index('--ownership') + 1])
                owner = acquire.load_json(ownership)
                owner.update(lifecycle='cleaned', cleanup={'container_removed': True, 'host_scratch_removed': True})
                ownership.write_text(json.dumps(owner))
                active['value'] = False
                return subprocess.CompletedProcess(argv, 0)
            raw = Path(argv[argv.index('--output') + 1])
            raw.mkdir()
            acquire.write_new(raw / 'ownership.json', {'lifecycle': 'created', 'cleanup': {}})
            (raw / 'run.json').write_text('{"status":')
            active['value'] = True
            raise subprocess.TimeoutExpired(argv, 600)
        with patch('campaign.subprocess.run', side_effect=timeout_then_recover) as run, patch('campaign.fingerprint', side_effect=checked_fingerprint):
            self.assertEqual(1, campaign.execute(self.output, self.plan))
            self.assertEqual(2, run.call_count)
        receipt = acquire.load_json(self.output / 'runs/lychee-nearest-cold/attempt-0001/completion.json')
        self.assertTrue(receipt['external_timeout'])
        self.assertTrue(receipt['cleanup_verified'])
        self.assertEqual({}, receipt['cache_after_sha256'])
        with patch('campaign.subprocess.run', side_effect=self.runner):
            self.assertEqual(0, campaign.execute(self.output, self.plan))

    def test_unverified_cleanup_never_inspects_cache_and_retains_actionable_failure(self):
        for outcome in ('timeout', 'nonzero', 'zero'):
            with self.subTest(outcome=outcome):
                output = self.root / outcome
                output.mkdir()
                acquire.write_new(output / 'plan.json', self.plan)
                active = {'value': False}
                fingerprint = campaign.fingerprint
                def checked_fingerprint(path):
                    if Path(path).parent.name == 'caches':
                        self.assertFalse(active['value'], 'unsafe cache inspection')
                    return fingerprint(path)
                def unsafe(argv, **kwargs):
                    if 'recover' in argv:
                        return subprocess.CompletedProcess(argv, 1)
                    result = self.runner(argv, **kwargs)
                    raw = Path(argv[argv.index('--output') + 1])
                    record = acquire.load_json(raw / 'run.json')
                    record['cleanup']['container_removed'] = False
                    (raw / 'run.json').write_text(json.dumps(record))
                    acquire.write_new(raw / 'ownership.json', {'lifecycle': 'unknown', 'cleanup': {}})
                    active['value'] = True
                    if outcome == 'timeout':
                        raise subprocess.TimeoutExpired(argv, 600)
                    return subprocess.CompletedProcess(argv, 1 if outcome == 'nonzero' else 0)
                with patch('campaign.subprocess.run', side_effect=unsafe), patch('campaign.fingerprint', side_effect=checked_fingerprint):
                    self.assertEqual(1, campaign.execute(output, self.plan))
                receipt = acquire.load_json(output / 'runs/lychee-nearest-cold/attempt-0001/completion.json')
                self.assertFalse(receipt['cleanup_verified'])
                self.assertIsNone(receipt['cache_after_sha256'])
                self.assertIn('ownership and recovery logs', receipt['recovery']['error'])
                with self.assertRaisesRegex(ValueError, 'safety review'):
                    campaign.execute(output, self.plan)

    def test_truncated_completion_and_unsealed_run_are_partial(self):
        with patch('campaign.subprocess.run', side_effect=self.runner):
            campaign.execute(self.output, self.plan)
        warm = self.output / 'runs/lychee-nearest-warm/attempt-0001'
        for filename in ('completion.json', 'raw/run.json'):
            with self.subTest(filename=filename):
                (warm / filename).write_text('{"incomplete":')
                if filename.startswith('raw/'):
                    (warm / 'completion.json').unlink()
                summary = summarize.summarize(self.output)
                self.assertEqual('partial', summary['status'])
                self.assertEqual('completed', summary['latest'][0]['status'])
                self.assertEqual('interrupted', summary['latest'][1]['status'])


if __name__ == '__main__':
    unittest.main()
