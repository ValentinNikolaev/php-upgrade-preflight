#!/usr/bin/env python3
"""Verify R4 evidence and project JSON/Markdown tables; missing measurements stay null."""
import argparse
import math
import re
from collections import Counter
from pathlib import Path

from acquire import SOURCE_ALGORITHM, load_json, projects, sha256, source_pin, write_new
from campaign import attempt_dirs, clean, digest, fingerprint


def numeric(value, name):
    if value is not None and (isinstance(value, bool) or not isinstance(value, (int, float)) or
                              not math.isfinite(value) or value < 0):
        raise ValueError('invalid metric: ' + name)
    return value


def partial_json(path):
    """A killed writer may leave no JSON or a truncated document; never infer success."""
    try:
        return load_json(path)
    except (OSError, ValueError):
        return None


def record_error(record, raw, plan, spec):
    try:
        if record is None:
            raise ValueError('missing or truncated harness record')
        validate_record(record, raw, plan, spec)
    except (ValueError, TypeError, KeyError, AttributeError) as error:
        return str(error)
    return None


def verify_attempt(attempt, plan, spec):
    attempt = Path(attempt)
    invocation_path = attempt / 'invocation.json'
    if not invocation_path.exists():
        return None
    invocation = partial_json(invocation_path)
    if invocation is None:
        return None
    if not isinstance(invocation, dict):
        raise ValueError('invalid invocation object')
    if invocation.get('plan_sha256') != digest(plan) or invocation.get('spec') != spec:
        raise ValueError('attempt does not match declared plan')
    project = next(p for p in projects(plan['manifest']) if p['id'] == spec['project'])
    if invocation.get('source_snapshot_algorithm') != SOURCE_ALGORITHM or invocation.get('source_snapshot_sha256') != source_pin(project):
        raise ValueError('invocation source differs from frozen plan')
    completion = attempt / 'completion.json'
    if not completion.exists():
        return None
    receipt = partial_json(completion)
    if receipt is None:
        return None
    if not isinstance(receipt, dict):
        raise ValueError('invalid completion object')
    if receipt.get('plan_sha256') != digest(plan) or receipt.get('run_id') != spec['id']:
        raise ValueError('completion does not match declared plan')
    if receipt.get('status') not in ('completed', 'failed', 'unavailable'):
        raise ValueError('invalid attempt status')
    numeric(receipt.get('external_wall_seconds'), 'external_wall_seconds')
    if receipt.get('external_wall_seconds') is None:
        raise ValueError('completion missing external wall time')
    actual = fingerprint(attempt)
    del actual['completion.json']
    if actual != receipt.get('files_sha256'):
        raise ValueError('artifact hash inventory mismatch: ' + str(attempt))
    if receipt.get('cache_path') != invocation.get('cache_path'):
        raise ValueError('completion cache differs from invocation')
    raw = attempt / 'raw' / 'run.json'
    record = partial_json(raw)
    error = record_error(record, attempt / 'raw', plan, spec)
    if error and not (receipt['status'] != 'completed' and receipt.get('validation_error') == error):
        raise ValueError(error)
    recovery = receipt.get('recovery')
    if recovery is not None:
        if not isinstance(recovery, dict):
            raise ValueError('invalid recovery receipt')
        owner = partial_json(attempt / 'raw' / 'ownership.json')
        if recovery.get('returncode') == 0 and (not clean(recovery) or not isinstance(owner, dict) or owner.get('cleanup') != recovery.get('cleanup')):
            raise ValueError('recovery disagrees with durable ownership cleanup')
    safe = (recovery.get('returncode') == 0 and clean(recovery)) if recovery else clean(record)
    if receipt.get('cleanup_verified') is not safe or (not safe and receipt.get('cache_after_sha256') is not None):
        raise ValueError('unverified cleanup or unsafe cache inspection')
    if receipt['status'] == 'completed':
        if not record or record.get('status') != 'completed' or record.get('immutable') is not True or receipt.get('exit_code') != 0 or receipt.get('external_timeout'):
            raise ValueError('false completed attempt')
        if record.get('input_before') != record.get('input_after'):
            raise ValueError('inconsistent immutability evidence')
        if not all(record.get('cleanup', {}).get(k) is True for k in ('container_removed', 'host_scratch_removed')):
            raise ValueError('completed record has unverified cleanup')
        if not safe or receipt.get('source_after_sha256') != source_pin(project):
            raise ValueError('completed attempt missing verified source or cleanup')
    return receipt


def validate_record(record, raw, plan, spec):
    if not isinstance(record, dict):
        raise ValueError('invalid harness record object')
    if record.get('status') not in ('completed', 'failed', 'watchdog_timeout', 'incomplete'):
        raise ValueError('unknown harness status')
    expected = {**spec['request'], 'memory_limit': spec['memory_limit'], 'network': spec['network'],
                'probe': spec['probe'], 'image': plan['image'],
                'composer_memory_limit': plan['composer_memory_limit'],
                'scenario_timeout': plan['scenario_timeout_seconds'],
                'diagnostic_timeout': plan['diagnostic_timeout_seconds'],
                'overall_timeout': plan['overall_timeout_seconds']}
    expected['github_transport'] = plan['github_transport']
    if any(record.get('request', {}).get(k, 'default' if k == 'github_transport' else None) != v for k, v in expected.items()):
        raise ValueError('harness request differs from declaration')
    provenance = record.get('provenance')
    if record.get('status') == 'completed' and not provenance:
        raise ValueError('completed record missing provenance')
    if provenance:
        expected_provenance = {'tool_git_head': plan['repo_commit'],
                               'tool_composer_lock_sha256': plan['dependency_lock_sha256'],
                               'tool_runtime_tree_sha256': plan['tool_runtime_tree_sha256'],
                               'harness_files_sha256': plan['harness_files_sha256']}
        if plan['image'].startswith('sha256:'):
            expected_provenance['image_id'] = plan['image']
        if any(provenance.get(k) != v for k, v in expected_provenance.items()):
            raise ValueError('run provenance differs from frozen plan')
    project = next(p for p in projects(plan['manifest']) if p['id'] == spec['project'])
    for key in ('input_before', 'input_after'):
        snapshot = record.get(key)
        if snapshot is not None or record.get('status') == 'completed':
            if not isinstance(snapshot, dict) or not isinstance(snapshot.get('sha256'), str) or not re.fullmatch(r'[0-9a-f]{64}', snapshot['sha256']):
                raise ValueError('missing or invalid source snapshot: ' + key)
            shape = snapshot.get('shape')
            if not isinstance(shape, dict) or any(type(shape.get(k)) is not int or shape[k] < 0 for k in ('files', 'directories', 'symlinks', 'bytes', 'php_files', 'php_bytes')):
                raise ValueError('missing or invalid source snapshot shape: ' + key)
            if shape['files'] < 2 or shape['php_files'] > shape['files'] or shape['php_bytes'] > shape['bytes']:
                raise ValueError('inconsistent source snapshot shape: ' + key)
            for field, count in (('file_count', 'files'), ('source_bytes', 'bytes'), ('php_files', 'php_files')):
                if field in project and project[field] != shape[count]:
                    raise ValueError('source snapshot inventory differs from pinned manifest')
            if snapshot['sha256'] != source_pin(project):
                raise ValueError('run source snapshot differs from pinned manifest')
            for filename, field in (('composer.json_sha256', 'composer_json_sha256'), ('composer.lock_sha256', 'composer_lock_sha256')):
                if snapshot.get(filename) != project[field]:
                    raise ValueError('run input differs from pinned manifest')
    if record.get('status') == 'completed' and (record.get('immutable') is not True or record['input_before'] != record['input_after']):
        raise ValueError('inconsistent immutability evidence')
    if record.get('status') == 'completed' and not clean(record):
        raise ValueError('completed record has unverified cleanup')
    collector = record.get('collector')
    if record.get('status') == 'completed' and (not isinstance(collector, dict) or collector.get('status') != 'completed'):
        raise ValueError('completed record missing completed collector')
    for section, fields in ((record, ('container_wall_seconds',)),
                            (collector or {}, ('analysis_seconds', 'php_heap_peak_bytes', 'php_process_peak_rss_kib')),
                            (record.get('analyzer_resources') or {}, ('elapsed_seconds', 'max_rss_kib')),
                            (record.get('container') or {}, ('container_memory_v1_peak_bytes',))):
        if not isinstance(section, dict):
            raise ValueError('invalid resource metrics object')
        for key in fields:
            numeric(section.get(key), key)
    composer = record.get('composer')
    if record.get('status') == 'completed' and not isinstance(composer, dict):
        raise ValueError('completed record missing Composer inventory')
    if composer is not None:
        if not isinstance(composer, dict):
            raise ValueError('invalid Composer inventory')
        invocations = composer.get('invocations')
        if not isinstance(invocations, list) or type(composer.get('started_count')) is not int or composer['started_count'] != len(invocations):
            raise ValueError('inconsistent Composer starts')
        if any(not isinstance(v, dict) or not isinstance(v.get('id'), str) or not v['id'] or not isinstance(v.get('resources'), dict) for v in invocations):
            raise ValueError('invalid Composer invocation')
        if len({v['id'] for v in invocations}) != len(invocations):
            raise ValueError('duplicate Composer invocation')
        if type(composer.get('timed_count')) is not int or composer['timed_count'] != sum(v['resources'].get('available') is True for v in invocations):
            raise ValueError('inconsistent Composer timed count')
        for invocation in invocations:
            if type(invocation['resources'].get('available')) is not bool:
                raise ValueError('invalid Composer resource availability')
            for key in ('elapsed_seconds', 'max_rss_kib'):
                numeric(invocation.get('resources', {}).get(key), key)
    for filename in ('report.json', 'report.md'):
        path = raw / filename
        if record.get('report_bytes', {}).get(filename) != (path.stat().st_size if path.is_file() else None):
            raise ValueError('report byte count mismatch')
    report_path = raw / 'report.json'
    if report_path.exists():
        report = load_json(report_path)
        if not isinstance(report, dict):
            raise ValueError('invalid canonical report object')
        states = (record.get('collector') or {}).get('report_states') or {}
        for key in ('resolution', 'staged_resolution'):
            section = report.get(key)
            if not isinstance(section, dict) or section.get('status') not in ('feasible', 'feasible_with_changes', 'blocked', 'unknown'):
                raise ValueError('missing or invalid canonical report section: ' + key)
            if states.get(key) != section['status']:
                raise ValueError('collector disagrees with canonical report')
        if not isinstance(report['resolution'].get('scenarios'), list) or not isinstance(report['staged_resolution'].get('stages'), list) or not isinstance(report.get('uncertainties'), list):
            raise ValueError('missing canonical scenario, stage or uncertainty inventory')
        canonical_scenarios(report)
    elif record.get('status') == 'completed':
        raise ValueError('completed harness record missing canonical report')


def canonical_scenarios(report):
    direct, stages = report['resolution']['scenarios'], report['staged_resolution']['stages']
    staged = []
    for stage in stages:
        if not isinstance(stage, dict) or not isinstance(stage.get('attempts'), list):
            raise ValueError('invalid canonical stage attempts')
        for attempt in stage['attempts']:
            if not isinstance(attempt, dict):
                raise ValueError('invalid canonical stage attempt')
            if attempt.get('scenario') is not None:
                staged.append(attempt['scenario'])
    if any(not isinstance(s, dict) or not isinstance(s.get('outcome'), str) or not isinstance(s.get('diagnostics'), list) for s in direct + staged):
        raise ValueError('invalid canonical scenario diagnostics')
    return direct, staged


def row_for(attempt, plan, spec):
    receipt = verify_attempt(attempt, plan, spec)
    if not receipt or receipt.get('validation_error'):
        return {'run_id': spec['id'], 'project': spec['project'], 'category': spec['category'],
                'attempt': attempt.name, 'status': receipt['status'] if receipt else 'interrupted',
                'validation_error': receipt.get('validation_error') if receipt else 'missing or truncated attempt evidence',
                'metrics': None, 'report_states': None, 'report_counts': None}
    record_path = attempt / 'raw' / 'run.json'
    record = load_json(record_path) if record_path.exists() else {}
    collector = record.get('collector') or {}
    composer = record.get('composer') or {}
    resources = record.get('analyzer_resources') or {}
    container = record.get('container') or {}
    invocations = composer.get('invocations', [])
    report_path = attempt / 'raw' / 'report.json'
    report = load_json(report_path) if report_path.exists() else None
    report_counts = None
    if report and isinstance(report.get('resolution', {}).get('scenarios'), list) and isinstance(report.get('staged_resolution', {}).get('stages'), list):
        direct, staged = canonical_scenarios(report)
        stages = report['staged_resolution']['stages']
        scenarios = direct + staged
        report_counts = {'direct_scenarios': len(direct), 'staged_scenarios': len(staged),
                         'stages': len(stages),
                         'direct_diagnostic_calls': sum(len(s['diagnostics']) for s in direct),
                         'staged_diagnostic_calls': sum(len(s['diagnostics']) for s in staged),
                         'diagnostic_calls': sum(len(s['diagnostics']) for s in scenarios) if all(isinstance(s.get('diagnostics'), list) for s in scenarios) else None,
                         'scenario_outcomes': dict(Counter(s.get('outcome') for s in scenarios)),
                         'uncertainties': len(report['uncertainties']) if isinstance(report.get('uncertainties'), list) else None}
    child_peaks = [v.get('resources', {}).get('max_rss_kib') for v in invocations]
    measured = [p for p in child_peaks if p is not None]
    metrics = {'external_wall_seconds': receipt['external_wall_seconds'] if receipt else None,
               'container_wall_seconds': record.get('container_wall_seconds'),
               'analysis_seconds': collector.get('analysis_seconds'),
               'php_heap_peak_bytes': collector.get('php_heap_peak_bytes'),
               'php_process_peak_rss_kib': collector.get('php_process_peak_rss_kib'),
               'gnu_time_envelope_max_rss_kib': resources.get('max_rss_kib'),
               'composer_max_individual_rss_kib': max(measured) if measured else None,
               'composer_rss_complete': len(measured) == len(invocations) if invocations else None,
               'container_memory_v1_peak_bytes': container.get('container_memory_v1_peak_bytes'),
               'composer_starts': composer.get('started_count'), 'composer_timed_completions': composer.get('timed_count'),
               'report_json_bytes': record.get('report_bytes', {}).get('report.json')}
    for key, value in metrics.items():
        if key != 'composer_rss_complete':
            numeric(value, key)
    probe_observed = None
    if spec['category'] == 'probe':
        if spec['probe'] != 'normal':
            probe_observed = any(v.get('probe_applied') == spec['probe'] for v in invocations)
            if spec['probe'] == 'cache-failure':
                probe_observed = probe_observed and container.get('cache_fault_requested') is True and container.get('cache_path_writable') is False
        elif spec['network'] == 'none':
            probe_observed = record.get('request', {}).get('network') == 'none' and record.get('input_before') is not None and composer.get('started_count', 0) > 0
        else:
            probe_observed = collector.get('collector_memory_limit') == spec['memory_limit']
    return {'run_id': spec['id'], 'project': spec['project'], 'category': spec['category'],
            'attempt': attempt.name, 'status': receipt['status'] if receipt else 'interrupted',
            'harness_status': record.get('status'), 'probe_observed': probe_observed,
            'probe_outcome': 'observed' if probe_observed is True else ('unavailable' if probe_observed is False else None),
            'immutable': record.get('immutable'), 'watchdog': record.get('watchdog'),
            'container_exit_code': record.get('container_exit_code'),
            'report_states': collector.get('report_states'), 'unknowns': collector.get('unknowns'),
            'report_counts': report_counts,
            'composer_invocations': invocations if composer else None,
            'cleanup': record.get('cleanup'), 'metrics': metrics,
            'provenance': record.get('provenance'),
            'run_record_sha256': sha256(record_path) if record_path.exists() else None,
            'completion_sha256': sha256(attempt / 'completion.json') if receipt else None}


def summarize(root):
    root = Path(root)
    plan = load_json(root / 'plan.json')
    if len({spec['id'] for spec in plan['runs']}) != len(plan['runs']):
        raise ValueError('duplicate declared run')
    known = {spec['id'] for spec in plan['runs']}
    runs_dir = root / 'runs'
    if runs_dir.exists() and any(p.name not in known for p in runs_dir.iterdir()):
        raise ValueError('undeclared run directory')
    rows, latest = [], []
    for spec in plan['runs']:
        attempts = attempt_dirs(root, spec['id'])
        current = [row_for(attempt, plan, spec) for attempt in attempts]
        rows.extend(current)
        latest.append(current[-1] if current else {'run_id': spec['id'], 'project': spec['project'],
                      'category': spec['category'], 'status': 'not_run', 'metrics': None, 'report_states': None})
        for attempt, row in zip(attempts, current):
            if row['status'] == 'interrupted':
                continue
            invocation_path = attempt / 'invocation.json'
            if not invocation_path.exists():
                continue
            invocation = load_json(invocation_path)
            if not spec['warm_after']:
                if invocation.get('cache_before_sha256') != {} or invocation.get('cold_receipt_sha256') is not None:
                    raise ValueError('cold run did not use an empty independent cache')
            else:
                predecessors = attempt_dirs(root, spec['warm_after'])
                matches = [p for p in predecessors if (p / 'completion.json').exists() and
                           sha256(p / 'completion.json') == invocation.get('cold_receipt_sha256')]
                if len(matches) != 1:
                    raise ValueError('warm run missing cold predecessor evidence')
                predecessor = load_json(matches[0] / 'completion.json')
                if predecessor.get('status') != 'completed' or predecessor.get('cache_path') == invocation.get('cache_path') or predecessor.get('cache_after_sha256') != invocation.get('cache_before_sha256'):
                    raise ValueError('warm cache lineage mismatch')
    complete = all(r['status'] == 'completed' and r.get('probe_observed') is not False for r in latest)
    return {'summary_version': 1, 'status': 'completed' if complete else 'partial',
            'plan_sha256': digest(plan), 'projects_sha256': plan['projects_sha256'],
            'planned': {category: sum(s['category'] == category for s in plan['runs'])
                        for category in ('primary', 'probe', 'supplemental')},
            'attempt_count': len(rows), 'failed_attempt_count': sum(r['status'] == 'failed' for r in rows),
            'metric_methods': {'external_wall_seconds': 'host monotonic invocation including harness hashing/startup/cleanup',
                               'analysis_seconds': 'collector hrtime around analyzeUpgrade only',
                               'php_heap_peak_bytes': 'PHP memory_get_peak_usage(true)',
                               'php_process_peak_rss_kib': 'collector Linux /proc/self/status VmHWM',
                               'gnu_time_envelope_max_rss_kib': 'GNU time maxRSS; may include waited descendants',
                               'composer_max_individual_rss_kib': 'maximum observed resources.max_rss_kib per Composer invocation; '
                                                                  'composer_php_process scope = own Linux /proc/self/status VmHWM; '
                                                                  'other scopes retained in composer_invocations[].resources.scope; '
                                                                  'absent scope = unknown (including legacy); not concurrent sum',
                               'container_memory_v1_peak_bytes': 'cgroup v1 peak; null where unavailable'},
            'latest': latest, 'attempts': rows}


def markdown(summary):
    lines = ['# R4 campaign evidence', '', f"Status: {summary['status']}; {summary['attempt_count']} attempts; {summary['failed_attempt_count']} failed attempts.",
             '', 'Missing measurements are `null`. Observed probes describe execution, not a product pass. All attempts remain in JSON.', '',
             '| Run | Attempt status | Direct | Staged | Analysis seconds | PHP heap bytes | Own RSS KiB | Composer starts |',
             '| --- | --- | --- | --- | ---: | ---: | ---: | ---: |']
    def cell(value):
        return 'null' if value is None else str(value).replace('|', '\\|').replace('\n', ' ')
    for row in summary['latest']:
        states, metrics = row.get('report_states') or {}, row.get('metrics') or {}
        values = [row['run_id'], row['status'], states.get('resolution'), states.get('staged_resolution'),
                  metrics.get('analysis_seconds'), metrics.get('php_heap_peak_bytes'),
                  metrics.get('php_process_peak_rss_kib'), metrics.get('composer_starts')]
        lines.append('| ' + ' | '.join(map(cell, values)) + ' |')
    return '\n'.join(lines) + '\n'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--campaign', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True, help='fresh nonexistent summary directory')
    args = parser.parse_args()
    summary = summarize(args.campaign)
    args.output.mkdir(parents=True, exist_ok=False)
    write_new(args.output / 'summary.json', summary)
    (args.output / 'summary.md').write_text(markdown(summary), encoding='utf-8')


if __name__ == '__main__':
    main()
