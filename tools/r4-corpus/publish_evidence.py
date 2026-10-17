#!/usr/bin/env python3
"""Publish verified R4 evidence without copying private runner metadata.

Run from the code checkout, or pass --tools /path/to/tools/r4-corpus.
Required: --campaign <frozen campaign> --output <fresh directory>.
Optional, repeatable: --diagnostics LABEL=/explicit/legacy/raw/directory.

Only explicit diagnostic directories are read; no diagnostic discovery occurs.
Diagnostics are unverified extras, never campaign slots. No subprocesses,
network, target execution or source/cache traversal is performed by this file.
The existing summarizer reads attempt files to verify its artifact inventories.
Use only after the controller has stopped: a controller.lock blocks publication.

Valid reports are UTF-8 canonical artifacts copied unchanged into deterministic gzip
(empty filename, mtime=0, compression=9). Reproducibility assumes the same zlib
implementation. Any detected host-path leak fails publication, without redaction.
The path scan is a filesystem privacy check, not a general secrets detector.
Malformed unsealed artifacts are omitted with original byte hashes/sizes and
explicit unavailable dispositions; verified artifacts still fail validation.
This is a publisher-verified portable projection/attestation, not a bundle from
which the excluded private plan/receipt/invocation/cache/cleanup checks can be
independently rerun. Public artifact hashes and included content are verifiable.
All bundle data is prepared and scanned before creating output; an interrupted
write retains INCOMPLETE.txt and must not be published or reused.
"""
import argparse
import copy
import csv
from contextvars import ContextVar
import gzip
import hashlib
import importlib
import io
import json
from pathlib import Path
import re
import sys
import tempfile

sys.dont_write_bytecode = True

METRICS = (
    'external_wall_seconds', 'container_wall_seconds', 'analysis_seconds',
    'php_heap_peak_bytes', 'php_process_peak_rss_kib',
    'gnu_time_envelope_max_rss_kib', 'composer_max_individual_rss_kib',
    'composer_rss_complete', 'container_memory_v1_peak_bytes',
    'composer_starts', 'composer_timed_completions', 'report_json_bytes',
)
COUNTS = ('direct_scenarios', 'staged_scenarios', 'stages',
          'direct_diagnostic_calls', 'staged_diagnostic_calls', 'diagnostic_calls',
          'scenario_outcomes', 'uncertainties')
REPORT_STATES = ('resolution', 'staged_resolution', 'staged_execution_state', 'staged_stop_reason')
RESOURCE_FIELDS = ('available', 'elapsed_seconds', 'user_seconds', 'system_seconds',
                   'max_rss_kib', 'exit_code', 'termination_kind',
                   'termination_signal', 'scope')
INVOCATION_FIELDS = ('id', 'start_status', 'started_unix_seconds', 'kind', 'probe_applied')
PROVENANCE_FIELDS = ('tool_git_head', 'tool_git_dirty', 'tool_runtime_tree_sha256',
                     'tool_runtime_files', 'tool_composer_lock_sha256',
                     'harness_files_sha256', 'image_id')
REQUEST_FIELDS = ('target_major', 'from_php', 'target_php', 'memory_limit',
                  'composer_memory_limit', 'network', 'github_transport',
                  'scenario_timeout', 'diagnostic_timeout', 'overall_timeout',
                  'image', 'probe', 'calibrate')
COLLECTOR_PROVENANCE = ('runtime_php', 'collector_memory_limit', 'composer_child_memory_limit',
                        'github_transport_policy', 'github_https_git_environment_active')
CLEANUP_FIELDS = ('container_removed', 'host_scratch_removed', 'inspection_failed',
                  'scratch_before_host_cleanup', 'failure_class')
SHAPE_FIELDS = ('files', 'directories', 'symlinks', 'bytes', 'php_files', 'php_bytes')
SNAPSHOT_FIELDS = ('sha256', 'composer.json_sha256', 'composer.lock_sha256')
PROJECT_FIELDS = ('id', 'repository', 'commit', 'archive_sha256',
                  'composer_json_sha256', 'composer_lock_sha256',
                  'source_snapshot_sha256', 'source_snapshot_algorithm',
                  'file_count', 'source_bytes', 'php_files', 'skipped_archive_links',
                  'case_sensitive_directories')
PLAN_FIELDS = ('campaign_version', 'repo_commit', 'projects_sha256',
               'dependency_lock_sha256', 'tool_runtime_tree_sha256',
               'harness_files_sha256', 'image', 'github_transport',
               'budget_seconds', 'external_timeout_seconds', 'recovery_timeout_seconds',
               'overall_timeout_seconds', 'scenario_timeout_seconds',
               'diagnostic_timeout_seconds', 'composer_memory_limit')
SPEC_FIELDS = ('id', 'project', 'category', 'request', 'warm_after', 'cold_repeat',
               'memory_limit', 'network', 'probe')
ROW_FIELDS = ('run_id', 'project', 'category', 'attempt', 'status', 'validation_error',
              'harness_status', 'probe_observed', 'probe_outcome', 'immutable',
              'watchdog', 'container_exit_code', 'report_states', 'unknowns',
              'report_counts', 'composer_invocations', 'cleanup', 'metrics',
              'provenance', 'run_record_sha256', 'completion_sha256')
SUMMARY_FIELDS = ('summary_version', 'status', 'plan_sha256', 'projects_sha256',
                  'planned', 'attempt_count', 'failed_attempt_count', 'metric_methods')
ERROR_FIELDS = (
    ('run', 'failure_class'), ('completion', 'failure_class'),
    ('completion', 'validation_error'), ('collector', 'fatal_type'),
    ('collector', 'fatal_reason'), ('collector', 'fatal_file'),
    ('collector', 'fatal_line'), ('collector', 'exception_class'),
)
HOST_PATH = re.compile(
    r'(?i)(?:(?<![a-z0-9_])[a-z]:[\\/]+|'
    r'(?<![a-z0-9_\\])\\\\(?:[?.]\\)?[^\s\\/]+[\\/]|'
    r'(?<![:/])//[a-z0-9_.-]+/|'
    r'(?<![a-z0-9_./\\-])/(?:home|Users|root|tmp|var/tmp|var/folders|'
    r'private/(?:tmp|var/tmp|var/folders)|run/user|Volumes)(?:/|(?=$|[\s\x22\x27`]))|'
    r'(?<![a-z0-9_./\\-])/mnt/[a-z]/)'
)
SAFE_ID = re.compile(r'[a-zA-Z0-9][a-zA-Z0-9_.-]{0,100}\Z')
PRIVATE_ROOTS = ContextVar('publisher_private_roots', default=())
ATTESTATION = {
    'classification': 'publisher-verified portable projection/attestation',
    'public_verification': 'Published file/gzip hashes, original hashes of included reports, and included canonical content can be independently verified.',
    'private_verification': 'Original private plan, receipts, invocations, cache-lineage and host-cleanup checks cannot be independently rerun from this bundle because their metadata and private paths are excluded; these checks are publisher attestations.',
    'unavailable_artifacts': 'Hashes and sizes of omitted malformed artifacts attest to privately observed bytes; their contents are unavailable for independent recomputation.',
}
DIAGNOSTIC_ENTRIES = (
    'diagnostic_calls, direct_diagnostic_calls and staged_diagnostic_calls count canonical diagnostic entries, '
    'including reused/cached entries, not unique spawned processes. Composer started_count and invocation '
    'kinds (--version, validate, update, prohibits and others) form a separate process inventory; '
    'do not derive process starts by summing report diagnostic entries.'
)


class PublicationError(ValueError):
    pass


class ReportFormatError(PublicationError):
    """Only byte-format failures can become unavailable unsealed artifacts."""


def private_roots(values):
    roots = set(PRIVATE_ROOTS.get())
    for value in values:
        if isinstance(value, (str, Path)) and str(value):
            normalized = str(value).replace('\\', '/').rstrip('/').casefold()
            if len(normalized) > 1:
                roots.add(normalized)
    return tuple(sorted(roots))


def sha(data):
    return hashlib.sha256(data).hexdigest()


def json_bytes(value):
    return (json.dumps(value, ensure_ascii=True, sort_keys=True, indent=2,
                       allow_nan=False) + '\n').encode('utf-8')


def strict_json(data):
    def pairs(items):
        result = {}
        for key, value in items:
            if key in result:
                raise PublicationError('duplicate JSON key')
            result[key] = value
        return result
    return json.loads(data.decode('utf-8'), object_pairs_hook=pairs,
                      parse_constant=lambda _: (_ for _ in ()).throw(PublicationError('nonfinite JSON number')))


def scan(value, label):
    """Scan decoded strings as well as serialized text, including escaped JSON paths."""
    if isinstance(value, str):
        inspected = re.sub(r'(?i)file://(?:localhost)?', ' ', value)
        normalized = inspected.replace('\\', '/').casefold()
        known_private_root = any(re.search(
            r'(?<![a-z0-9_./\\-])' + re.escape(root) + r'(?=/|$|[^a-z0-9_.-])', normalized
        ) for root in PRIVATE_ROOTS.get())
        if HOST_PATH.search(inspected) or known_private_root:
            # The offending value itself must never be copied into a public error.
            raise PublicationError('host absolute path detected in ' + label)
    elif isinstance(value, dict):
        for key, item in value.items():
            scan(key, label)
            scan(item, label)
    elif isinstance(value, (list, tuple)):
        for item in value:
            scan(item, label)


def selected(value, fields):
    if value is None:
        return None
    if not isinstance(value, dict):
        raise PublicationError('expected object for allowlisted metadata')
    return {key: copy.deepcopy(value[key]) for key in fields if key in value}


def snapshot(value):
    result = selected(value, SNAPSHOT_FIELDS)
    if result is not None and 'shape' in value:
        result['shape'] = selected(value['shape'], SHAPE_FIELDS)
    return result


def cleanup(value):
    result = selected(value, CLEANUP_FIELDS)
    if result and 'scratch_before_host_cleanup' in result:
        result['scratch_before_host_cleanup'] = selected(result['scratch_before_host_cleanup'], SHAPE_FIELDS)
    return result


def invocations(value):
    if value is None:
        return None
    if not isinstance(value, list):
        raise PublicationError('invalid Composer invocation inventory')
    result = []
    for item in value:
        projected = selected(item, INVOCATION_FIELDS)
        if not isinstance(projected, dict):
            raise PublicationError('invalid Composer invocation')
        if 'resources' in item:
            projected['resources'] = selected(item['resources'], RESOURCE_FIELDS)
        result.append(projected)
    return result


def portable_row(row):
    if set(row) - set(ROW_FIELDS):
        raise PublicationError('unsupported summary row fields; review exporter allowlist')
    result = selected(row, ROW_FIELDS)
    for key, fields in (('metrics', METRICS), ('report_counts', COUNTS),
                        ('provenance', PROVENANCE_FIELDS)):
        if key in row:
            if isinstance(row[key], dict) and set(row[key]) - set(fields):
                raise PublicationError('unsupported summary ' + key + ' fields')
            result[key] = selected(row[key], fields)
    if 'cleanup' in row:
        result['cleanup'] = cleanup(row['cleanup'])
    if 'composer_invocations' in row:
        result['composer_invocations'] = invocations(row['composer_invocations'])
    scan(result, 'portable summary row')
    return result


def portable_summary(summary):
    if set(summary) - set(SUMMARY_FIELDS + ('latest', 'attempts')):
        raise PublicationError('unsupported summary fields; review exporter allowlist')
    result = selected(summary, SUMMARY_FIELDS)
    result['latest'] = [portable_row(row) for row in summary['latest']]
    result['attempts'] = [portable_row(row) for row in summary['attempts']]
    scan(result, 'portable summary')
    return result


def bounded_errors(record, receipt):
    sources = {'run': record or {}, 'completion': receipt or {},
               'collector': (record or {}).get('collector') or {}}
    result = []
    for section, field in ERROR_FIELDS:
        value = sources[section].get(field)
        if value is None:
            continue
        if not isinstance(value, (str, int)) or isinstance(value, bool):
            raise PublicationError('invalid selected error field')
        scan(value, 'selected error')
        result.append({'section': section, 'field': field,
                       'value': value[:512] if isinstance(value, str) else value,
                       'truncated': isinstance(value, str) and len(value) > 512})
    return result


def disposition(record, receipt):
    record, receipt = record or {}, receipt or {}
    collector = record.get('collector') or {}
    recovery = receipt.get('recovery') or {}
    result = {
        'harness_status': record.get('status'), 'immutable': record.get('immutable'),
        'input_before': snapshot(record.get('input_before')),
        'input_after': snapshot(record.get('input_after')),
        'source_after_sha256': receipt.get('source_after_sha256'),
        'cleanup': cleanup(record.get('cleanup')),
        'cleanup_verified': receipt.get('cleanup_verified'),
        'recovery': {'attempted': bool(recovery), 'returncode': recovery.get('returncode'),
                     'cleanup': cleanup(recovery.get('cleanup'))},
        'watchdog': record.get('watchdog'), 'external_timeout': receipt.get('external_timeout'),
        'exit_code': receipt.get('exit_code'), 'container_exit_code': record.get('container_exit_code'),
        'oom_killed': record.get('oom_killed'), 'collector_status': collector.get('status'),
        'errors': bounded_errors(record, receipt),
    }
    scan(result, 'attempt dispositions')
    return result


class Reader:
    def __init__(self):
        self.hashes = {}

    def data(self, path):
        path = Path(path)
        if path.is_symlink() or not path.is_file():
            raise PublicationError('input file missing or symlinked')
        data = path.read_bytes()
        previous = self.hashes.setdefault(path, sha(data))
        if previous != sha(data):
            raise PublicationError('input changed during publication preparation')
        return data

    def object(self, path, optional=False):
        if optional and not Path(path).exists():
            return None
        try:
            result = strict_json(self.data(path))
        except (UnicodeError, json.JSONDecodeError):
            if optional:
                return None
            raise PublicationError('invalid required JSON input') from None
        if not isinstance(result, dict):
            raise PublicationError('JSON input must be an object')
        return result

    def stable(self):
        for path, expected in self.hashes.items():
            if path.is_symlink() or sha(path.read_bytes()) != expected:
                raise PublicationError('input changed during publication preparation')


def identity(value):
    if not isinstance(value, str) or not SAFE_ID.fullmatch(value) or value in ('.', '..'):
        raise PublicationError('unsafe portable artifact identity')
    return value


def deterministic_gzip(data):
    output = io.BytesIO()
    with gzip.GzipFile(filename='', mode='wb', fileobj=output, compresslevel=9, mtime=0) as stream:
        stream.write(data)
    return output.getvalue()


def add_report(bundle, index, prefix, name, data, verification):
    label = prefix + '/' + name
    try:
        decoded = data.decode('utf-8')
    except UnicodeError:
        raise ReportFormatError('invalid_utf8') from None
    if name == 'report.json':
        try:
            document = strict_json(data)
        except (json.JSONDecodeError, UnicodeError, PublicationError):
            raise ReportFormatError('invalid_json') from None
        scan(document, label)  # Privacy failures must never become format omissions.
    else:
        scan(decoded, label)
    compressed = deterministic_gzip(data)
    if gzip.decompress(compressed) != data:
        raise PublicationError('gzip round-trip failed')
    path = 'reports/' + label + '.gz'
    if path in bundle:
        raise PublicationError('duplicate canonical artifact identity')
    bundle[path] = compressed
    index.append({'path': path, 'original_filename': name, 'verification': verification,
                  'source_artifact': label, 'content_status': 'published_unchanged',
                  'original_sha256': sha(data), 'original_bytes': len(data),
                  'gzip_sha256': sha(compressed), 'gzip_bytes': len(compressed)})


def add_raw_reports(reader, bundle, index, raw, prefix, receipt, verification):
    start = len(index)
    for name in ('report.json', 'report.md'):
        path = raw / name
        if path.exists():
            data = reader.data(path)
            if receipt is not None and receipt.get('files_sha256', {}).get('raw/' + name) != sha(data):
                raise PublicationError('canonical report differs from verified completion inventory')
            try:
                add_report(bundle, index, prefix, name, data, verification)
            except ReportFormatError as error:
                if receipt is not None:
                    raise PublicationError('invalid verified canonical artifact: ' + prefix + '/' + name) from None
                index.append({'path': None, 'source_artifact': prefix + '/' + name,
                              'original_filename': name,
                              'verification': 'unverified_noncanonical_content_unavailable',
                              'source_verification': verification,
                              'content_status': 'unavailable_noncanonical', 'reason': str(error),
                              'original_sha256': sha(data), 'original_bytes': len(data),
                              'gzip_sha256': None, 'gzip_bytes': None})
    return copy.deepcopy(index[start:])


def observed_record(reader, raw, required=False):
    record = reader.object(raw / 'run.json', optional=not required)
    collector = reader.object(raw / 'collector.json', optional=True)
    if collector is not None:
        if record is not None and record.get('collector') is not None and record['collector'] != collector:
            raise PublicationError('collector artifact disagrees with run record')
        record = copy.deepcopy(record) if record is not None else {}
        record['collector'] = collector
    return record


def composer_scopes(row):
    values = row.get('composer_invocations')
    if values is None:
        return None
    return sorted({(item.get('resources') or {}).get('scope') or 'unknown' for item in values})


def scalar(value):
    if value is None:
        return 'null'
    if isinstance(value, bool):
        return 'true' if value else 'false'
    if isinstance(value, (list, dict)):
        return json.dumps(value, sort_keys=True, ensure_ascii=True, separators=(',', ':'))
    return str(value)


def tabular_rows(summary):
    latest = {(row['run_id'], row.get('attempt')) for row in summary['latest']}
    rows = summary['attempts'] + [row for row in summary['latest'] if row['status'] == 'not_run']
    for row in rows:
        result = {key: row.get(key) for key in ('run_id', 'project', 'category', 'attempt', 'status',
                                               'harness_status', 'probe_observed', 'probe_outcome')}
        result['selected_latest'] = (row['run_id'], row.get('attempt')) in latest
        result.update({key: (row.get('report_states') or {}).get(key) for key in REPORT_STATES})
        result.update({key: (row.get('metrics') or {}).get(key) for key in METRICS})
        result.update({key: (row.get('report_counts') or {}).get(key) for key in COUNTS})
        result['composer_rss_scopes'] = composer_scopes(row)
        yield result


def csv_bytes(summary):
    rows = list(tabular_rows(summary))
    fields = ['run_id', 'project', 'category', 'attempt', 'status', 'harness_status',
              'probe_observed', 'probe_outcome', 'selected_latest', *REPORT_STATES, *METRICS, *COUNTS,
              'composer_rss_scopes']
    stream = io.StringIO(newline='')
    writer = csv.DictWriter(stream, fieldnames=fields, lineterminator='\n')
    writer.writeheader()
    for row in rows:
        writer.writerow({key: scalar(value) for key, value in row.items()})
    return stream.getvalue().encode('utf-8')


def markdown_bytes(summary):
    lines = ['# R4 published campaign evidence', '',
             'Status: ' + summary['status'] + '. Missing values are `null`; failed and unavailable probes are retained.',
             'CSV includes every attempt and not-run slot; this table shows the latest declared slots.',
             ATTESTATION['classification'] + '. ' + ATTESTATION['public_verification'],
             ATTESTATION['private_verification'],
             DIAGNOSTIC_ENTRIES,
             'Composer RSS scope is retained per invocation. `composer_php_process` means own Linux `/proc/self/status` VmHWM; absent scope is unknown, including legacy. Peaks are not concurrent sums.', '',
             '| Run | Attempt | Status | Probe | Direct state | Staged state | Direct | Staged | Direct diagnostic entries | Staged diagnostic entries | Analysis seconds | Composer RSS KiB | RSS scopes |',
             '| --- | --- | --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |']
    for row in summary['latest']:
        counts, metrics = row.get('report_counts') or {}, row.get('metrics') or {}
        values = [row['run_id'], row.get('attempt'), row['status'], row.get('probe_outcome'),
                  (row.get('report_states') or {}).get('resolution'),
                  (row.get('report_states') or {}).get('staged_resolution'),
                  counts.get('direct_scenarios'), counts.get('staged_scenarios'),
                  counts.get('direct_diagnostic_calls'), counts.get('staged_diagnostic_calls'),
                  metrics.get('analysis_seconds'), metrics.get('composer_max_individual_rss_kib'),
                  composer_scopes(row)]
        lines.append('| ' + ' | '.join(scalar(v).replace('|', '\\|').replace('\n', ' ') for v in values) + ' |')
    return ('\n'.join(lines) + '\n').encode('utf-8')


def scan_bundle(bundle):
    for name, data in bundle.items():
        if name.endswith('.gz'):
            data = gzip.decompress(data)
        try:
            text = data.decode('utf-8')
        except UnicodeError:
            raise PublicationError('non-UTF-8 publication artifact') from None
        if name.endswith(('.json', '.json.gz')):
            scan(strict_json(data), name)
        else:
            scan(text, name)


def load_tools(directory):
    directory = Path(directory).resolve()
    if any(not (directory / name).is_file() for name in ('summarize.py', 'campaign.py', 'acquire.py')):
        raise PublicationError('R4 verifier modules unavailable; pass --tools')
    sys.path.insert(0, str(directory))
    for name in ('acquire', 'campaign', 'summarize'):
        existing = sys.modules.get(name)
        if existing and Path(existing.__file__).resolve() != directory / (name + '.py'):
            raise PublicationError('conflicting R4 verifier module already imported')
    return importlib.import_module('summarize')


def prepare(campaign, tools, diagnostics=()):
    roots = private_roots((Path(campaign).resolve(), Path(tools).resolve(), Path.home(),
                           tempfile.gettempdir(), Path(tempfile.gettempdir()).resolve(),
                           *(Path(raw).resolve() for _, raw in diagnostics)))
    token = PRIVATE_ROOTS.set(roots)
    try:
        return _prepare(campaign, tools, diagnostics)
    finally:
        PRIVATE_ROOTS.reset(token)


def _prepare(campaign, tools, diagnostics):
    if Path(campaign).is_symlink():
        raise PublicationError('campaign directory must not be symlinked')
    campaign = Path(campaign).resolve()
    if not campaign.is_dir() or campaign.is_symlink():
        raise PublicationError('campaign directory unavailable')
    if (campaign / 'controller.lock').exists():
        raise PublicationError('controller lease exists; freeze and review campaign before publication')
    reader, bundle, report_index, attempts = Reader(), {}, [], []
    plan = reader.object(campaign / 'plan.json')
    PRIVATE_ROOTS.set(private_roots((plan.get('apps'), plan.get('repo'), plan.get('harness'),
                                    str(plan.get('harness', '')).replace('\\', '/').rsplit('/', 1)[0])))
    verifier_hashes = {name: sha(reader.data(Path(tools) / name))
                       for name in ('summarize.py', 'campaign.py', 'acquire.py')}
    summarizer = load_tools(tools)
    original = summarizer.summarize(campaign)  # Validation/hash errors propagate; no fallback.
    if summarizer.digest(plan) != original['plan_sha256']:
        raise PublicationError('plan differs from verified summary')
    summary = portable_summary(original)
    specs = {spec['id']: spec for spec in plan['runs']}
    for row in summary['attempts']:
        run_id, attempt_id = identity(row['run_id']), identity(row['attempt'])
        attempt = campaign / 'runs' / run_id / attempt_id
        if attempt.resolve() != attempt or attempt.is_symlink():
            raise PublicationError('unsafe attempt directory')
        record = observed_record(reader, attempt / 'raw')
        receipt = reader.object(attempt / 'completion.json', optional=True)
        # Interrupted records have no verified completion; never promote them.
        verified = row['status'] != 'interrupted' and receipt is not None
        report_receipt = receipt if verified else None
        verification = 'verified_completion_inventory' if verified else 'unsealed_attempt_not_verified'
        details = {'run_id': run_id, 'attempt': attempt_id, 'verification': verification,
                   'harness_version': (record or {}).get('harness_version'),
                   'declared_request': selected(specs[run_id], SPEC_FIELDS),
                   'observed_request': selected((record or {}).get('request'), REQUEST_FIELDS),
                   'observed_provenance': selected((record or {}).get('provenance'), PROVENANCE_FIELDS),
                   'observed_collector_provenance': selected((record or {}).get('collector'), COLLECTOR_PROVENANCE),
                   'dispositions': disposition(record, receipt)}
        details['reports'] = add_raw_reports(reader, bundle, report_index, attempt / 'raw',
                                            'campaign/' + run_id + '/' + attempt_id, report_receipt, verification)
        attempts.append(details)
    extras = []
    used_labels = set()
    for label, raw in diagnostics:
        identity(label)
        if label in used_labels:
            raise PublicationError('duplicate diagnostic label')
        used_labels.add(label)
        raw = Path(raw).resolve()
        record = observed_record(reader, raw, required=True)
        extra = {'label': label, 'classification': 'unverified_legacy_diagnostic_not_campaign_slot',
                       'run_record_sha256': reader.hashes[raw / 'run.json'],
                       'request': selected(record.get('request'), REQUEST_FIELDS),
                       'harness_version': record.get('harness_version'),
                       'provenance': selected(record.get('provenance'), PROVENANCE_FIELDS),
                       'dispositions': disposition(record, None),
                       'collector_metrics': selected(record.get('collector'), ('status', 'analysis_seconds',
                           'php_heap_peak_bytes', 'php_process_peak_rss_kib', 'report_states')),
                       'composer': {'started_count': (record.get('composer') or {}).get('started_count'),
                                    'timed_count': (record.get('composer') or {}).get('timed_count'),
                                    'invocations': invocations((record.get('composer') or {}).get('invocations'))},
                       'analyzer_resources': selected(record.get('analyzer_resources'), RESOURCE_FIELDS)}
        extra['reports'] = add_raw_reports(reader, bundle, report_index, raw, 'diagnostics/' + label,
                                           None, 'unverified_legacy_diagnostic_not_campaign_slot')
        extras.append(extra)
    manifest = plan['manifest']
    provenance = {'plan_sha256': original['plan_sha256'], 'plan_file_sha256': reader.hashes[campaign / 'plan.json'],
                  'frozen_configuration': selected(plan, PLAN_FIELDS),
                  'projects': [selected(p, PROJECT_FIELDS) for p in manifest['projects'] + manifest.get('supplemental_projects', [])],
                  'verifier_files_sha256': verifier_hashes,
                  'exporter_sha256': sha(Path(__file__).read_bytes())}
    bundle.update({'summary.json': json_bytes(summary), 'provenance.json': json_bytes(provenance),
                   'attempts.json': json_bytes(attempts), 'diagnostics.json': json_bytes(extras),
                   'reports.json': json_bytes(report_index), 'metrics.csv': csv_bytes(summary),
                   'summary.md': markdown_bytes(summary)})
    bundle['README.md'] = ('# Portable R4 evidence\n\n' + ATTESTATION['classification'] + '.\n\n' +
                           '\n\n'.join(ATTESTATION[key] for key in ('public_verification', 'private_verification', 'unavailable_artifacts')) +
                           '\n\n' + DIAGNOSTIC_ENTRIES + '\n\n' +
                           'Canonical gzip contents are unchanged. Malformed unsealed content is omitted and indexed as unavailable with original hash/size; no bytes are repaired or promoted. Missing numeric values remain null.\n\n' +
                           'Legacy diagnostics are explicit unverified extras excluded from campaign slots. The path scan covers common Unix/Windows host paths and known input/temp roots; it is not a general secrets detector.\n').encode('utf-8')
    scan_bundle(bundle)
    reader.stable()
    if (campaign / 'controller.lock').exists() or summarizer.summarize(campaign) != original:
        raise PublicationError('campaign changed during publication preparation')
    publication = {'publication_version': 1, 'campaign_status': summary['status'],
                   'plan_sha256': summary['plan_sha256'], 'report_count': len(report_index),
                   'diagnostic_count': len(extras),
                   'published_report_count': sum(item['path'] is not None for item in report_index),
                   'unavailable_report_count': sum(item['path'] is None for item in report_index),
                   'privacy': 'common Unix/Windows host paths and known private input/temp roots scanned in all published text and decompressed canonical artifacts; no report redaction; not a general secrets detector',
                   'attestation': copy.deepcopy(ATTESTATION),
                   'diagnostic_entry_semantics': DIAGNOSTIC_ENTRIES,
                   'summary_projection': 'original summary fields retained; cleanup, provenance and invocation metadata allowlisted; private state paths excluded',
                   'diagnostics_policy': 'explicit legacy extras are unverified and excluded from campaign counts',
                   'files': {name: {'sha256': sha(data), 'bytes': len(data)} for name, data in sorted(bundle.items())}}
    bundle['publication.json'] = json_bytes(publication)
    scan_bundle(bundle)
    return bundle


def write_bundle(output, bundle):
    output = Path(output)
    output.mkdir(parents=True, exist_ok=False)
    marker = output / 'INCOMPLETE.txt'
    marker.write_text('Publication incomplete. Do not publish or reuse this directory.\n', encoding='utf-8')
    for name, data in sorted(bundle.items(), key=lambda item: (item[0] == 'publication.json', item[0])):
        path = output / name
        if path.is_absolute() and output.resolve() not in path.resolve().parents:
            raise PublicationError('unsafe publication artifact path')
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open('xb') as stream:
            stream.write(data)
    marker.unlink()


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--campaign', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path, help='fresh nonexistent destination')
    parser.add_argument('--tools', type=Path, default=Path.cwd() / 'tools' / 'r4-corpus',
                        help='trusted R4 verifier directory; defaults to current checkout tools/r4-corpus')
    parser.add_argument('--diagnostics', action='append', default=[], metavar='LABEL=RAW_DIRECTORY',
                        help='explicit legacy diagnostic input; repeat for each directory')
    args = parser.parse_args(argv)
    try:
        diagnostics = []
        for value in args.diagnostics:
            label, separator, raw = value.partition('=')
            if not separator or not raw:
                raise PublicationError('diagnostics require LABEL=RAW_DIRECTORY')
            diagnostics.append((identity(label), Path(raw)))
        output = args.output.resolve()
        if args.output.exists() or args.output.is_symlink():
            raise PublicationError('output must be a fresh nonexistent directory')
        for source in (args.campaign, args.tools, *(path for _, path in diagnostics)):
            source = source.resolve()
            if output == source or source in output.parents or output in source.parents:
                raise PublicationError('output and input directories must be separate and non-nested')
        bundle = prepare(args.campaign, args.tools, diagnostics)
        write_bundle(output, bundle)
        print('Published verified portable evidence: ' + str(len(bundle)) + ' artifacts')
        return 0
    except (OSError, ValueError, TypeError, KeyError) as error:
        # Never echo arbitrary exception values containing private host paths.
        message = str(error) if isinstance(error, PublicationError) else type(error).__name__ + '; review input validation locally'
        print('PUBLICATION FAILED: ' + message, file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
