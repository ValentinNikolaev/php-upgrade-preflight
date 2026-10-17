#!/usr/bin/env python3
"""Prepare, gate and summarize staged offline performance experiments; no network acquisition."""
import argparse
import csv
import importlib.util
import json
import math
import os
from pathlib import Path
import re
import shutil
import statistics
import subprocess
import sys
import time
import uuid

from acquire import load_json, sha256, write_new
from campaign import cache_directories, digest, fingerprint

PREVIOUS = '6fe3249686866e0b9764ceb7eb2b51c89aad88e6'
PROJECTS = ('crater', 'bookstack', 'lychee', 'snipeit', 'invoiceninja',
            'akaunting', 'monica', 'koel', 'bagisto', 'firefly')
PROFILE_PROJECTS = ('crater', 'invoiceninja', 'bagisto')
RUNTIME_CLASSES = ('packages/core/src/Analysis/DefaultUpgradeAnalyzer.php',
                   'packages/core/src/Source/SourceUsageScanner.php', 'packages/core/src/Model/UpgradeReport.php')
STAGES = {'cache-off': ('previous', 'cache-off'),
          'profiles': ('profile',), 'final': ('cache-off', 'optimized'),
          'final-third': ('cache-off', 'optimized')}


def require(condition, message):
    if not condition:
        raise ValueError(message)


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], text=True, timeout=30).strip()


def code_clean(repo, head):
    require(git(repo, 'rev-parse', 'HEAD') == head, 'runtime HEAD changed')
    require(not git(repo, 'status', '--porcelain', '--untracked-files=all', '--',
                    'packages', 'vendor', 'composer.json', 'composer.lock'), 'runtime is dirty')


def harness_files(root):
    return {p.name: sha256(p) for p in sorted(Path(root).iterdir())
            if p.is_file() and (p.suffix in ('.py', '.php', '.sh') or p.name == 'Dockerfile')}


def runner(root):
    spec = importlib.util.spec_from_file_location('performance_runner', Path(root) / 'run.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def variant_provenance(module, variant, harness, image):
    return module.provenance(Path(variant['repo']), harness, image,
                             Path(variant['vendor']) if variant.get('vendor') else None)


def schedule(stage, repetitions):
    require(stage in STAGES, 'unknown stage')
    require(type(repetitions) is int and repetitions == (2 if stage == 'final' else 1),
            'stage repetitions are fixed: final=2, other=1')
    entries = []
    for repeat in range(1, repetitions + 1):
        if stage == 'final-third':
            repeat = 3
        for index, project in enumerate(PROFILE_PROJECTS if stage == 'profiles' else PROJECTS):
            roles = list(STAGES[stage])
            if (index + repeat) % 2 == 0:
                roles.reverse()
            for temperature in (('independent',) if stage in ('final', 'final-third') else
                                ('first',) if stage == 'profiles' else ('first', 'repeat')):
                for role in roles:
                    entries.append({'id': f'{project}-{role}-r{repeat}-{temperature}',
                                    'project': project, 'role': role,
                                    'repetition': repeat, 'temperature': temperature})
    return entries


def semantics(report):
    """Retained observations and explicit omissions, never scanned-file coverage or recall."""
    facts = [[u['file'], u['symbol'], u['usage_type'], u.get('line')]
             for u in report['source_inventory']]
    warnings = [u for u in report['uncertainties']
                if re.search(r'(?i)source|autoload|pars(?:e|ing)|usage.*limit', u)]
    # Evidence allocation can differ; compare the actual qualification text.
    warnings = [re.sub(r'\b(?:source|source-limit|source-discovery)-[0-9]+\b', '[evidence]', u)
                for u in warnings]
    return {'facts_sha256': digest(facts), 'retained_facts': len(facts),
            'files_with_retained_facts': len({f[0] for f in facts}),
            'source_qualifications': warnings,
            'direct': report['resolution']['status'],
            'staged': report['staged_resolution']['status'],
            'staged_execution': report['staged_resolution']['execution_state']}


def check_case(module, case):
    require(module.snapshot(Path(case['app'])) == case['harness_input'], 'source snapshot changed')
    require(sha256(Path(case['seed_proof'])) == case['seed_proof_sha256'], 'seed proof changed')
    proof = load_json(case['seed_proof'])
    require(proof['policy'] == 'runtime-user-copy-v1', 'unsupported seed policy')
    require(fingerprint(Path(case['seed'])) == proof['files_sha256'], 'seed bytes changed')
    require(cache_directories(Path(case['seed'])) == proof['directories'], 'seed directories changed')


def prepare(config, output):
    """Potentially expensive verification; run only after exact heads are supplied, before timing."""
    config = load_json(config)
    stage = config['stage']
    repetitions = config['repetitions']
    entries = schedule(stage, repetitions)
    require(set(config['variants']) == set(STAGES[stage]), 'stage variant membership differs')
    require(re.fullmatch(r'sha256:[0-9a-f]{64}', config['image']) is not None, 'image ID must be exact')
    reference_path = Path(config['reference_plan']).resolve()
    require(sha256(reference_path) == config['reference_plan_sha256'], 'reference plan changed')
    reference = load_json(reference_path)
    reference_summary_path = reference_path.parent / 'summary.json'
    require(sha256(reference_summary_path) == config['reference_summary_sha256'], 'accepted reference summary changed')
    reference_summary = load_json(reference_summary_path)
    require(reference_summary['bound_inputs']['plan_sha256'] == config['reference_plan_sha256'], 'reference summary plan differs')
    require(reference['image'] == config['image'], 'image differs from accepted reference')
    require([c['project'] for c in reference['cases']] == list(PROJECTS), 'reference project set/order differs')
    require(stage != 'cache-off' or config['variants']['previous']['head'] == PREVIOUS,
            'previous runtime must be frozen 6fe3249')
    prior = None
    if stage == 'final-third':
        prior_path = Path(config['prior_final_plan']).resolve()
        prior = load_json(prior_path)
        require(sha256(prior_path) == config['prior_final_plan_sha256']
                and prior['stage'] == 'final', 'third pair must bind the final plan')
        require(sha256(prior_path.parent / 'summary.json') == config['prior_final_summary_sha256'], 'prior summary differs')
        prior_rows = verified_rows(prior_path)
        require(extension_reason(prior_rows) is not None, 'third pair trigger not met')
    output = Path(output).resolve()
    inputs = [reference_path.parent, Path(__file__).resolve().parent]
    if prior is not None:
        inputs.append(prior_path.parent)
    for variant in config['variants'].values():
        require(isinstance(variant['head'], str) and re.fullmatch('[0-9a-f]{40}', variant['head']),
                'supply exact reviewed runtime heads; placeholders cannot execute')
        variant['repo'] = str(Path(variant['repo']).resolve())
        vendor = Path(variant.get('vendor', str(Path(variant['repo']) / 'vendor'))).resolve()
        require((vendor / 'autoload.php').is_file(), 'runtime dependencies unavailable')
        if variant.get('vendor'):
            variant['vendor'] = str(vendor)
        code_clean(variant['repo'], variant['head'])
        inputs.extend([Path(variant['repo']), vendor])
    if prior is not None:
        require(prior['variants'] == config['variants'], 'third pair must bind the same final runtimes')
    cases = [dict(c) for c in reference['cases']
             if stage != 'profiles' or c['project'] in PROFILE_PROJECTS]
    module = runner(Path(__file__).resolve().parent)
    for case in cases:
        case['seed_proof_sha256'] = sha256(Path(case['seed_proof']))
        inputs.extend(Path(case[k]).resolve() for k in ('app', 'seed', 'seed_proof'))
        check_case(module, case)
        source_report = reference_path.parent / (case['project'] + '-candidate-first') / 'report.json'
        case['reference_report_sha256'] = sha256(source_report)
        reference_run_path = source_report.parent / 'run.json'
        accepted = [r for r in reference_summary['runs'] if r['project'] == case['project'] and r['variant'] == 'candidate-first']
        require(len(accepted) == 1 and accepted[0]['run_sha256'] == sha256(reference_run_path), 'reference run seal differs')
        require(load_json(reference_run_path)['collector']['report_artifacts']['report.json']['sha256']
                == case['reference_report_sha256'], 'reference report seal differs')
        case['expected_semantics'] = semantics(load_json(source_report))
    require(all(output != p and output not in p.parents and p not in output.parents for p in inputs),
            'output and immutable inputs must be separate non-nested directories')
    require(not output.exists() and not output.is_symlink(), 'cohort output must be fresh')
    plan = {'version': 1, 'stage': stage, 'repetitions': repetitions, 'image': config['image'],
            'reference_plan': str(reference_path), 'reference_plan_sha256': config['reference_plan_sha256'],
            'reference_summary_sha256': config['reference_summary_sha256'],
            'variants': config['variants'], 'cases': cases, 'schedule': entries,
            'protected_export_paths': sorted({str(p.resolve()) for p in inputs}),
            'harness_files_sha256': harness_files(Path(__file__).resolve().parent),
            'controller_sha256': sha256(Path(__file__)), 'output': str(output),
            'instrumented': stage == 'profiles',
            'metadata_policy': 'every invocation has a fresh identically preseeded Composer cache',
            'source_cache_policy': 'previous uses explicit persistent facts only first-to-repeat; other roles use reviewed defaults',
            'timing_scope': 'analysis excludes preparation/rendering; controller covers invocation, cleanup and postflight source verification, excluding initial preflight'}
    if prior is not None:
        require(all(plan[k] == prior[k] for k in ('image', 'cases', 'reference_plan_sha256')), 'third pair input scope differs')
        plan['prior_final'] = {k: config[k] for k in ('prior_final_plan', 'prior_final_plan_sha256', 'prior_final_summary_sha256')}
    output.mkdir(parents=True)
    harness = output / 'harness'
    harness.mkdir()
    for name in plan['harness_files_sha256']:
        shutil.copyfile(Path(__file__).parent / name, harness / name)
    plan['provenance'] = {role: variant_provenance(module, v, harness, plan['image'])
                          for role, v in plan['variants'].items()}
    plan['runtime_class_files_sha256'] = {role: {p: sha256(Path(v['repo']) / p) for p in RUNTIME_CLASSES}
                                         for role, v in plan['variants'].items()}
    write_new(output / 'plan.json', plan)
    return plan


def arguments(plan, entry):
    root = Path(plan['output'])
    case = next(c for c in plan['cases'] if c['project'] == entry['project'])
    variant = plan['variants'][entry['role']]
    argv = ['--app', case['app'], '--repo', variant['repo'], '--output', str(root / 'runs' / entry['id']),
            '--cache', str(root / 'composer-caches' / entry['id']), '--cache-seed', case['seed'],
            '--cache-seed-proof', case['seed_proof'], '--target-major', str(case['request']['target_major']),
            '--from-php', case['request']['from_php'], '--target-php', case['request']['target_php'],
            '--memory-limit', '1024M', '--composer-memory-limit', '1024M', '--network', 'none',
            '--github-transport', 'public-https', '--scenario-timeout', '45', '--diagnostic-timeout', '15',
            '--overall-timeout', '540', '--image', plan['image'], '--analysis-mode', 'standard']
    if entry['role'] == 'previous':
        argv += ['--source-cache-dir', str(root / 'facts' / f"{entry['project']}-r{entry['repetition']}")]
    if variant.get('vendor'):
        argv += ['--runtime-vendor', variant['vendor']]
    if plan['instrumented']:
        argv.append('--source-profile')
    return argv


def finite(value, positive=False):
    return type(value) in (int, float) and math.isfinite(value) and (value > 0 if positive else value >= 0)


def valid_profile(value):
    return (isinstance(value, dict) and set(value) == {'total_ms', 'timings_ms', 'counters'}
            and finite(value['total_ms']) and isinstance(value['timings_ms'], dict)
            and set(value['timings_ms']) == {'discovery', 'read', 'hash', 'cache_get', 'parse', 'cache_put', 'evidence'}
            and all(finite(v) for v in value['timings_ms'].values())
            and isinstance(value['counters'], dict) and 1 <= len(value['counters']) <= 64
            and all(isinstance(k, str) and re.fullmatch('[a-z_]{1,64}', k)
                    and type(v) is int and v >= 0 for k, v in value['counters'].items()))


def valid_analysis_profile(value):
    return (isinstance(value, dict) and set(value) == {'status', 'total_ms', 'timings_ms'}
            and value['status'] == 'completed' and finite(value['total_ms'])
            and isinstance(value['timings_ms'], dict)
            and set(value['timings_ms']) == {'source_capture', 'source_recheck_after_scan', 'input_recheck_final',
                                             'ownership', 'source_impact', 'stage_assessment', 'risk_effort', 'assembly'}
            and all(finite(v) for v in value['timings_ms'].values()))


def verify_completed(module, plan, entry):
    output = Path(plan['output']) / 'runs' / entry['id']
    receipt = load_json(output / 'completion.json')
    record = load_json(output / 'run.json')
    actual = fingerprint(output)
    del actual['completion.json']
    require(receipt['files_sha256'] == actual, 'output seal differs')
    require(receipt['plan_sha256'] == digest(plan) and receipt['entry'] == entry,
            'foreign receipt/entry')
    require(receipt['argv'] == arguments(plan, entry), 'invocation differs')
    require(receipt['controller_sha256'] == plan['controller_sha256'], 'controller differs')
    namespace = module.arguments(arguments(plan, entry))
    expected = {k: v for k, v in vars(namespace).items()
                if k not in ('app', 'repo', 'output', 'cache', 'cache_seed', 'cache_seed_proof', 'source_cache_dir', 'runtime_vendor')}
    require(record['request'] == expected, 'recorded request differs')
    case = next(c for c in plan['cases'] if c['project'] == entry['project'])
    require(record['input_before'] == record['input_after'] == case['harness_input'], 'source differs')
    require(record['provenance'] == plan['provenance'][entry['role']], 'runtime/harness provenance differs')
    require(record['collector']['runtime_class_files_sha256'] == plan['runtime_class_files_sha256'][entry['role']],
            'actually loaded runtime differs from pinned package files')
    require(record['status'] == 'completed' and record['immutable'] is True
            and record['container_exit_code'] == 0 and record['watchdog'] is False
            and record.get('oom_killed') is False, 'attempt did not complete normally')
    require(all(record['cleanup'].get(k) is True for k in ('container_removed', 'host_scratch_removed')),
            'cleanup unverified')
    owner = load_json(output / 'ownership.json')
    require(owner['lifecycle'] == 'cleaned' and all(owner['cleanup'].get(k) is True
            for k in ('container_removed', 'host_scratch_removed')), 'ownership cleanup unverified')
    require(module.report_evidence(output, record['collector'], record['container']), 'invalid canonical artifacts')
    require(record.get('cache_seed_expected_sha256') == case['seed_proof_sha256'], 'seed declaration differs')
    seed = record['cache_seed']
    expected_seed = load_json(case['seed_proof'])
    require(seed == load_json(output / 'cache-seed.json') and seed['status'] == 'completed'
            and seed['policy'] == expected_seed['policy']
            and type(seed['owner_uid']) is int and seed['owner_uid'] >= 0
            and type(seed['owner_gid']) is int and seed['owner_gid'] >= 0
            and finite(seed['elapsed_seconds'])
            and seed['entries_owned'] is True and seed['destination_empty'] is True
            and seed['expected_proof_sha256'] == case['seed_proof_sha256']
            and seed['cold_receipt_sha256'] == expected_seed['cold_receipt_sha256'], 'seed proof unverified')
    for key in ('source_before_sha256', 'source_after_sha256', 'destination_before_analysis_sha256'):
        require(seed[key] == expected_seed['files_sha256'], 'seed bytes differ')
    require(seed['directories'] == expected_seed['directories']
            and record['collector']['cache_seed_verified'] is True
            and finite(record['collector']['cache_seed_verification_seconds'])
            and record['collector']['cache_before_analysis_sha256'] == expected_seed['files_sha256'], 'collector seed gate differs')
    if entry['role'] == 'previous':
        require(record['collector']['source_cache_policy'] == 'dedicated_external_directory', 'previous cache policy differs')
        if entry['temperature'] == 'first':
            require(all(v == 0 for v in record['source_cache_before']['shape'].values()), 'first cache was not empty')
        else:
            first = dict(entry, id=entry['id'].removesuffix('repeat') + 'first', temperature='first')
            first_record, _ = verify_completed(module, plan, first)
            require(record['source_cache_before'] == first_record['source_cache_after'], 'repeat fact-cache lineage differs')
    else:
        require(record['collector']['source_cache_policy'] == 'tool_default'
                and 'source_cache_before' not in record and 'source_cache_after' not in record,
                'default-policy run unexpectedly used an explicit cache mount')
    require(finite(record['collector']['analysis_seconds'], True), 'analysis time missing/nonfinite')
    for key in ('human_summary_ready_seconds', 'first_direct_finding_seconds'):
        require(record['collector'].get(key) is None or finite(record['collector'][key]), 'invalid optional timing')
    starts = record['composer']['started_count']
    require(type(starts) is int and starts >= 0, 'invalid Composer count')
    require(record['collector']['source_profile_instrumented'] is plan['instrumented'], 'profile scope differs')
    require(valid_profile(record['collector']['source_profile']) if plan['instrumented']
            else record['collector'].get('source_profile') is None, 'missing or unexpected source profile')
    details = record['collector'].get('analysis_profile')
    require(details is None or (plan['instrumented'] and valid_analysis_profile(details)), 'invalid analysis profile')
    observed = semantics(load_json(output / 'report.json'))
    require(observed == case['expected_semantics'], 'retained facts, source qualifications or verdict scope differs')
    require(receipt['semantics'] == observed and finite(receipt['controller_wall_seconds'], True), 'receipt metrics differ')
    summary = (output / 'summary.txt').read_bytes()
    require(len(summary) == record['collector']['summary_bytes']
            and len(summary.rstrip(b'\r\n').split(b'\n')) == record['collector']['summary_lines'], 'summary differs')
    return record, receipt


def execute(plan_path, approved_hash):
    require(sha256(Path(plan_path)) == approved_hash, 'explicit approved plan hash required')
    plan = load_json(plan_path)
    root = Path(plan['output'])
    require(Path(plan_path).resolve() == root / 'plan.json', 'plan location differs')
    require(not (root / 'ABORTED.json').exists(), 'aborted cohorts cannot resume or produce statistics')
    require(plan['schedule'] == schedule(plan['stage'], plan['repetitions']), 'schedule changed')
    require(harness_files(root / 'harness') == plan['harness_files_sha256'], 'frozen harness changed')
    require(harness_files(Path(__file__).parent) == plan['harness_files_sha256'], 'execute the frozen reviewed harness')
    require(sha256(Path(__file__)) == plan['controller_sha256'], 'controller changed')
    require(sha256(Path(plan['reference_plan'])) == plan['reference_plan_sha256'], 'reference changed')
    module = runner(root / 'harness')
    for role, variant in plan['variants'].items():
        code_clean(variant['repo'], variant['head'])
        require(variant_provenance(module, variant, root / 'harness', plan['image']) == plan['provenance'][role],
                'runtime boundary provenance changed')
    token = uuid.uuid4().hex
    lease = root / 'controller.lock'
    write_new(lease, {'pid': os.getpid(), 'token': token, 'plan_sha256': approved_hash})
    try:
        for entry in plan['schedule']:
            variant = plan['variants'][entry['role']]
            code_clean(variant['repo'], variant['head'])
            case = next(c for c in plan['cases'] if c['project'] == entry['project'])
            output = root / 'runs' / entry['id']
            if output.exists():
                verify_completed(module, plan, entry)
                continue
            check_case(module, case)
            argv = arguments(plan, entry)
            parsed = module.arguments(argv)
            require(not parsed.cache.exists(), 'invocation Composer cache must be fresh')
            if entry['role'] == 'previous':
                if entry['temperature'] == 'first':
                    require(not parsed.source_cache_dir.exists(), 'first source cache must be fresh')
                else:
                    first = dict(entry, id=entry['id'].removesuffix('repeat') + 'first', temperature='first')
                    first_record, _ = verify_completed(module, plan, first)
                    require(module.snapshot(parsed.source_cache_dir) == first_record['source_cache_after'],
                            'first-to-repeat source cache lineage differs')
            started = time.monotonic()
            try:
                code = subprocess.run([sys.executable, '-B', str(root / 'harness/run.py'), *argv],
                                      timeout=660, check=False).returncode
            except subprocess.TimeoutExpired:
                ownership = output / 'ownership.json'
                if ownership.is_file():
                    subprocess.run([sys.executable, '-B', str(root / 'harness/run.py'), 'recover',
                                    '--ownership', str(ownership.resolve())], timeout=120, check=False)
                raise RuntimeError('outer deadline; retain ownership/recovery evidence') from None
            require(code == 0, 'failed attempt retained; stop and review before a new cohort')
            check_case(module, case)
            receipt = {'plan_sha256': digest(plan), 'entry': entry, 'argv': argv,
                       'controller_sha256': plan['controller_sha256'],
                       'controller_wall_seconds': time.monotonic() - started,
                       'semantics': semantics(load_json(output / 'report.json')),
                       'files_sha256': fingerprint(output)}
            write_new(output / 'completion.json', receipt)
            verify_completed(module, plan, entry)
        for role, variant in plan['variants'].items():
            code_clean(variant['repo'], variant['head'])
            require(variant_provenance(module, variant, root / 'harness', plan['image']) == plan['provenance'][role],
                    'runtime drift at completion')
        summary = {'status': 'completed', 'plan_sha256': approved_hash,
                   'runs': {e['id']: sha256(root / 'runs' / e['id'] / 'completion.json') for e in plan['schedule']}}
        if (root / 'summary.json').exists():
            require(load_json(root / 'summary.json') == summary, 'completed summary differs on resume')
        else:
            write_new(root / 'summary.json', summary)
    except BaseException:
        if not (root / 'ABORTED.json').exists():
            write_new(root / 'ABORTED.json', {'status': 'aborted', 'plan_sha256': approved_hash,
                      'reason': 'execution/validation interrupted; inspect retained receipts and owned resources'})
        raise
    finally:
        if load_json(lease).get('token') == token:
            lease.unlink()


def verified_rows(plan_path):
    plan = load_json(plan_path)
    require(sha256(Path(__file__)) == plan['controller_sha256'], 'export controller changed')
    root = Path(plan['output'])
    require(not (root / 'controller.lock').exists() and not (root / 'ABORTED.json').exists(), 'cohort not settled')
    require(harness_files(root / 'harness') == plan['harness_files_sha256'], 'harness changed')
    summary = load_json(root / 'summary.json')
    require(summary['status'] == 'completed' and summary['plan_sha256'] == sha256(Path(plan_path)), 'summary differs')
    require(plan['schedule'] == schedule(plan['stage'], plan['repetitions']), 'schedule differs')
    require(set(summary['runs']) == {e['id'] for e in plan['schedule']}, 'incomplete/foreign row set')
    require({p.name for p in (root / 'runs').iterdir()} == set(summary['runs']), 'unexpected run directory')
    module = runner(root / 'harness')
    rows = []
    for entry in plan['schedule']:
        require(summary['runs'][entry['id']] == sha256(root / 'runs' / entry['id'] / 'completion.json'), 'receipt changed')
        record, receipt = verify_completed(module, plan, entry)
        collector = record['collector']
        rows.append({**entry, 'head': plan['variants'][entry['role']]['head'],
                     'instrumented': plan['instrumented'], 'analysis_s': collector['analysis_seconds'],
                     'summary_ready_s': collector.get('human_summary_ready_seconds'),
                     'preliminary_direct_s': collector.get('first_direct_finding_seconds'),
                     'controller_wall_s': receipt['controller_wall_seconds'],
                     'container_wall_s': record.get('container_wall_seconds'),
                     'heap_through_analysis_bytes': collector.get('heap_peak_through_analysis_bytes'),
                     'heap_at_shutdown_bytes': collector.get('php_heap_peak_bytes'),
                     'analyzer_process_vmhwm_kib': collector.get('php_process_peak_rss_kib'),
                     'summary_bytes': collector.get('summary_bytes'), 'summary_lines': collector.get('summary_lines'),
                     'composer_starts': record['composer']['started_count'],
                     **{k: receipt['semantics'][k] for k in ('direct', 'staged', 'retained_facts', 'files_with_retained_facts', 'facts_sha256')},
                     'source_profile': json.dumps(collector.get('source_profile'), sort_keys=True),
                     'analysis_profile': json.dumps(collector.get('analysis_profile'), sort_keys=True),
                     'phase_seconds': json.dumps(collector.get('phase_seconds'), sort_keys=True),
                     'run_sha256': sha256(root / 'runs' / entry['id'] / 'run.json')})
    return rows


def extension_reason(rows):
    """Predeclared descriptive trigger, not a confidence interval or significance test."""
    medians = {role: [statistics.median([r['analysis_s'] for r in rows
                if r['project'] == project and r['role'] == role]) for project in PROJECTS]
               for role in ('cache-off', 'optimized')}
    if abs(statistics.median(medians['optimized']) / statistics.median(medians['cache-off']) - 1) < 0.1:
        return 'absolute cohort median difference below 10 percent'
    for project in PROJECTS:
        for role in ('cache-off', 'optimized'):
            values = [r['analysis_s'] for r in rows if r['project'] == project and r['role'] == role]
            if (max(values) - min(values)) / statistics.median(values) > 0.1:
                return 'within-project/role replicate spread above 10 percent of median'
    return None


def export_destination(plan, plan_path, output):
    """Resolve aliases before admitting a fresh directory disjoint from sealed inputs."""
    raw = Path(output)
    require(not os.path.lexists(raw), 'export directory must be fresh')
    target = raw.resolve()
    protected = [Path(plan['output']), Path(plan_path).resolve().parent,
                 Path(plan['reference_plan']).resolve().parent, Path(__file__).resolve().parent,
                 *[Path(p) for p in plan.get('protected_export_paths', [])]]
    for variant in plan['variants'].values():
        repo = Path(variant['repo'])
        protected.extend([repo, Path(variant.get('vendor', repo / 'vendor'))])
    for case in plan['cases']:
        protected.extend(Path(case[k]) for k in ('app', 'seed', 'seed_proof'))
    if plan.get('prior_final'):
        protected.append(Path(plan['prior_final']['prior_final_plan']).resolve().parent)
    for path in protected:
        path = path.resolve()
        require(target != path and target not in path.parents and path not in target.parents,
                'export directory overlaps a protected cohort/input/code path')
    require(not os.path.lexists(target), 'export directory must be fresh')
    return target


def export(plan_path, output):
    """Validate every expected receipt before writing concise derived CSV; no invented medians."""
    plan = load_json(plan_path)
    rows = verified_rows(plan_path)
    output = export_destination(plan, plan_path, output)
    output.mkdir(parents=True, exist_ok=False)
    with (output / 'measurements.csv').open('x', encoding='utf-8', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=list(rows[0]))
        writer.writeheader()
        writer.writerows(rows)
    lines = ['# Controlled performance stage', '', 'Stage: ' + plan['stage'],
             'Instrumented: ' + str(plan['instrumented']) + '. No application upgrade or user benefit established.',
             'Retained facts and represented files are not scanned-file counts or recall.',
             'Fresh Composer caches use identical metadata seeds; first/repeat refers to source-cache lineage.', '',
             '| Project | Role | Condition | n | Median analyzer s | Min–max s |',
             '| --- | --- | --- | ---: | ---: | --- |']
    for project in (PROFILE_PROJECTS if plan['instrumented'] else PROJECTS):
        for role in STAGES[plan['stage']]:
            for condition in (('independent',) if plan['stage'] in ('final', 'final-third') else
                               ('first',) if plan['instrumented'] else ('first', 'repeat')):
                values = [r['analysis_s'] for r in rows if r['project'] == project and r['role'] == role and r['temperature'] == condition]
                lines.append(f'| {project} | {role} | {condition} | {len(values)} | {statistics.median(values):.3f} | {min(values):.3f}–{max(values):.3f} |')
    if plan['stage'] == 'final':
        lines += ['', 'Third-pair trigger: ' + (extension_reason(rows) or 'not met') + '.',
                  'A third pair requires a separately frozen/reviewed final-third plan on all ten projects.',
                  'Two or three samples do not establish statistical significance.']
    if not plan['instrumented']:
        left, right = STAGES[plan['stage']]
        lines += ['', 'Savings below use 1 - candidate/baseline; negative means slower.',
                  'Ratio of medians and median of paired percentages are different descriptive quantities.',
                  'Replicate invocations are equally weighted; no significance or user-benefit claim follows.', '',
                  '| Condition | Baseline median s | Candidate median s | Ratio-of-medians savings | Median paired savings |',
                  '| --- | ---: | ---: | ---: | ---: |']
        for condition in (('independent',) if plan['stage'] in ('final', 'final-third') else ('first', 'repeat')):
            values = {role: {(r['project'], r['repetition']): r['analysis_s'] for r in rows
                            if r['role'] == role and r['temperature'] == condition} for role in (left, right)}
            require(values[left].keys() == values[right].keys(), 'paired membership differs')
            baseline = statistics.median(values[left].values())
            candidate = statistics.median(values[right].values())
            paired = statistics.median(1 - values[right][k] / value for k, value in values[left].items())
            lines.append(f'| {condition} | {baseline:.3f} | {candidate:.3f} | {1-candidate/baseline:.1%} | {paired:.1%} |')
    (output / 'results.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='operation', required=True)
    command = commands.add_parser('plan')
    command.add_argument('--config', type=Path, required=True)
    command.add_argument('--output', type=Path, required=True)
    command = commands.add_parser('run')
    command.add_argument('--plan', type=Path, required=True)
    command.add_argument('--approved-plan-sha256', required=True)
    command = commands.add_parser('export')
    command.add_argument('--plan', type=Path, required=True)
    command.add_argument('--output', type=Path, required=True)
    args = parser.parse_args(argv)
    if args.operation == 'plan':
        prepare(args.config, args.output)
        print('Prepared; independent plan review and exact hash authorization required before run')
    elif args.operation == 'run':
        execute(args.plan, args.approved_plan_sha256)
    else:
        export(args.plan, args.output)


if __name__ == '__main__':
    main()
