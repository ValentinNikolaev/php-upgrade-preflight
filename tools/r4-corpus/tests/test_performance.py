"""Tiny synthetic admission/protocol controls; never invokes Docker or a real corpus."""
import copy
import csv
import json
from pathlib import Path
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import acquire
import campaign
import performance as perf
import run


class ProtocolTests(unittest.TestCase):
    def test_optional_analysis_profile_is_numeric_completed_and_exact_scope(self):
        profile = {'status': 'completed', 'total_ms': 2.0, 'timings_ms': {k: 0.1 for k in
            ('source_capture', 'source_recheck_after_scan', 'input_recheck_final', 'ownership',
             'source_impact', 'stage_assessment', 'risk_effort', 'assembly')}}
        self.assertTrue(perf.valid_analysis_profile(profile))
        for value in ('/private/path', float('inf'), True):
            profile['timings_ms']['source_capture'] = value
            self.assertFalse(perf.valid_analysis_profile(profile))
        profile['timings_ms']['source_capture'] = 0.1
        profile['status'] = 'failed'
        self.assertFalse(perf.valid_analysis_profile(profile))

    def test_stage_counts_fixed_replicates_and_balanced_order(self):
        self.assertEqual(40, len(perf.schedule('cache-off', 1)))
        self.assertEqual(3, len(perf.schedule('profiles', 1)))
        final = perf.schedule('final', 2)
        self.assertEqual(40, len(final))
        self.assertEqual(40, len({e['id'] for e in final}))
        self.assertEqual({'independent'}, {e['temperature'] for e in final})
        self.assertEqual(20, len(perf.schedule('final-third', 1)))
        self.assertEqual({3}, {e['repetition'] for e in perf.schedule('final-third', 1)})
        self.assertNotEqual(final[0]['role'], final[20]['role'])
        for stage, count in [('final', 1), ('final', 3), ('cache-off', 2), ('profiles', True)]:
            with self.subTest(stage=stage, count=count), self.assertRaises(ValueError):
                perf.schedule(stage, count)

    def test_third_pair_trigger_is_predeclared_and_all_project(self):
        rows = [{'project': p, 'role': role, 'analysis_s': duration}
                for p in perf.PROJECTS for role, duration in [('cache-off', 10), ('optimized', 8)]
                for _ in range(2)]
        self.assertIsNone(perf.extension_reason(rows))
        rows[0]['analysis_s'] = 20
        self.assertIn('spread', perf.extension_reason(rows))
        for r in rows:
            r['analysis_s'] = 10 if r['role'] == 'cache-off' else 9.5
        self.assertIn('median difference', perf.extension_reason(rows))


class ExportBoundaryTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='preflight-public-export-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.cohort = self.root / 'cohort'
        self.runtime = self.root / 'runtime'
        self.protected = [self.root / name for name in ('cohort', 'runtime', 'donor/vendor',
                          'reference', 'prior-final', 'source', 'seed', 'original-harness', 'original-tooling')]
        for directory in self.protected:
            directory.mkdir(parents=True)
            (directory / 'sealed.txt').write_text('immutable synthetic input\n', encoding='utf-8')
        self.proof = self.root / 'proof.json'
        self.proof.write_text('{}', encoding='utf-8')
        self.plan = {'output': str(self.cohort), 'stage': 'cache-off', 'instrumented': False,
                     'variants': {role: {'repo': str(self.runtime)} for role in perf.STAGES['cache-off']},
                     'cases': [{'app': str(self.root / 'source'), 'seed': str(self.root / 'seed'),
                                'seed_proof': str(self.proof)}],
                     'reference_plan': str(self.root / 'reference/plan.json'),
                     'prior_final': {'prior_final_plan': str(self.root / 'prior-final/plan.json')},
                     'protected_export_paths': [str(self.root / name) for name in
                                                ('original-harness', 'original-tooling')]}
        self.plan['variants']['cache-off']['vendor'] = str(self.root / 'donor/vendor')
        self.plan_path = self.cohort / 'plan.json'
        acquire.write_new(self.plan_path, self.plan)
        self.rows = [dict(entry, analysis_s=1.0) for entry in perf.schedule('cache-off', 1)]
        for name in ('run', 'Popen', 'check_output'):
            process = patch.object(perf.subprocess, name, side_effect=AssertionError('no subprocess'))
            process.start()
            self.addCleanup(process.stop)

    def test_export_rejects_runtime_destination_without_creating_parents_or_changing_seals(self):
        before = campaign.fingerprint(self.root)
        with patch.object(perf, 'verified_rows', return_value=self.rows):
            with self.assertRaisesRegex(ValueError, 'protected'):
                perf.export(self.plan_path, self.runtime / 'missing-parent/derived')
        self.assertFalse((self.runtime / 'missing-parent').exists())
        self.assertEqual(before, campaign.fingerprint(self.root))

    def test_all_protected_roots_and_lexical_traversal_reject_without_writes(self):
        before = campaign.fingerprint(self.root)
        targets = [p / 'missing-parent/derived' for p in self.protected]
        targets += [self.proof, self.proof / 'derived', self.cohort, self.root,
                    self.root / 'not-created/../runtime/derived']
        with patch.object(perf, 'verified_rows', return_value=self.rows):
            for target in targets:
                with self.subTest(target=target), self.assertRaises(ValueError):
                    perf.export(self.plan_path, target)
                self.assertEqual(before, campaign.fingerprint(self.root))
        self.assertFalse((self.root / 'not-created').exists())
        self.assertTrue(all(not (p / 'missing-parent').exists() for p in self.protected))

    def test_mandatory_roots_do_not_depend_on_optional_protected_list(self):
        for paths in ([], [str(self.root / 'unrelated')]):
            self.plan['protected_export_paths'] = paths
            self.plan_path.write_text(json.dumps(self.plan), encoding='utf-8')
            with patch.object(perf, 'verified_rows', return_value=self.rows):
                for root in self.protected[:-2]:
                    with self.subTest(paths=paths, root=root), self.assertRaisesRegex(ValueError, 'protected'):
                        perf.export(self.plan_path, root / 'derived')
        del self.plan['protected_export_paths']
        self.plan_path.write_text(json.dumps(self.plan), encoding='utf-8')
        with patch.object(perf, 'verified_rows', return_value=self.rows), self.assertRaisesRegex(ValueError, 'protected'):
            perf.export(self.plan_path, self.proof / 'derived')

    def test_destination_containing_a_missing_protected_input_is_rejected(self):
        self.plan['cases'][0]['app'] = str(self.root / 'future-parent/source')
        self.plan_path.write_text(json.dumps(self.plan), encoding='utf-8')
        with patch.object(perf, 'verified_rows', return_value=self.rows), self.assertRaisesRegex(ValueError, 'protected'):
            perf.export(self.plan_path, self.root / 'future-parent')
        self.assertFalse((self.root / 'future-parent').exists())

    def test_frozen_controller_root_is_mandatory_even_with_empty_additional_list(self):
        frozen = self.root / 'frozen-tooling'
        frozen.mkdir()
        self.plan['protected_export_paths'] = []
        self.plan_path.write_text(json.dumps(self.plan), encoding='utf-8')
        with patch.object(perf, '__file__', str(frozen / 'performance.py')), \
                patch.object(perf, 'verified_rows', return_value=self.rows):
            with self.assertRaisesRegex(ValueError, 'protected'):
                perf.export(self.plan_path, frozen / 'missing-parent/derived')
        self.assertFalse((frozen / 'missing-parent').exists())

    def test_fresh_sibling_is_allowed_and_existing_outputs_are_preserved(self):
        before = campaign.fingerprint(self.root)
        target = self.root / 'derived'
        with patch.object(perf, 'verified_rows', return_value=self.rows):
            perf.export(self.plan_path, target)
            with (target / 'measurements.csv').open(newline='', encoding='utf-8') as stream:
                self.assertEqual(40, len(list(csv.DictReader(stream))))
            self.assertTrue((target / 'results.md').is_file())
            exported = campaign.fingerprint(self.root)
            for existing in (target, self.runtime / 'sealed.txt'):
                with self.assertRaisesRegex(ValueError, 'fresh'):
                    perf.export(self.plan_path, existing)
                self.assertEqual(exported, campaign.fingerprint(self.root))
        self.assertEqual(before, {k: v for k, v in exported.items() if not k.startswith('derived/')})

    def test_failed_verification_creates_no_export_or_parent(self):
        with patch.object(perf, 'verified_rows', side_effect=ValueError('invalid seals')):
            with self.assertRaisesRegex(ValueError, 'invalid seals'):
                perf.export(self.plan_path, self.root / 'missing-parent/derived')
        self.assertFalse((self.root / 'missing-parent').exists())

    def test_directory_symlink_ancestor_cannot_bypass_boundary(self):
        alias = self.root / 'alias'
        try:
            alias.symlink_to(self.runtime, target_is_directory=True)
        except (OSError, NotImplementedError) as failure:
            self.skipTest('directory symlinks unavailable: ' + str(failure))
        self.addCleanup(alias.unlink)
        with patch.object(perf, 'verified_rows', return_value=self.rows):
            for target in (alias, alias / 'derived', alias / 'missing-parent/derived'):
                with self.subTest(target=target), self.assertRaises(ValueError):
                    perf.export(self.plan_path, target)
        self.assertFalse((self.runtime / 'missing-parent').exists())

    def test_dangling_final_symlink_is_rejected_without_creating_its_target(self):
        alias = self.root / 'dangling'
        destination = self.root / 'not-created'
        try:
            alias.symlink_to(destination, target_is_directory=True)
        except (OSError, NotImplementedError) as failure:
            self.skipTest('directory symlinks unavailable: ' + str(failure))
        self.addCleanup(alias.unlink)
        with patch.object(perf, 'verified_rows', return_value=self.rows), self.assertRaisesRegex(ValueError, 'fresh'):
            perf.export(self.plan_path, alias)
        self.assertFalse(destination.exists())

    def test_native_windows_junction_ancestor_and_dangling_final_alias_reject(self):
        try:
            from _winapi import CreateJunction
        except ImportError:
            self.skipTest('native Windows junction creation unavailable')
        alias = self.root / 'junction'
        CreateJunction(str(self.runtime), str(alias))
        self.addCleanup(alias.rmdir)
        with patch.object(perf, 'verified_rows', return_value=self.rows):
            for target in (alias, alias / 'derived', alias / 'missing-parent/derived'):
                with self.subTest(target=target), self.assertRaises(ValueError):
                    perf.export(self.plan_path, target)
            self.assertFalse((self.runtime / 'missing-parent').exists())
            alias.rmdir()
            destination = self.root / 'not-created'
            destination.mkdir()
            CreateJunction(str(destination), str(alias))
            destination.rmdir()
            with self.assertRaisesRegex(ValueError, 'fresh'):
                perf.export(self.plan_path, alias)
            self.assertFalse(destination.exists())
            vendor = self.runtime / 'vendor'
            CreateJunction(str(self.root / 'donor/vendor'), str(vendor))
            self.addCleanup(vendor.rmdir)
            del self.plan['variants']['cache-off']['vendor']
            self.plan['protected_export_paths'] = []
            self.plan_path.write_text(json.dumps(self.plan), encoding='utf-8')
            with self.assertRaisesRegex(ValueError, 'protected'):
                perf.export(self.plan_path, self.root / 'donor/vendor/derived')
            self.assertFalse((self.root / 'donor/vendor/derived').exists())


class AdmissionTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='preflight-performance-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve() / 'cohort'
        self.entry = perf.schedule('final', 2)[0]
        self.output = self.root / 'runs' / self.entry['id']
        self.output.mkdir(parents=True)
        self.proof = self.root / 'seed.json'
        acquire.write_new(self.proof, {'policy': 'runtime-user-copy-v1', 'cold_receipt_sha256': 'a' * 64,
                                      'files_sha256': {}, 'directories': []})
        self.case = {'project': 'crater', 'app': str(self.root / 'app'), 'seed': str(self.root / 'cold'),
                     'seed_proof': str(self.proof), 'seed_proof_sha256': acquire.sha256(self.proof),
                     'harness_input': {'sha256': 'b' * 64},
                     'request': {'target_major': 9, 'from_php': '7.4.0', 'target_php': '8.1.0'}}
        self.plan = {'output': str(self.root), 'stage': 'final', 'repetitions': 2, 'instrumented': False,
                     'reference_plan': str(self.root.parent / 'reference/plan.json'),
                     'controller_sha256': acquire.sha256(Path(perf.__file__)), 'image': 'sha256:' + 'd' * 64,
                     'variants': {role: {'repo': str(self.root / role), 'head': 'e' * 40}
                                  for role in ('cache-off', 'optimized')},
                     'provenance': {role: {'tool_git_head': 'e' * 40} for role in ('cache-off', 'optimized')},
                     'runtime_class_files_sha256': {role: {'runtime': 'a' * 64} for role in ('cache-off', 'optimized')},
                     'cases': [self.case], 'schedule': perf.schedule('final', 2)}
        self.report = {'resolution': {'status': 'blocked'},
                       'staged_resolution': {'status': 'blocked', 'execution_state': 'evaluated', 'stop_reason': 'stage_blocked'},
                       'transition': {'framework_guidance': []},
                       'source_inventory': [{'file': 'app/Example.php', 'symbol': 'Example', 'usage_type': 'import', 'line': 2}],
                       'uncertainties': ['Source scanning reached a test safety limit (source-limit-1).']}
        self.case['expected_semantics'] = perf.semantics(self.report)
        self.seed = {'status': 'completed', 'entries_owned': True, 'destination_empty': True,
                     'policy': 'runtime-user-copy-v1', 'owner_uid': 1000, 'owner_gid': 1000, 'elapsed_seconds': 0.01,
                     'expected_proof_sha256': self.case['seed_proof_sha256'], 'cold_receipt_sha256': 'a' * 64,
                     'source_before_sha256': {}, 'source_after_sha256': {},
                     'destination_before_analysis_sha256': {}, 'directories': []}
        self.collector = {'status': 'completed', 'analysis_seconds': 1.25,
                          'runtime_class_files_sha256': {'runtime': 'a' * 64},
                          'source_profile_instrumented': False, 'source_profile': None, 'source_cache_policy': 'tool_default',
                          'cache_seed_verified': True, 'cache_seed_verification_seconds': 0.01, 'cache_before_analysis_sha256': {},
                          'summary_bytes': 12, 'summary_lines': 1,
                          'report_states': {'resolution': 'blocked', 'staged_resolution': 'blocked',
                            'staged_execution_state': 'evaluated', 'staged_stop_reason': 'stage_blocked', 'framework_guidance': []}}
        namespace = run.arguments(perf.arguments(self.plan, self.entry))
        request = {k: v for k, v in vars(namespace).items() if k not in
                   ('app', 'repo', 'output', 'cache', 'cache_seed', 'cache_seed_proof', 'source_cache_dir', 'runtime_vendor')}
        self.record = {'status': 'completed', 'request': request, 'immutable': True,
                       'input_before': self.case['harness_input'], 'input_after': self.case['harness_input'],
                       'provenance': self.plan['provenance'][self.entry['role']], 'container_exit_code': 0,
                       'watchdog': False, 'oom_killed': False,
                       'cleanup': {'container_removed': True, 'host_scratch_removed': True},
                       'collector': self.collector, 'composer': {'started_count': 5},
                       'container': {'status': 'completed', 'collector_exit_code': 0,
                         'scratch_leftovers_before_container_exit': {'files': 0}, 'sanitized_stderr_bytes': 0},
                       'cache_seed_expected_sha256': self.case['seed_proof_sha256'], 'cache_seed': self.seed}
        self.persist()

    def persist(self):
        (self.output / 'report.json').write_text(json.dumps(self.report), encoding='utf-8')
        (self.output / 'report.md').write_text('# Synthetic report\n', encoding='utf-8')
        (self.output / 'summary.txt').write_bytes(b'test result\n')
        self.collector['report_artifacts'] = {name: {'bytes': (self.output / name).stat().st_size,
           'sha256': acquire.sha256(self.output / name)} for name in ('report.json', 'report.md')}
        (self.output / 'run.json').write_text(json.dumps(self.record), encoding='utf-8')
        (self.output / 'cache-seed.json').write_text(json.dumps(self.seed), encoding='utf-8')
        (self.output / 'ownership.json').write_text(json.dumps({'lifecycle': 'cleaned', 'cleanup': self.record['cleanup']}), encoding='utf-8')
        self.receipt = {'plan_sha256': campaign.digest(self.plan), 'entry': self.entry,
                        'controller_sha256': self.plan['controller_sha256'],
                        'argv': perf.arguments(self.plan, self.entry), 'semantics': self.case['expected_semantics'],
                        'controller_wall_seconds': 2.0}
        self.reseal()

    def reseal(self):
        files = campaign.fingerprint(self.output)
        files.pop('completion.json', None)
        self.receipt['files_sha256'] = files
        (self.output / 'completion.json').write_text(json.dumps(self.receipt), encoding='utf-8')

    def check(self):
        return perf.verify_completed(run, self.plan, self.entry)

    def test_actual_harness_guard_accepts_only_complete_sealed_fixture(self):
        record, _ = self.check()
        self.assertEqual(1.25, record['collector']['analysis_seconds'])
        (self.output / 'report.md').write_text('tampered')
        with self.assertRaisesRegex(ValueError, 'seal'):
            self.check()
        self.reseal()
        with self.assertRaisesRegex(ValueError, 'canonical artifacts'):
            self.check()

    def test_rehashed_foreign_identity_request_provenance_and_seed_fail(self):
        for field, value in [('request', {}), ('provenance', {'tool_git_head': 'f' * 40}),
                             ('input_before', {'sha256': 'f' * 64}), ('cache_seed_expected_sha256', 'f' * 64)]:
            with self.subTest(field=field):
                original = copy.deepcopy(self.record[field])
                self.record[field] = value
                self.persist()
                with self.assertRaises(ValueError):
                    self.check()
                self.record[field] = original
        self.persist()
        self.receipt['entry'] = dict(self.entry, repetition=99)
        self.reseal()
        with self.assertRaisesRegex(ValueError, 'foreign'):
            self.check()

    def test_nonfinite_zero_boolean_and_missing_analysis_time_rejected(self):
        for value in (None, 0, -1, float('nan'), float('inf'), True):
            with self.subTest(value=value):
                self.collector['analysis_seconds'] = value
                self.persist()
                with self.assertRaises(ValueError):
                    self.check()

    def test_copied_vendor_runtime_is_rejected_even_after_resealing(self):
        self.collector['runtime_class_files_sha256'] = {'runtime': 'f' * 64}
        self.persist()
        with self.assertRaisesRegex(ValueError, 'actually loaded runtime'):
            self.check()

    def test_previous_first_repeat_fact_cache_lineage_is_verified(self):
        self.plan['variants']['previous'] = self.plan['variants']['cache-off']
        self.plan['provenance']['previous'] = self.plan['provenance']['cache-off']
        self.plan['runtime_class_files_sha256']['previous'] = self.plan['runtime_class_files_sha256']['cache-off']
        self.collector['source_cache_policy'] = 'dedicated_external_directory'
        self.record['source_cache_before'] = {'shape': {'files': 0}, 'hash': 'empty'}
        self.record['source_cache_after'] = {'shape': {'files': 1}, 'hash': 'first-facts'}
        for temperature in ('first', 'repeat'):
            self.entry = {'id': 'crater-previous-r1-' + temperature, 'project': 'crater', 'role': 'previous',
                          'repetition': 1, 'temperature': temperature}
            self.output = self.root / 'runs' / self.entry['id']
            self.output.mkdir()
            ns = run.arguments(perf.arguments(self.plan, self.entry))
            self.record['request'] = {k: v for k, v in vars(ns).items() if k not in
                ('app', 'repo', 'output', 'cache', 'cache_seed', 'cache_seed_proof', 'source_cache_dir', 'runtime_vendor')}
            if temperature == 'repeat':
                self.record['source_cache_before'] = copy.deepcopy(self.record['source_cache_after'])
            self.persist()
            self.check()
        self.record['source_cache_before']['hash'] = 'foreign-facts'
        self.persist()
        with self.assertRaisesRegex(ValueError, 'lineage'):
            self.check()

    def test_planning_freezes_tiny_verified_reference_without_executing_a_process(self):
        reference = self.root / 'reference'
        reference.mkdir()
        cases, sealed = [], []
        for project in perf.PROJECTS:
            app = self.root / 'inputs' / project
            app.mkdir(parents=True)
            for name in ('composer.json', 'composer.lock'):
                (app / name).write_text('{}')
            cold = self.root / 'seed-caches' / project
            cold.mkdir(parents=True)
            cases.append(dict(self.case, project=project, app=str(app), seed=str(cold), harness_input=run.snapshot(app)))
            output = reference / (project + '-candidate-first')
            output.mkdir()
            acquire.write_new(output / 'report.json', self.report)
            acquire.write_new(output / 'run.json', {'collector': {'report_artifacts': {'report.json':
                                {'sha256': acquire.sha256(output / 'report.json')}}}})
            sealed.append({'project': project, 'variant': 'candidate-first', 'run_sha256': acquire.sha256(output / 'run.json')})
        acquire.write_new(reference / 'plan.json', {'cases': cases, 'image': self.plan['image']})
        reference_hash = acquire.sha256(reference / 'plan.json')
        acquire.write_new(reference / 'summary.json', {'bound_inputs': {'plan_sha256': reference_hash}, 'runs': sealed})
        variants = {}
        for role in perf.STAGES['cache-off']:
            repo = self.root / role
            (repo / 'vendor').mkdir(parents=True)
            (repo / 'vendor/autoload.php').touch()
            for path in perf.RUNTIME_CLASSES:
                target = repo / path
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_text('<?php // synthetic source identity')
            variants[role] = {'repo': str(repo), 'head': perf.PREVIOUS if role == 'previous' else 'e' * 40}
        config = {'stage': 'cache-off', 'repetitions': 1, 'image': self.plan['image'],
                  'reference_plan': str(reference / 'plan.json'), 'reference_plan_sha256': reference_hash,
                  'reference_summary_sha256': acquire.sha256(reference / 'summary.json'), 'variants': variants}
        acquire.write_new(self.root / 'config.json', config)
        tiny = SimpleNamespace(snapshot=run.snapshot, provenance=lambda *a: {'image_id': self.plan['image']})
        with patch.object(perf, 'runner', return_value=tiny), patch.object(perf, 'code_clean'), \
                patch.object(perf.subprocess, 'run', side_effect=AssertionError('no Docker/process')):
            plan = perf.prepare(self.root / 'config.json', self.root / 'new-cohort')
        self.assertEqual(40, len(plan['schedule']))
        self.assertEqual(perf.harness_files(self.root / 'new-cohort/harness'), plan['harness_files_sha256'])
        self.assertEqual(perf.PREVIOUS, plan['variants']['previous']['head'])
        self.assertEqual(self.case['expected_semantics'], plan['cases'][0]['expected_semantics'])
        protected = set(plan['protected_export_paths'])
        self.assertIn(str(Path(perf.__file__).resolve().parent), protected)
        self.assertIn(str(reference), protected)
        self.assertIn(str(self.proof), protected)
        for variant in variants.values():
            self.assertIn(variant['repo'], protected)
            self.assertIn(str(Path(variant['repo']) / 'vendor'), protected)
        for case in cases:
            self.assertIn(case['app'], protected)
            self.assertIn(case['seed'], protected)
        original = Path(perf.__file__).resolve().parent
        with patch.object(perf, '__file__', str(self.root / 'new-cohort/harness/performance.py')):
            with self.assertRaisesRegex(ValueError, 'protected'):
                perf.export_destination(plan, self.root / 'new-cohort/plan.json', original / 'never-created')

    def test_fact_scope_qualification_and_failed_cleanup_cannot_be_resealed_away(self):
        original = copy.deepcopy(self.report)
        for mutate in (lambda: self.report['source_inventory'].clear(),
                       lambda: self.report['uncertainties'].clear(),
                       lambda: self.record['cleanup'].update(container_removed=False)):
            mutate()
            self.persist()
            with self.assertRaises(ValueError):
                self.check()
            self.report = copy.deepcopy(original)
            self.record['cleanup']['container_removed'] = True

    def test_profile_is_explicit_and_not_fabricated_on_baseline(self):
        self.assertNotIn('--source-profile', perf.arguments(self.plan, self.entry))
        self.plan['instrumented'] = True
        self.assertIn('--source-profile', perf.arguments(self.plan, self.entry))
        self.record['request']['source_profile'] = True
        self.collector['source_profile_instrumented'] = True
        self.persist()
        with self.assertRaisesRegex(ValueError, 'source profile'):
            self.check()

    def test_unapproved_plan_never_reaches_execution(self):
        plan_path = self.root / 'plan.json'
        acquire.write_new(plan_path, self.plan)
        with patch.object(perf, 'runner', side_effect=AssertionError('must reject before execution')):
            with self.assertRaisesRegex(ValueError, 'approved plan hash'):
                perf.execute(plan_path, '0' * 64)

    def test_aborted_or_incomplete_cohort_cannot_export(self):
        plan_path = self.root / 'plan.json'
        acquire.write_new(plan_path, self.plan)
        (self.root / 'ABORTED.json').write_text('{}')
        with self.assertRaisesRegex(ValueError, 'not settled'):
            perf.export(plan_path, self.root / 'derived')
        self.assertFalse((self.root / 'derived').exists())

    def test_different_controller_cannot_export_even_to_a_valid_sibling(self):
        self.plan['controller_sha256'] = '0' * 64
        plan_path = self.root / 'plan.json'
        acquire.write_new(plan_path, self.plan)
        with self.assertRaisesRegex(ValueError, 'export controller changed'):
            perf.export(plan_path, self.root.parent / 'derived')
        self.assertFalse((self.root.parent / 'derived').exists())

    def test_distinct_fresh_composer_destinations_and_cache_policy(self):
        entries = perf.schedule('cache-off', 1)
        plan = dict(self.plan, variants={role: {'repo': 'tool', 'head': 'e' * 40} for role in perf.STAGES['cache-off']})
        plan['cases'] = [dict(self.case, project=p) for p in perf.PROJECTS]
        destinations = [run.arguments(perf.arguments(plan, e)).cache for e in entries]
        self.assertEqual(40, len(set(destinations)))
        previous = [run.arguments(perf.arguments(plan, e)).source_cache_dir for e in entries if e['role'] == 'previous']
        self.assertEqual(10, len(set(previous)))
        self.assertTrue(all(run.arguments(perf.arguments(plan, e)).source_cache_dir is None
                            for e in entries if e['role'] == 'cache-off'))

    def test_complete_synthetic_set_exports_then_foreign_membership_is_rejected(self):
        self.plan['cases'] = [dict(self.case, project=p) for p in perf.PROJECTS]
        self.plan['harness_files_sha256'] = {}
        (self.root / 'harness').mkdir()
        for entry in self.plan['schedule']:
            self.entry = entry
            # Deliberately make the two descriptive savings formulas diverge.
            self.collector['analysis_seconds'] = (1 if entry['role'] == 'cache-off' else 0.5) \
                if perf.PROJECTS.index(entry['project']) < 5 else 100
            self.output = self.root / 'runs' / entry['id']
            self.output.mkdir(exist_ok=True)
            ns = run.arguments(perf.arguments(self.plan, entry))
            self.record['request'] = {k: v for k, v in vars(ns).items() if k not in
                    ('app', 'repo', 'output', 'cache', 'cache_seed', 'cache_seed_proof', 'source_cache_dir', 'runtime_vendor')}
            self.persist()
        plan_path = self.root / 'plan.json'
        acquire.write_new(plan_path, self.plan)
        summary = {'status': 'completed', 'plan_sha256': acquire.sha256(plan_path),
                   'runs': {e['id']: acquire.sha256(self.root / 'runs' / e['id'] / 'completion.json')
                            for e in self.plan['schedule']}}
        acquire.write_new(self.root / 'summary.json', summary)
        with patch.object(perf, 'runner', return_value=run):
            before = campaign.fingerprint(self.root)
            valid_rows = perf.verified_rows(plan_path)
            with self.assertRaisesRegex(ValueError, 'protected'):
                perf.export(plan_path, self.root / 'runs/missing-parent/derived')
            self.assertFalse((self.root / 'runs/missing-parent').exists())
            perf.export(plan_path, self.root.parent / 'derived')
            self.assertEqual(valid_rows, perf.verified_rows(plan_path))
            self.assertEqual(before, campaign.fingerprint(self.root))
            with (self.root.parent / 'derived/measurements.csv').open(newline='', encoding='utf-8') as stream:
                self.assertEqual(40, len(list(csv.DictReader(stream))))
            self.assertIn('| 50.500 | 50.250 | 0.5% | 25.0% |',
                          (self.root.parent / 'derived/results.md').read_text(encoding='utf-8'))
            original_plan = plan_path.read_bytes()
            plan_path.write_text(json.dumps(dict(self.plan, protected_export_paths=[])), encoding='utf-8')
            with self.assertRaisesRegex(ValueError, 'summary differs'):
                perf.export(plan_path, self.root.parent / 'edited-plan-derived')
            self.assertFalse((self.root.parent / 'edited-plan-derived').exists())
            plan_path.write_bytes(original_plan)
            summary['runs']['foreign-self-sealed'] = 'f' * 64
            (self.root / 'summary.json').write_text(json.dumps(summary))
            with self.assertRaisesRegex(ValueError, 'row set'):
                perf.export(plan_path, self.root.parent / 'foreign-derived')
        self.assertFalse((self.root.parent / 'foreign-derived').exists())


if __name__ == '__main__':
    unittest.main()
