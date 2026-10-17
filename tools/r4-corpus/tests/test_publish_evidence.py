"""Offline publisher regressions; every campaign here is synthetic test evidence."""
import copy
import csv
import gzip
import hashlib
import io
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import acquire
import campaign
import publish_evidence as publisher
import summarize
import test_evidence


TOOLS = Path(__file__).resolve().parents[1]
APPROVED_SHA256 = '1af32014f6247485d501ac6e55dc9c0e8246be38b605bc548950f9a2fdcc1092'


class PublisherTests(unittest.TestCase):
    def setUp(self):
        # Compose the existing fixture without inheriting and rediscovering its tests.
        self.fixture = test_evidence.CampaignEvidence(methodName='runTest')
        self.addCleanup(self.fixture.doCleanups)
        self.fixture.setUp()
        self.root = self.fixture.root
        self.assertEqual(Path(tempfile.gettempdir()).resolve(), self.root.resolve().parent)
        self.assertTrue(self.root.name.startswith('r4-evidence-test-'))
        self.plan = self.fixture.plan
        matrix = campaign.matrix(self.plan['manifest'])
        self.plan['runs'] = [matrix[0], next(s for s in matrix if s['probe'] == 'child-failure'), matrix[2]]
        self.cold, self.probe, self.not_run = self.plan['runs']
        self.output = self.fixture.output
        self.markdown = '# Synthétique — offline fixture\r\nNot live campaign evidence.\r\n'.encode('utf-8')
        self.canonical = {
            'resolution': {'status': 'blocked', 'scenarios': [
                {'outcome': 'blocked', 'diagnostics': [{'reused': False}, {'reused': True}]}]},
            'staged_resolution': {'status': 'unknown', 'stages': [
                {'attempts': [{'scenario': {'outcome': 'unknown', 'diagnostics': [{'cached': True}]}}]}]},
            'uncertainties': ['Synthetic fixture; no solver or target application ran.'],
        }
        self.fixture.canonical = lambda: copy.deepcopy(self.canonical)
        (self.output / 'plan.json').write_text(json.dumps(self.plan), encoding='utf-8')

    def runner(self, argv, **kwargs):
        self.fixture.runner(argv, **kwargs)
        raw = Path(argv[argv.index('--output') + 1])
        record = acquire.load_json(raw / 'run.json')
        failed = self.probe['id'] in raw.parts
        record['composer'] = {'started_count': 4, 'timed_count': 3, 'invocations': [
            {'id': 'version', 'kind': '--version', 'resources': {
                'available': True, 'elapsed_seconds': 0.0, 'max_rss_kib': 0, 'scope': 'composer_php_process'}},
            {'id': 'validate', 'kind': 'validate', 'resources': {
                'available': True, 'elapsed_seconds': 0.25, 'max_rss_kib': None, 'scope': 'composer_php_process'}},
            {'id': 'update', 'kind': 'update', 'resources': {
                'available': True, 'elapsed_seconds': 0.5, 'max_rss_kib': 123, 'scope': 'composer_php_process'}},
            {'id': 'prohibits', 'kind': 'prohibits', 'probe_applied': 'child-failure' if failed else 'normal',
             'resources': {'available': False, 'elapsed_seconds': None, 'max_rss_kib': None}},
        ]}
        if failed:
            record['status'] = 'failed'
            record['collector'].update(status='fatal', fatal_reason='php_memory_exhausted', fatal_line=12,
                                       fatal_file='[TOOL]/synthetic.php')
        (raw / 'report.md').write_bytes(self.markdown)
        record['report_bytes']['report.md'] = len(self.markdown)
        (raw / 'run.json').write_text(json.dumps(record), encoding='utf-8')
        return subprocess.CompletedProcess(argv, 1 if failed else 0)

    def seed(self, include_probe=False):
        ids = [self.cold['id']] + ([self.probe['id']] if include_probe else [])
        # The real controller seals fixture receipts; subprocess calls are replaced.
        with patch('campaign.subprocess.run', side_effect=self.runner):
            self.assertEqual(1, campaign.execute(self.output, self.plan, only=ids))

    def attempt(self, spec=None):
        return self.output / 'runs' / (spec or self.cold)['id'] / 'attempt-0001'

    def reseal(self, attempt=None):
        """Make a changed fixture hash-consistent so semantic negative controls matter."""
        attempt = attempt or self.attempt()
        receipt = acquire.load_json(attempt / 'completion.json')
        receipt['files_sha256'] = campaign.fingerprint(attempt)
        del receipt['files_sha256']['completion.json']
        (attempt / 'completion.json').write_text(json.dumps(receipt), encoding='utf-8')

    def rewrite_record(self, modify):
        attempt = self.attempt()
        record = acquire.load_json(attempt / 'raw/run.json')
        modify(record)
        (attempt / 'raw/run.json').write_text(json.dumps(record), encoding='utf-8')
        self.reseal(attempt)

    def rewrite_report(self, data, name='report.json', reseal=True):
        (self.attempt() / 'raw' / name).write_bytes(data)
        if reseal:
            self.rewrite_record(lambda record: record['report_bytes'].__setitem__(name, len(data)))

    def bundle(self):
        return publisher.prepare(self.output, TOOLS)

    def test_source_matches_independently_approved_bytes(self):
        self.assertEqual(APPROVED_SHA256, hashlib.sha256((TOOLS / 'publish_evidence.py').read_bytes()).hexdigest())

    def test_unchanged_reports_and_deterministic_hash_index(self):
        self.seed()
        expected_summary = summarize.summarize(self.output)
        bundle = self.bundle()
        self.assertEqual(bundle, self.bundle())
        self.assertEqual(expected_summary, json.loads(bundle['summary.json']))
        reports = json.loads(bundle['reports.json'])
        self.assertEqual(2, len(reports))
        for entry in reports:
            with self.subTest(filename=entry['original_filename']):
                original = (self.attempt() / 'raw' / entry['original_filename']).read_bytes()
                compressed = bundle[entry['path']]
                self.assertEqual(original, gzip.decompress(compressed))
                self.assertEqual(hashlib.sha256(original).hexdigest(), entry['original_sha256'])
                self.assertEqual(hashlib.sha256(compressed).hexdigest(), entry['gzip_sha256'])
                self.assertEqual(len(original), entry['original_bytes'])
                self.assertEqual(len(compressed), entry['gzip_bytes'])
                self.assertEqual(b'\0\0\0\0', compressed[4:8])
                self.assertEqual('published_unchanged', entry['content_status'])
        self.assertIn(b'\r\n', gzip.decompress(bundle[next(e['path'] for e in reports if e['original_filename'] == 'report.md')]))
        for name, entry in json.loads(bundle['publication.json'])['files'].items():
            self.assertEqual(hashlib.sha256(bundle[name]).hexdigest(), entry['sha256'])
            self.assertEqual(len(bundle[name]), entry['bytes'])

    def test_failed_probe_not_run_nulls_rss_and_diagnostic_entries_remain_distinct(self):
        self.seed(include_probe=True)
        bundle = self.bundle()
        summary = json.loads(bundle['summary.json'])
        self.assertEqual(['completed', 'failed', 'not_run'], [r['status'] for r in summary['latest']])
        self.assertEqual('partial', summary['status'])
        self.assertTrue(summary['latest'][1]['probe_observed'])
        self.assertEqual(1, summary['failed_attempt_count'])
        metrics = summary['latest'][0]['metrics']
        self.assertIsNone(metrics['analysis_seconds'])
        self.assertIsNone(metrics['php_heap_peak_bytes'])
        self.assertEqual(123, metrics['composer_max_individual_rss_kib'])
        self.assertFalse(metrics['composer_rss_complete'])
        self.assertEqual(4, metrics['composer_starts'])
        counts = summary['latest'][0]['report_counts']
        self.assertEqual((1, 1, 2, 1, 3), tuple(counts[k] for k in (
            'direct_scenarios', 'staged_scenarios', 'direct_diagnostic_calls', 'staged_diagnostic_calls', 'diagnostic_calls')))
        invocations = summary['latest'][0]['composer_invocations']
        self.assertEqual('composer_php_process', invocations[0]['resources']['scope'])
        self.assertEqual(0.0, invocations[0]['resources']['elapsed_seconds'])
        self.assertEqual(0, invocations[0]['resources']['max_rss_kib'])
        self.assertIsNone(invocations[1]['resources']['max_rss_kib'])
        rows = list(csv.DictReader(io.StringIO(bundle['metrics.csv'].decode('utf-8'))))
        self.assertEqual(['completed', 'failed', 'not_run'], [r['status'] for r in rows])
        self.assertEqual('null', rows[0]['analysis_seconds'])
        self.assertEqual('null', rows[2]['composer_starts'])
        self.assertEqual(['composer_php_process', 'unknown'], json.loads(rows[0]['composer_rss_scopes']))
        publication = json.loads(bundle['publication.json'])
        self.assertIn('including reused/cached entries', publication['diagnostic_entry_semantics'])
        self.assertIn(b'Direct diagnostic entries', bundle['summary.md'])
        self.assertIn(b'not unique spawned processes', bundle['README.md'])
        self.assertIn('cannot be independently rerun', publication['attestation']['private_verification'])

    def test_cr006_host_paths_in_metadata_and_canonical_content_are_rejected(self):
        self.seed()
        original = (self.attempt() / 'raw/report.json').read_bytes()
        private_root = '/srv/publisher-private-fixture'
        for location in ('metadata', 'canonical'):
            for value in ('C:/Users/alice/private', r'\\server\share\private', '/home/alice/private',
                          '/tmp/alice/private-repository/secrets.php', '/var/tmp/alice/private',
                          '/private/tmp/alice/private', '/private/var/folders/alice/private',
                          'file:///tmp/alice/private', private_root + '/secrets.php',
                          str(self.root / 'private-input.txt')):
                with self.subTest(location=location, value=value):
                    if location == 'metadata':
                        self.rewrite_record(lambda record: record['collector'].__setitem__('fatal_file', value))
                    else:
                        report = json.loads(original)
                        report['synthetic_observed_file'] = value
                        self.rewrite_report(json.dumps(report).encode('utf-8'))
                    with patch('publish_evidence.tempfile.gettempdir', return_value=private_root):
                        with self.assertRaisesRegex(publisher.PublicationError, 'host absolute path detected'):
                            self.bundle()
                    self.rewrite_record(lambda record: record['collector'].pop('fatal_file', None))
                    self.rewrite_report(original)

    def test_cr006_declared_private_input_prefix_is_rejected(self):
        self.seed()
        private_root = '/srv/declared-private-tool'
        self.plan['repo'] = private_root
        (self.output / 'plan.json').write_text(json.dumps(self.plan), encoding='utf-8')
        attempt = self.attempt()
        invocation = acquire.load_json(attempt / 'invocation.json')
        invocation['plan_sha256'] = campaign.digest(self.plan)
        (attempt / 'invocation.json').write_text(json.dumps(invocation), encoding='utf-8')
        receipt = acquire.load_json(attempt / 'completion.json')
        receipt['plan_sha256'] = campaign.digest(self.plan)
        (attempt / 'completion.json').write_text(json.dumps(receipt), encoding='utf-8')
        self.rewrite_record(lambda record: record['collector'].__setitem__('fatal_file', private_root + '/private.php'))
        with self.assertRaisesRegex(publisher.PublicationError, 'host absolute path detected'):
            self.bundle()

    def test_proc_metrics_urls_and_relative_source_paths_are_preserved(self):
        self.seed()
        self.rewrite_record(lambda record: record['collector'].__setitem__('fatal_file', '/proc/self/status'))
        report = copy.deepcopy(self.canonical)
        report['synthetic_references'] = ['own Linux /proc/self/status VmHWM',
                                          'https://github.com/example/docs',
                                          'app/Http/Requests/Users/List.php', r'Illuminate\Foundation\Http']
        data = json.dumps(report, ensure_ascii=False).encode('utf-8')
        self.rewrite_report(data)
        bundle = self.bundle()
        entry = next(e for e in json.loads(bundle['reports.json']) if e['original_filename'] == 'report.json')
        self.assertEqual(data, gzip.decompress(bundle[entry['path']]))
        self.assertIn(b'/proc/self/status', bundle['attempts.json'])

    def test_cr007_unsealed_invalid_reports_preserve_intact_partial_evidence(self):
        self.seed()
        interrupted = self.attempt(self.not_run)
        raw = interrupted / 'raw'
        raw.mkdir(parents=True)
        baseline = summarize.summarize(self.output)
        self.assertEqual('interrupted', baseline['latest'][-1]['status'])
        for filename, data, reason in (('report.json', b'{"resolution":', 'invalid_json'),
                                       ('report.json', b'\xff\xfeinvalid', 'invalid_utf8'),
                                       ('report.md', b'\xffinvalid markdown', 'invalid_utf8')):
            with self.subTest(filename=filename, reason=reason):
                path = raw / filename
                path.write_bytes(data)
                bundle = self.bundle()
                self.assertEqual(baseline, json.loads(bundle['summary.json']))
                reports = json.loads(bundle['reports.json'])
                unavailable = [e for e in reports if e['content_status'] == 'unavailable_noncanonical']
                self.assertEqual(1, len(unavailable))
                entry = unavailable[0]
                self.assertIsNone(entry['path'])
                self.assertIsNone(entry['gzip_sha256'])
                self.assertIsNone(entry['gzip_bytes'])
                self.assertEqual(reason, entry['reason'])
                self.assertEqual(len(data), entry['original_bytes'])
                self.assertEqual(hashlib.sha256(data).hexdigest(), entry['original_sha256'])
                self.assertEqual('unverified_noncanonical_content_unavailable', entry['verification'])
                intact = [e for e in reports if e['path'] is not None]
                self.assertEqual(2, len(intact))
                for canonical in intact:
                    expected = (self.attempt() / 'raw' / canonical['original_filename']).read_bytes()
                    self.assertEqual(expected, gzip.decompress(bundle[canonical['path']]))
                disposition = json.loads(bundle['attempts.json'])[-1]
                self.assertEqual('unavailable_noncanonical', disposition['reports'][0]['content_status'])
                publication = json.loads(bundle['publication.json'])
                self.assertEqual((2, 1), (publication['published_report_count'], publication['unavailable_report_count']))
                self.assertEqual(data, path.read_bytes())
                self.assertEqual(bundle, self.bundle())
                path.unlink()

    def test_verified_malformed_reports_fail_even_with_matching_inventory(self):
        self.seed()
        originals = {name: (self.attempt() / 'raw' / name).read_bytes() for name in ('report.json', 'report.md')}
        for filename, data in (('report.json', b'{"resolution":'), ('report.json', b'\xffbad'),
                               ('report.md', b'\xffinvalid markdown')):
            with self.subTest(filename=filename, data=data):
                self.rewrite_report(data, filename)
                with self.assertRaises(ValueError):
                    self.bundle()
                self.rewrite_report(originals[filename], filename)

    def test_verified_hash_mismatch_and_live_lease_fail(self):
        self.seed()
        self.rewrite_report(b'tampered without resealing', 'report.md', reseal=False)
        with self.assertRaisesRegex(ValueError, 'hash inventory'):
            self.bundle()
        self.rewrite_report(self.markdown, 'report.md')
        (self.output / 'controller.lock').write_bytes(b'')
        with self.assertRaisesRegex(publisher.PublicationError, 'controller lease'):
            self.bundle()

    def test_exclusive_output_and_incomplete_write_marker(self):
        self.seed()
        bundle = self.bundle()
        output = self.root / 'published'
        publisher.write_bundle(output, bundle)
        self.assertFalse((output / 'INCOMPLETE.txt').exists())
        self.assertEqual(set(bundle), {p.relative_to(output).as_posix() for p in output.rglob('*') if p.is_file()})
        for name, data in bundle.items():
            self.assertEqual(data, (output / name).read_bytes())
        with self.assertRaises(FileExistsError):
            publisher.write_bundle(output, bundle)
        self.assertEqual(bundle['publication.json'], (output / 'publication.json').read_bytes())
        failed = self.root / 'interrupted-write'
        original_open = Path.open
        def interrupted_open(path, mode='r', *args, **kwargs):
            if path == failed / 'summary.json' and mode == 'xb':
                raise OSError('synthetic write interruption')
            return original_open(path, mode, *args, **kwargs)
        with patch.object(Path, 'open', interrupted_open):
            with self.assertRaisesRegex(OSError, 'synthetic write interruption'):
                publisher.write_bundle(failed, bundle)
        self.assertTrue((failed / 'INCOMPLETE.txt').is_file())
        self.assertFalse((failed / 'publication.json').exists())


if __name__ == '__main__':
    unittest.main()
