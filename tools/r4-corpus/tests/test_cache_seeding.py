"""Synthetic v2 cache/lineage tests; opt-in Docker checks never run an analyzer."""
import copy
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch
import uuid

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import acquire
import campaign
import run
import summarize
import test_evidence

TOOLS = Path(__file__).resolve().parents[1]


def owned_command(root, label, image, mounts, script, timeout=30):
    """Use production ownership/recovery boundaries for every offline test container."""
    token = uuid.uuid4().hex
    state = Path(tempfile.gettempdir()).resolve() / ('preflight-r4-' + token)
    path = root / (label + '-ownership.json')
    owner = {'version': 1, 'task_token': token, 'container_name': state.name,
             'state_path': str(state), 'host_pid': os.getpid(), 'lifecycle': 'prepared',
             'creation_started': False, 'create_settled': False,
             'cleanup': {'container_removed': False, 'host_scratch_removed': False}}
    run.ownership_update(path, owner, 'prepared')
    try:
        state.mkdir()
        command = ['docker', 'create', '--pull', 'never', '--name', state.name, '--label', 'preflight.r4.task=' + token,
                   '--network', 'none', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges',
                   '--mount', f'type=bind,source={state},target=/scratch']
        for source, destination, readonly in mounts:
            command += ['--mount', f'type=bind,source={source},target={destination}' + (',readonly' if readonly else '')]
        command += ['--entrypoint', '/bin/sh', image, '-c', script]
        owner['creation_started'] = True
        run.ownership_update(path, owner, 'creating')
        created = subprocess.run(command, capture_output=True, timeout=30)
        owner['create_settled'] = True
        run.ownership_update(path, owner, 'created' if created.returncode == 0 else 'create_failed')
        if created.returncode:
            raise AssertionError(created.stderr.decode())
        owner['container_id'] = run.owned_container(owner)['Id']
        run.ownership_update(path, owner, 'created')
        return subprocess.run(['docker', 'start', '--attach', owner['container_id']],
                              capture_output=True, timeout=timeout)
    finally:
        run.remove_owned_container(path, owner)
        run.remove_owned_state(path, owner)


class SeedHostTests(unittest.TestCase):
    def setUp(self):
        self.fixture = test_evidence.CampaignEvidence(methodName='runTest')
        self.addCleanup(self.fixture.doCleanups)
        self.fixture.setUp()
        self.plan = self.fixture.plan
        self.plan.update(campaign_version=2, cache_seed_policy=campaign.SEED_POLICY)
        self.output = self.fixture.output
        (self.output / 'plan.json').write_text(json.dumps(self.plan), encoding='utf-8')

    def runner(self, argv, **kwargs):
        result = self.fixture.runner(argv, **kwargs)
        raw = Path(argv[argv.index('--output') + 1])
        cache = Path(argv[argv.index('--cache') + 1])
        if '--cache-seed' not in argv:
            (cache / 'vcs' / 'mirror').mkdir(parents=True)
            (cache / 'vcs' / 'mirror' / 'HEAD').write_bytes(b'ref: refs/heads/main\n')
            return result
        expected_path = Path(argv[argv.index('--cache-seed-proof') + 1])
        source = Path(argv[argv.index('--cache-seed') + 1])
        self.assertEqual({}, campaign.fingerprint(cache), 'host must not pre-copy the warm cache')
        expected = acquire.load_json(expected_path)
        # Emulate only the protocol record here; real PHP copying/UID checks are below.
        shutil.copytree(source, cache, dirs_exist_ok=True)
        proof = {'policy': campaign.SEED_POLICY, 'status': 'completed', 'owner_uid': 1000, 'owner_gid': 1000,
                 'destination_empty': True, 'entries_owned': True, 'elapsed_seconds': 0.125,
                 'cold_receipt_sha256': expected['cold_receipt_sha256'],
                 'expected_proof_sha256': acquire.sha256(expected_path),
                 'source_before_sha256': expected['files_sha256'], 'source_after_sha256': expected['files_sha256'],
                 'destination_before_analysis_sha256': expected['files_sha256'], 'directories': expected['directories']}
        acquire.write_new(raw / 'cache-seed.json', proof)
        record = acquire.load_json(raw / 'run.json')
        record.update(cache_seed=proof, cache_seed_expected_sha256=acquire.sha256(expected_path))
        record['collector'].update(cache_seed_verified=True, cache_seed_verification_seconds=0.025,
                                   cache_before_analysis_sha256=expected['files_sha256'])
        (raw / 'run.json').write_text(json.dumps(record), encoding='utf-8')
        return result

    def execute(self, runner=None):
        with patch('campaign.subprocess.run', side_effect=runner or self.runner):
            return campaign.execute(self.output, self.plan)

    def warm(self):
        return self.output / 'runs/lychee-nearest-warm/attempt-0001'

    def reseal(self):
        attempt = self.warm()
        receipt = acquire.load_json(attempt / 'completion.json')
        receipt['files_sha256'] = campaign.fingerprint(attempt)
        del receipt['files_sha256']['completion.json']
        (attempt / 'completion.json').write_text(json.dumps(receipt), encoding='utf-8')

    def test_v2_seed_lineage_timing_and_independent_destination(self):
        self.assertEqual(0, self.execute())
        result = summarize.summarize(self.output)
        self.assertEqual(2, result['summary_version'])
        self.assertEqual(campaign.SEED_POLICY, result['cache_seed_policy'])
        cold = acquire.load_json(self.output / 'runs/lychee-nearest-cold/attempt-0001/completion.json')
        invocation = acquire.load_json(self.warm() / 'invocation.json')
        expected = acquire.load_json(self.warm() / 'cache-seed-expected.json')
        self.assertEqual({}, invocation['cache_before_sha256'])
        self.assertNotEqual(invocation['cache_path'], cold['cache_path'])
        self.assertEqual(cold['cache_after_sha256'], expected['files_sha256'])
        self.assertEqual(cold['cache_after_directories'], expected['directories'])
        self.assertEqual(cold['cache_after_sha256'], campaign.fingerprint(Path(cold['cache_path'])))
        self.assertIsNone(result['latest'][0]['metrics']['cache_seed_seconds'])
        self.assertEqual(0.125, result['latest'][1]['metrics']['cache_seed_seconds'])
        self.assertEqual(0.025, result['latest'][1]['metrics']['cache_seed_verification_seconds'])
        self.assertGreaterEqual(result['latest'][1]['metrics']['host_preparation_seconds'], 0)
        with patch('campaign.subprocess.run', side_effect=AssertionError('completed fixtures must not rerun')):
            self.assertEqual(0, campaign.execute(self.output, self.plan))

    def test_seed_ownership_bytes_and_collector_gate_cannot_be_forged_by_rehashing(self):
        self.assertEqual(0, self.execute())
        record_path = self.warm() / 'raw/run.json'
        proof_path = self.warm() / 'raw/cache-seed.json'
        original_record = record_path.read_bytes()
        original_proof = proof_path.read_bytes()
        for field, value in (('entries_owned', False), ('destination_before_analysis_sha256', {}),
                             ('cold_receipt_sha256', 'f' * 64), ('directories', []), ('elapsed_seconds', None)):
            with self.subTest(field=field):
                record = json.loads(original_record)
                proof = json.loads(original_proof)
                proof[field] = value
                record['cache_seed'] = proof
                record_path.write_text(json.dumps(record))
                proof_path.write_text(json.dumps(proof))
                self.reseal()
                with self.assertRaises(ValueError):
                    summarize.summarize(self.output)
                record_path.write_bytes(original_record)
                proof_path.write_bytes(original_proof)
        record = json.loads(original_record)
        record['collector']['cache_seed_verified'] = False
        record_path.write_text(json.dumps(record))
        self.reseal()
        with self.assertRaisesRegex(ValueError, 'collector did not verify'):
            summarize.summarize(self.output)

    def test_failed_seed_is_failed_not_unknown_and_preserves_cold_for_retry(self):
        def failure(argv, **kwargs):
            if '--cache-seed' not in argv:
                return self.runner(argv, **kwargs)
            result = self.fixture.runner(argv, **kwargs)
            raw = Path(argv[argv.index('--output') + 1])
            record = acquire.load_json(raw / 'run.json')
            proof = {'policy': campaign.SEED_POLICY, 'status': 'failed', 'failure_reason': 'cache_seed_copy_failed', 'elapsed_seconds': 0.5}
            acquire.write_new(raw / 'cache-seed.json', proof)
            record.update(status='failed', cache_seed=proof, collector=None)
            record['report_bytes'] = {'report.json': None, 'report.md': None}
            (raw / 'report.json').unlink()
            (raw / 'run.json').write_text(json.dumps(record))
            return subprocess.CompletedProcess(argv, 78)
        self.assertEqual(1, self.execute(failure))
        receipt = (self.warm() / 'completion.json').read_bytes()
        result = summarize.summarize(self.output)
        self.assertEqual('failed', result['latest'][1]['status'])
        self.assertIsNone(result['latest'][1]['report_states'])
        self.assertEqual(0, self.execute())
        self.assertEqual(receipt, (self.warm() / 'completion.json').read_bytes())

    def test_seed_proof_is_bound_to_frozen_cold_receipt(self):
        self.assertEqual(0, self.execute())
        receipt = acquire.load_json(self.warm() / 'completion.json')
        receipt['cold_cache_after_sha256'] = {}
        (self.warm() / 'completion.json').write_text(json.dumps(receipt))
        with self.assertRaisesRegex(ValueError, 'cold cache changed'):
            summarize.summarize(self.output)

    def test_seed_mounts_use_existing_owner_and_readonly_inputs(self):
        import test_harness
        directory = self.fixture.root / 'mount-check'
        directory.mkdir()
        args = test_harness.HostTests().args(directory)
        args.cache_seed = self.fixture.root / 'seed-input'
        args.cache_seed.mkdir()
        args.cache_seed_proof = self.fixture.root / 'seed-proof.json'
        args.cache_seed_proof.write_text('{}')
        with patch.object(run, 'docker_call', return_value=1) as docker, patch.object(run, 'owned_container', return_value=None), patch.object(run, 'provenance', return_value={'image_id': 'sha256:test'}):
            self.assertEqual(1, run.run(args))
        create = docker.call_args_list[0].args[0]
        self.assertIn(f'type=bind,source={args.app},target=/target,readonly', create)
        self.assertIn(f'type=bind,source={args.cache_seed},target=/cache-seed,readonly', create)
        self.assertIn(f'type=bind,source={args.cache_seed_proof},target=/cache-seed-proof.json,readonly', create)
        self.assertIn(f'type=bind,source={args.cache},target=/composer-cache', create)
        self.assertIn('R4_CACHE_SEED_POLICY=runtime-user-copy-v1', create)
        self.assertIn('--cap-drop', create)
        owner = acquire.load_json(args.output / 'ownership.json')
        self.assertTrue(campaign.clean(owner))

    def test_seed_paths_must_be_disjoint_before_ownership_or_output_creation(self):
        import test_harness
        directory = self.fixture.root / 'disjoint-check'
        directory.mkdir()
        args = test_harness.HostTests().args(directory)
        args.cache_seed = args.app
        args.cache_seed_proof = self.fixture.root / 'disjoint-proof.json'
        args.cache_seed_proof.write_text('{}')
        with self.assertRaisesRegex(ValueError, 'separate non-nested'):
            run.validate(args)
        self.assertFalse(args.output.exists())

    def test_directory_inventory_order_is_portable_and_preserves_empty_directories(self):
        root = self.fixture.root / 'mixed-case-cache'
        for name in ('a-empty', 'Z-empty'):
            (root / name).mkdir(parents=True)
        self.assertEqual(['Z-empty', 'a-empty'], campaign.cache_directories(root))
        self.assertEqual({}, campaign.fingerprint(root))

    def test_version1_keeps_legacy_command_lineage_states_and_metric_shape(self):
        self.plan['campaign_version'] = 1
        del self.plan['cache_seed_policy']
        (self.output / 'plan.json').write_text(json.dumps(self.plan), encoding='utf-8')
        self.assertEqual(0, self.execute(self.fixture.runner))
        result = summarize.summarize(self.output)
        self.assertEqual(1, result['summary_version'])
        self.assertNotIn('campaign_version', result)
        self.assertNotIn('cache_seed_policy', result)
        for row in result['latest']:
            self.assertEqual('completed', row['status'])
            self.assertEqual({'resolution': 'blocked', 'staged_resolution': 'unknown'}, row['report_states'])
            self.assertEqual(2, row['metrics']['composer_starts'])
            self.assertIsNone(row['metrics']['analysis_seconds'])
            self.assertNotIn('host_preparation_seconds', row['metrics'])
            self.assertNotIn('cache_seed', row)
        invocation = acquire.load_json(self.warm() / 'invocation.json')
        self.assertNotIn('--cache-seed', invocation['argv'])
        cold = acquire.load_json(self.output / 'runs/lychee-nearest-cold/attempt-0001/completion.json')
        self.assertEqual(cold['cache_after_sha256'], invocation['cache_before_sha256'])


@unittest.skipUnless(os.environ.get('R4_CACHE_TEST_IMAGE'), 'set R4_CACHE_TEST_IMAGE for isolated offline Git/seed tests')
class SeedDockerTests(unittest.TestCase):
    def setUp(self):
        self.root = Path(tempfile.mkdtemp(prefix='r4-cache-seed-test-')).resolve()
        self.image = os.environ['R4_CACHE_TEST_IMAGE']
        self.addCleanup(self.cleanup)
        for name in ('cold', 'seeded', 'output'):
            (self.root / name).mkdir()
        created = owned_command(self.root, 'cold', self.image, [(self.root / 'cold', '/composer-cache', False)],
                                'mkdir -p /composer-cache/vcs/Z-empty /composer-cache/vcs/a-empty; git init --bare /composer-cache/vcs/offline.git >/dev/null; git -C /composer-cache/vcs/offline.git rev-parse --is-bare-repository')
        self.assertEqual(0, created.returncode, created.stderr.decode())
        self.before = campaign.fingerprint(self.root / 'cold')
        self.expected = {'policy': campaign.SEED_POLICY, 'cold_receipt_sha256': 'a' * 64,
                         'files_sha256': self.before, 'directories': campaign.cache_directories(self.root / 'cold')}
        acquire.write_new(self.root / 'expected.json', self.expected)

    def cleanup(self):
        for path in self.root.glob('*-ownership.json'):
            owner = acquire.load_json(path)
            if not campaign.clean(owner):
                print('Retained unsafe synthetic cache diagnostic: ' + str(self.root))
                return
        if self.root.parent != Path(tempfile.gettempdir()).resolve() or not self.root.name.startswith('r4-cache-seed-test-'):
            raise AssertionError('unsafe test cleanup path')
        shutil.rmtree(self.root)

    def mounts(self, destination_readonly=False):
        return [(TOOLS, '/harness', True), (self.root / 'cold', '/cache-seed', True),
                (self.root / 'expected.json', '/cache-seed-proof.json', True),
                (self.root / 'seeded', '/composer-cache', destination_readonly), (self.root / 'output', '/output', False)]

    def test_runtime_copy_preserves_git_identity_where_host_copy_loses_it(self):
        shutil.copytree(self.root / 'cold', self.root / 'host-copy')
        bad = owned_command(self.root, 'host-copy', self.image,
                            [(self.root / 'host-copy', '/composer-cache', True)],
                            'id -u; stat -c "%u:%g" /composer-cache/vcs/offline.git; git -C /composer-cache/vcs/offline.git rev-parse --is-bare-repository')
        if os.name == 'nt':
            self.assertEqual(128, bad.returncode)
            self.assertIn(b'dubious ownership', bad.stderr)
        good = owned_command(self.root, 'seed', self.image, self.mounts(),
                             'php /harness/cache-seed.php && php -r \'require "/harness/cache-seed.php"; r4VerifySeedBeforeAnalysis();\' && id -u && stat -c "%u:%g" /composer-cache/vcs/offline.git && git -C /composer-cache/vcs/offline.git rev-parse --is-bare-repository')
        self.assertEqual(0, good.returncode, (good.stdout + good.stderr).decode())
        proof = acquire.load_json(self.root / 'output/cache-seed.json')
        self.assertTrue(proof['entries_owned'])
        self.assertEqual('completed', proof['status'])
        self.assertEqual(self.before, proof['destination_before_analysis_sha256'])
        self.assertEqual(self.before, campaign.fingerprint(self.root / 'cold'))
        self.assertEqual(self.before, campaign.fingerprint(self.root / 'seeded'))
        self.assertIn((str(proof['owner_uid']) + ':' + str(proof['owner_gid'])).encode(), good.stdout)
        tampered = owned_command(self.root, 'gate-mutation', self.image, self.mounts(),
                                 'printf tampered >> /composer-cache/vcs/offline.git/HEAD; php -r \'require "/harness/cache-seed.php"; r4VerifySeedBeforeAnalysis();\'')
        self.assertNotEqual(0, tampered.returncode)
        self.assertIn(b'cache_before_analysis_mismatch', tampered.stdout + tampered.stderr)
        self.assertEqual(self.before, campaign.fingerprint(self.root / 'cold'))

    def test_failed_copy_and_wrong_inventory_fail_closed_without_source_mutation(self):
        entrypoint = 'R4_CACHE_SEED_POLICY=runtime-user-copy-v1 /bin/sh /harness/container.sh'
        result = owned_command(self.root, 'readonly-destination', self.image, self.mounts(True), entrypoint)
        self.assertEqual(78, result.returncode)
        self.assertEqual('failed', acquire.load_json(self.root / 'output/cache-seed.json')['status'])
        self.assertFalse((self.root / 'output/analyzer.time').exists())
        self.assertFalse((self.root / 'output/report.json').exists())
        wrong = dict(self.expected, files_sha256={})
        (self.root / 'expected.json').write_text(json.dumps(wrong))
        result = owned_command(self.root, 'wrong-inventory', self.image, self.mounts(), entrypoint)
        self.assertEqual(78, result.returncode)
        self.assertEqual('cache_seed_source_mismatch', acquire.load_json(self.root / 'output/cache-seed.json')['failure_reason'])
        self.assertEqual(self.before, campaign.fingerprint(self.root / 'cold'))
        self.assertEqual({}, campaign.fingerprint(self.root / 'seeded'))

    def test_killed_seed_owner_recovers_only_its_container_and_state(self):
        script = 'import json,sys; from pathlib import Path; from test_cache_seeding import owned_command; owned_command(Path(sys.argv[1]), "interrupted", sys.argv[2], [(Path(a),b,c) for a,b,c in json.loads(sys.argv[3])], "php /harness/cache-seed.php && sleep 120", timeout=150)'
        mounts = [[str(a), b, c] for a, b, c in self.mounts()]
        child = subprocess.Popen([sys.executable, '-B', '-c', script, str(self.root), self.image, json.dumps(mounts)],
                                 cwd=Path(__file__).parent, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        try:
            deadline = time.monotonic() + 30
            while not (self.root / 'output/cache-seed.json').exists() and child.poll() is None and time.monotonic() < deadline:
                time.sleep(0.05)
            self.assertTrue((self.root / 'output/cache-seed.json').exists())
            child.kill()
            child.wait(timeout=5)
            path = self.root / 'interrupted-ownership.json'
            owner = acquire.load_json(path)
            self.assertIsNotNone(run.owned_container(owner))
            self.assertEqual(0, run.recover(path))
            recovered = acquire.load_json(path)
            self.assertTrue(campaign.clean(recovered))
            self.assertIsNone(run.owned_container(recovered))
            self.assertFalse(Path(owner['state_path']).exists())
            self.assertEqual(self.before, campaign.fingerprint(self.root / 'cold'))
        finally:
            if child.poll() is None:
                child.kill()
                child.wait(timeout=5)
            child.stderr.close()
            path = self.root / 'interrupted-ownership.json'
            if path.exists():
                run.recover(path)


if __name__ == '__main__':
    unittest.main()
