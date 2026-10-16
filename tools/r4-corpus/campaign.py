#!/usr/bin/env python3
"""Predeclare and execute serial R4 runs; preserve every attempt and resume by hashes."""
import argparse
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import time

from acquire import SOURCE_ALGORITHM, load_json, projects, sha256, source_pin, verify_app, write_new


def fingerprint(root):
    root = Path(root)
    if root.is_symlink() or not root.is_dir():
        raise ValueError('evidence or cache directory missing or symlinked')
    result = {}
    for path in sorted(root.rglob('*')):
        if path.is_symlink():
            raise ValueError('symlink in evidence or cache tree')
        if path.is_file():
            result[path.relative_to(root).as_posix()] = sha256(path)
    return result


def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, separators=(',', ':'), allow_nan=False).encode()).hexdigest()


def matrix(manifest):
    projects(manifest)
    runs = []
    for p in manifest['projects']:
        for kind, request, cache in (('nearest-cold', 'nearest', 'nearest'),
                                     ('nearest-warm', 'nearest', 'nearest'),
                                     ('longest-cold', 'longest', 'longest')):
            runs.append({'id': p['id'] + '-' + kind, 'project': p['id'], 'category': 'primary',
                         'request': p['requests'][request], 'cache': p['id'] + '-' + cache,
                         'warm_after': p['id'] + '-nearest-cold' if kind == 'nearest-warm' else None,
                         'cold_repeat': kind == 'longest-cold' and p['requests']['nearest'] == p['requests']['longest'],
                         'memory_limit': '1024M', 'network': 'bridge', 'probe': 'normal'})
    for name, memory, network, probe in (
        ('memory-128', '128M', 'bridge', 'normal'),
        ('memory-1024', '1024M', 'bridge', 'normal'),
        ('short-timeout', '1024M', 'bridge', 'timeout'),
        ('network-denied', '1024M', 'none', 'normal'),
        ('child-failure', '1024M', 'bridge', 'child-failure'),
        ('cache-failure', '1024M', 'bridge', 'cache-failure'),
    ):
        lychee = next(p for p in manifest['projects'] if p['id'] == 'lychee')
        runs.append({'id': 'probe-' + name, 'project': 'lychee', 'category': 'probe',
                     'request': lychee['requests']['nearest'], 'cache': 'probe-' + name,
                     'warm_after': None, 'cold_repeat': False, 'memory_limit': memory,
                     'network': network, 'probe': probe})
    for p in manifest.get('supplemental_projects', []):
        runs.append({'id': p['id'] + '-longest-cold', 'project': p['id'], 'category': 'supplemental',
                     'request': p['requests']['longest'], 'cache': p['id'] + '-longest',
                     'warm_after': None, 'cold_repeat': False, 'memory_limit': '1024M',
                     'network': 'bridge', 'probe': 'normal'})
    return runs


def make_plan(args):
    manifest = load_json(args.projects)
    for project in projects(manifest):
        verify_app(args.apps / project['id'], project, require_source=True)
    tool_files = {p.name: sha256(p) for p in sorted(args.harness.parent.iterdir())
                  if p.is_file() and (p.suffix in ('.py', '.php', '.sh') or p.name == 'Dockerfile')}
    runtime_files = {p.relative_to(args.repo).as_posix(): sha256(p)
                     for directory in ('packages', 'vendor')
                     for p in sorted((args.repo / directory).rglob('*')) if p.is_file()}
    return {'campaign_version': 1, 'projects_sha256': sha256(args.projects), 'manifest': manifest,
            'apps': str(args.apps.resolve()), 'repo': str(args.repo.resolve()),
            'harness': str(args.harness.resolve()), 'harness_files_sha256': tool_files,
            'repo_commit': subprocess.check_output(['git', '-C', str(args.repo), 'rev-parse', 'HEAD'], text=True).strip(),
            'dependency_lock_sha256': sha256(args.repo / 'composer.lock'),
            'tool_runtime_tree_sha256': hashlib.sha256(json.dumps(runtime_files, sort_keys=True).encode()).hexdigest(),
            'github_transport': args.github_transport,
            'image': args.image, 'budget_seconds': args.budget_seconds,
            'external_timeout_seconds': 600, 'recovery_timeout_seconds': 120,
            'overall_timeout_seconds': args.overall_timeout,
            'scenario_timeout_seconds': 45, 'diagnostic_timeout_seconds': 15,
            'composer_memory_limit': '1024M', 'runs': matrix(manifest)}


def attempt_dirs(root, identity):
    directory = Path(root) / 'runs' / identity
    if not directory.exists():
        return []
    result = sorted(directory.iterdir())
    if any(not p.is_dir() or not p.name.startswith('attempt-') or not p.name[8:].isdigit() for p in result):
        raise ValueError('unexpected attempt path')
    return result


def command(plan, spec, output, cache):
    result = [sys.executable, plan['harness'], '--app', str(Path(plan['apps']) / spec['project']),
              '--output', str(output), '--cache', str(cache), '--repo', plan['repo'], '--image', plan['image'],
              '--github-transport', plan['github_transport']]
    for key, value in {**spec['request'], 'memory_limit': spec['memory_limit'], 'network': spec['network'],
                       'composer_memory_limit': plan['composer_memory_limit'], 'probe': spec['probe'],
                       'scenario_timeout': plan['scenario_timeout_seconds'],
                       'diagnostic_timeout': plan['diagnostic_timeout_seconds'],
                       'overall_timeout': plan['overall_timeout_seconds']}.items():
        result += ['--' + key.replace('_', '-'), str(value)]
    return result


def clean(record):
    return isinstance(record, dict) and isinstance(record.get('cleanup'), dict) and all(
        record['cleanup'].get(k) is True for k in ('container_removed', 'host_scratch_removed'))


def recover_owned(plan, attempt):
    """The harness owns identity, PID and OS-temp containment verification."""
    from summarize import partial_json
    ownership = attempt / 'raw' / 'ownership.json'
    result = {'returncode': None, 'ownership_path': str(ownership.resolve()), 'cleanup': {}, 'error': None}
    if not ownership.is_file():
        result['error'] = 'Missing durable ownership; manual resource review required before cache inspection or retry'
        return result
    try:
        with (attempt / 'recovery.stdout.log').open('xb') as stdout, (attempt / 'recovery.stderr.log').open('xb') as stderr:
            result['returncode'] = subprocess.run(
                [sys.executable, plan['harness'], 'recover', '--ownership', str(ownership.resolve())],
                stdout=stdout, stderr=stderr, timeout=plan.get('recovery_timeout_seconds', 120)).returncode
        owner = partial_json(ownership)
        if isinstance(owner, dict):
            result['cleanup'] = owner.get('cleanup', {})
        if result['returncode'] != 0 or not clean(result):
            result['error'] = 'Owned-resource cleanup unverified; inspect ownership and recovery logs before cache inspection or retry'
    except (OSError, subprocess.SubprocessError) as error:
        result['error'] = 'Recovery failed (' + type(error).__name__ + '); ownership retained for manual review'
    return result


def execute(root, plan, only=None):
    from summarize import partial_json, record_error, verify_attempt
    root = Path(root).resolve()
    # Exclusive controller lease: an interrupted controller leaves this for explicit review.
    lease = root / 'controller.lock'
    with lease.open('x'):
        pass
    try:
        for project in projects(plan['manifest']):
            verify_app(Path(plan['apps']) / project['id'], project, require_source=True)
        spent = 0.0
        completed = {}
        for spec in plan['runs']:
            attempts = attempt_dirs(root, spec['id'])
            for attempt in attempts:
                receipt = verify_attempt(attempt, plan, spec)
                spent += receipt['external_wall_seconds'] if receipt else 600
                if not receipt:
                    raise ValueError('interrupted attempt requires cleanup review before resume: ' + str(attempt))
            if attempts:
                receipt = verify_attempt(attempts[-1], plan, spec)
                if receipt and receipt['status'] == 'completed':
                    completed[spec['id']] = (attempts[-1], receipt)
                elif receipt:
                    raw = attempts[-1] / 'raw' / 'run.json'
                    record = partial_json(raw)
                    recovery = receipt.get('recovery')
                    safe = (recovery.get('returncode') == 0 and clean(recovery)) if recovery else clean(record)
                    if not safe or receipt.get('source_after_sha256') != source_pin(next(p for p in projects(plan['manifest']) if p['id'] == spec['project'])):
                        raise ValueError('failed attempt requires safety review before retry')
        ids = {s['id'] for s in plan['runs']}
        if only and not set(only) <= ids:
            raise ValueError('unknown selected run id')
        for spec in plan['runs']:
            if spec['id'] in completed or (only and spec['id'] not in only):
                continue
            if spent + plan.get('external_timeout_seconds', 600) + plan.get('recovery_timeout_seconds', 120) > plan['budget_seconds']:
                print('Campaign partial: remaining aggregate budget is below one run plus cleanup reserve', file=sys.stderr)
                return 1
            project = next(p for p in projects(plan['manifest']) if p['id'] == spec['project'])
            verify_app(Path(plan['apps']) / spec['project'], project, require_source=True)
            parent = completed.get(spec['warm_after']) if spec['warm_after'] else None
            if spec['warm_after'] and not parent:
                raise ValueError('warm run requires a verifiably completed cold predecessor')
            attempts = attempt_dirs(root, spec['id'])
            attempt = root / 'runs' / spec['id'] / f'attempt-{len(attempts) + 1:04d}'
            cache = root / 'caches' / (spec['id'] + '-' + attempt.name)
            if cache.exists() or cache.is_symlink():
                raise ValueError('refusing existing attempt cache')
            if parent:
                cold_cache = Path(parent[1]['cache_path'])
                if fingerprint(cold_cache) != parent[1]['cache_after_sha256']:
                    raise ValueError('warm cache changed since cold completion')
                cache.parent.mkdir(parents=True, exist_ok=True)
                shutil.copytree(cold_cache, cache)
                if fingerprint(cache) != parent[1]['cache_after_sha256'] or fingerprint(cold_cache) != parent[1]['cache_after_sha256']:
                    raise ValueError('warm cache copy differs from cold completion')
            else:
                cache.mkdir(parents=True, exist_ok=False)
            # Source/cache lineage checks finish before allocating an attempt number.
            attempt.mkdir(parents=True, exist_ok=False)
            invocation = {'plan_sha256': digest(plan), 'spec': spec, 'cache_path': str(cache.resolve()),
                          'cache_before_sha256': fingerprint(cache),
                          'source_snapshot_algorithm': SOURCE_ALGORITHM, 'source_snapshot_sha256': source_pin(project),
                          'cold_receipt_sha256': sha256(parent[0] / 'completion.json') if parent else None,
                          'argv': command(plan, spec, attempt / 'raw', cache)}
            write_new(attempt / 'invocation.json', invocation)
            started = time.monotonic()
            exit_code, timed_out, failure = None, False, None
            with (attempt / 'stdout.log').open('xb') as stdout, (attempt / 'stderr.log').open('xb') as stderr:
                try:
                    exit_code = subprocess.run(invocation['argv'], stdout=stdout, stderr=stderr,
                                               timeout=plan.get('external_timeout_seconds', 600)).returncode
                except subprocess.TimeoutExpired:
                    timed_out = True
                except OSError as error:
                    failure = type(error).__name__
            raw = attempt / 'raw' / 'run.json'
            record = partial_json(raw)
            recovery = recover_owned(plan, attempt) if timed_out or not clean(record) else None
            safe = (recovery.get('returncode') == 0 and clean(recovery)) if recovery else clean(record)
            validation_error = record_error(record, attempt / 'raw', plan, spec)
            source_after = None
            if safe:
                try:
                    verify_app(Path(plan['apps']) / spec['project'], project, require_source=True)
                    source_after = source_pin(project)
                except (OSError, ValueError) as error:
                    validation_error = validation_error or str(error)
            status = 'completed' if safe and not validation_error and record.get('status') == 'completed' and exit_code == 0 and not timed_out else 'failed'
            elapsed = time.monotonic() - started
            receipt = {'status': status, 'plan_sha256': digest(plan), 'run_id': spec['id'],
                       'external_wall_seconds': elapsed, 'external_timeout': timed_out, 'exit_code': exit_code,
                       'failure_class': failure, 'validation_error': validation_error, 'recovery': recovery,
                       'cleanup_verified': safe, 'source_after_sha256': source_after,
                       'cache_path': str(cache.resolve()),
                       'cache_after_sha256': fingerprint(cache) if safe else None, 'files_sha256': fingerprint(attempt)}
            write_new(attempt / 'completion.json', receipt)
            verify_attempt(attempt, plan, spec)
            spent += elapsed
            if status != 'completed' and not (spec['category'] == 'probe' and safe and not timed_out and not validation_error):
                print('Campaign partial: failed attempt retained at ' + str(attempt), file=sys.stderr)
                return 1
            if status == 'completed':
                completed[spec['id']] = (attempt, receipt)
        return 0 if len(completed) == len(plan['runs']) else 1
    finally:
        lease.unlink()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ('projects', 'apps', 'repo', 'harness', 'output'):
        parser.add_argument('--' + name, type=Path, required=True)
    parser.add_argument('--image', required=True, help='fixed Docker image ID/digest')
    parser.add_argument('--github-transport', choices=('default', 'public-https'), default='default')
    parser.add_argument('--budget-seconds', type=int, default=9 * 3600)
    parser.add_argument('--overall-timeout', type=int, default=540, help='inner watchdog leaves cleanup headroom below external600s')
    parser.add_argument('--execute', action='store_true', help='without this flag only write the complete plan')
    parser.add_argument('--resume', action='store_true')
    parser.add_argument('--only', help='comma-separated declared IDs for bounded pilots; full matrix stays declared')
    args = parser.parse_args()
    if not 600 <= args.budget_seconds <= 32400 or not 1 <= args.overall_timeout <= 540:
        parser.error('budget must be600..32400 seconds; inner watchdog1..540 seconds')
    if not args.image.startswith('sha256:') and '@sha256:' not in args.image:
        parser.error('image must be pinned by ID or digest')
    plan = make_plan(args)
    if args.resume:
        if load_json(args.output / 'plan.json') != plan:
            raise ValueError('resume provenance or plan changed')
    else:
        args.output.mkdir(parents=True, exist_ok=False)
        write_new(args.output / 'plan.json', plan)
    if args.execute:
        return execute(args.output, plan, args.only.split(',') if args.only else None)
    return 0


if __name__ == '__main__':
    sys.exit(main())
