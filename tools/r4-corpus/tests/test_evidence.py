"""Offline adversarial archives and campaign evidence failure/resume checks."""
import copy
import hashlib
import io
import json
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
        acquire.write_new(raw / 'report.json', {'resolution': {'status': 'blocked'}, 'staged_resolution': {'status': 'unknown'}})
        record['report_bytes']['report.json'] = (raw / 'report.json').stat().st_size
        (raw / 'run.json').write_text(json.dumps(record), encoding='utf-8')
        return subprocess.CompletedProcess(argv, 0)

    def record(self, spec):
        snapshot = {'sha256': 'a' * 64, 'composer.json_sha256': self.plan['manifest']['projects'][0]['composer_json_sha256'],
                    'composer.lock_sha256': self.plan['manifest']['projects'][0]['composer_lock_sha256']}
        return {'status': 'completed', 'immutable': True, 'input_before': snapshot, 'input_after': snapshot,
                'request': {**spec['request'], 'memory_limit': spec['memory_limit'], 'network': spec['network'],
                            'probe': spec['probe'], 'image': self.plan['image'], 'composer_memory_limit': '1024M',
                            'scenario_timeout': 45, 'diagnostic_timeout': 15, 'overall_timeout': 540, 'github_transport': 'default'},
                'provenance': {'tool_git_head': 'a' * 40, 'tool_composer_lock_sha256': 'a' * 64,
                               'tool_runtime_tree_sha256': 'a' * 64, 'harness_files_sha256': {}, 'image_id': self.plan['image']},
                'cleanup': {'container_removed': True, 'host_scratch_removed': True},
                'report_bytes': {'report.json': None, 'report.md': None},
                'collector': {'report_states': {'resolution': 'blocked', 'staged_resolution': 'unknown'}},
                'composer': {'started_count': 2, 'timed_count': 1,
                             'invocations': [{'id': 'one', 'resources': {'available': True, 'max_rss_kib': 123}},
                                             {'id': 'two', 'resources': {'available': False, 'max_rss_kib': None}}]}}

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


if __name__ == '__main__':
    unittest.main()
