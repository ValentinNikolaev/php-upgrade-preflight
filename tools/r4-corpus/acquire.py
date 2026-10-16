#!/usr/bin/env python3
"""Acquire pinned R4 archives without executing source; refuses existing outputs."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tarfile
import tempfile
import urllib.request

SOURCE_ALGORITHM = 'r4-tree-v1'


def tree_snapshot(root):
    """Portable r4-tree-v1; byte-compatible with the standalone harness snapshot."""
    root = Path(root)
    if root.is_symlink() or not root.is_dir():
        raise ValueError('source root missing or symlinked')
    digest = hashlib.sha256()
    shape = dict.fromkeys(('files', 'directories', 'symlinks', 'bytes', 'php_files', 'php_bytes'), 0)
    for current, dirs, files in os.walk(root, followlinks=False):
        dirs.sort()
        files.sort()
        for name in sorted(dirs + files):
            path = Path(current) / name
            relative = path.relative_to(root).as_posix()
            if path.is_symlink():
                kind, content = 'link', os.readlink(path).encode('utf-8', 'surrogateescape')
                shape['symlinks'] += 1
            elif path.is_dir():
                kind, content = 'directory', b''
                shape['directories'] += 1
            elif path.is_file():
                kind = 'file'
                hasher, size = hashlib.sha256(), 0
                with path.open('rb') as stream:
                    for block in iter(lambda: stream.read(1024 * 1024), b''):
                        hasher.update(block)
                        size += len(block)
                content = hasher.digest()
                shape['files'] += 1
                shape['bytes'] += size
                if path.suffix.lower() == '.php':
                    shape['php_files'] += 1
                    shape['php_bytes'] += size
            else:
                raise ValueError('source contains an unsupported filesystem entry')
            digest.update(json.dumps([relative, kind], ensure_ascii=True).encode() + b'\0' + content + b'\0')
    result = {'sha256': digest.hexdigest(), 'shape': shape}
    for name in ('composer.json', 'composer.lock'):
        path = root / name
        result[name + '_sha256'] = sha256(path) if path.is_file() else None
    return result


def source_pin(project):
    value = project.get('source_snapshot_sha256')
    if project.get('source_snapshot_algorithm') != SOURCE_ALGORITHM or not isinstance(value, str) or not re.fullmatch(r'[0-9a-f]{64}', value):
        raise ValueError('missing or invalid verified source snapshot pin: ' + project['id'])
    return value


def sha256(path):
    digest = hashlib.sha256()
    with Path(path).open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(block)
    return digest.hexdigest()


def load_json(path):
    def pairs(items):
        result = {}
        for key, value in items:
            if key in result:
                raise ValueError('duplicate JSON key: ' + key)
            result[key] = value
        return result
    return json.loads(Path(path).read_text(encoding='utf-8'), object_pairs_hook=pairs,
                      parse_constant=lambda value: (_ for _ in ()).throw(ValueError(value)))


def write_new(path, data):
    with Path(path).open('x', encoding='utf-8', newline='\n') as stream:
        json.dump(data, stream, indent=2, sort_keys=True, allow_nan=False)
        stream.write('\n')


def projects(manifest):
    items = manifest['projects'] + manifest.get('supplemental_projects', [])
    identities = set()
    for project in items:
        identity = project['id']
        if not re.fullmatch(r'[a-z0-9][a-z0-9-]*', identity) or identity in identities:
            raise ValueError('unsafe or duplicate project identity')
        identities.add(identity)
        if not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', project['repository']):
            raise ValueError('invalid repository')
        if not re.fullmatch(r'[0-9a-f]{40}', project['commit']):
            raise ValueError('commit must be pinned')
        for name in ('archive_sha256', 'composer_json_sha256', 'composer_lock_sha256'):
            if not re.fullmatch(r'[0-9a-f]{64}', project[name]):
                raise ValueError('invalid pinned hash')
        if 'source_snapshot_sha256' in project or 'source_snapshot_algorithm' in project:
            source_pin(project)
    return items


def safe_parts(name):
    # Apply Windows restrictions on every host so the same archive is portable.
    if '\\' in name or '\x00' in name or name.startswith('/'):
        raise ValueError('unsafe archive path')
    parts = name.rstrip('/').split('/')
    reserved = {'CON', 'PRN', 'AUX', 'NUL'} | {f'{p}{n}' for p in ('COM', 'LPT') for n in range(1, 10)}
    if not parts or any(p in ('', '.', '..') or p.endswith((' ', '.')) or
                        any(c in p for c in ':<>"|?*') or any(ord(c) < 32 for c in p) or
                        p.split('.')[0].upper() in reserved for p in parts):
        raise ValueError('unsafe archive path')
    return parts


def case_directories(project):
    result = project.get('case_sensitive_directories', [])
    if not isinstance(result, list) or any(not isinstance(p, str) or '/'.join(safe_parts(p)) != p for p in result) or len(set(result)) != len(result):
        raise ValueError('invalid case-sensitive directory declarations')
    return result


def prepare_case_directories(destination, directories):
    for relative in sorted(directories, key=lambda value: (value.count('/'), value)):
        path = destination.joinpath(*safe_parts(relative))
        path.mkdir(parents=True, exist_ok=True)
        if os.name == 'nt':
            try:
                result = subprocess.run(['fsutil', 'file', 'setCaseSensitiveInfo', str(path), 'enable'],
                                        capture_output=True, timeout=30)
                if result.returncode != 0:
                    raise ValueError('fsutil rejected case-sensitive directory: ' + relative)
            except (OSError, subprocess.SubprocessError, ValueError) as error:
                raise ValueError('cannot preserve declared archive case variants at ' + str(path) +
                                 '; enable NTFS case sensitivity or acquire on a case-sensitive filesystem') from error


def verify_app(root, project, require_source=False):
    root = Path(root)
    for filename, field in (('composer.json', 'composer_json_sha256'), ('composer.lock', 'composer_lock_sha256')):
        path = root / filename
        if path.is_symlink() or sha256(path) != project[field]:
            raise ValueError('pinned input mismatch: ' + project['id'] + '/' + filename)
    if require_source or 'source_snapshot_sha256' in project:
        if tree_snapshot(root)['sha256'] != source_pin(project):
            raise ValueError('source snapshot mismatch: ' + project['id'])


def extract(archive, destination, project, max_total=1024 * 1024 * 1024, max_members=100000):
    if sha256(archive) != project['archive_sha256']:
        raise ValueError('archive hash mismatch')
    destination = Path(destination)
    destination.mkdir(parents=True, exist_ok=False)
    sensitive = case_directories(project)
    prepare_case_directories(destination, sensitive)
    seen, aliases, links, total, files, php_files, prefix = set(), {}, [], 0, 0, 0, None
    try:
        with tarfile.open(archive, mode='r|gz') as source:
            for count, member in enumerate(source, 1):
                if count > max_members:
                    raise ValueError('archive member budget exceeded')
                parts = safe_parts(member.name)
                prefix = prefix or parts[0]
                if parts[0] != prefix:
                    raise ValueError('multiple archive roots')
                if len(parts) == 1:
                    if not member.isdir():
                        raise ValueError('archive root is not a directory')
                    continue
                relative = '/'.join(parts[1:])
                for depth in range(2, len(parts) + 1):
                    component = '/'.join(parts[1:depth])
                    prior = aliases.setdefault(component.casefold(), component)
                    if prior != component and not any(component.startswith(directory + '/') for directory in sensitive):
                        raise ValueError('case-collision archive path: ' + component)
                key = relative
                if key in seen:
                    raise ValueError('duplicate archive path')
                seen.add(key)
                if member.issym():
                    links.append(relative)
                    if relative not in project.get('skipped_archive_links', []):
                        raise ValueError('unrecorded omitted symlink')
                    continue
                if not (member.isfile() or member.isdir()):
                    raise ValueError('unsupported archive entry')
                target = destination.joinpath(*parts[1:])
                if member.isdir():
                    target.mkdir(parents=True, exist_ok=True)
                    continue
                total += member.size
                if member.size < 0 or total > max_total:
                    raise ValueError('uncompressed archive budget exceeded')
                target.parent.mkdir(parents=True, exist_ok=True)
                remaining = member.size
                with source.extractfile(member) as incoming, target.open('xb') as outgoing:
                    while remaining:
                        block = incoming.read(min(1024 * 1024, remaining))
                        if not block:
                            raise ValueError('truncated archive member')
                        outgoing.write(block)
                        remaining -= len(block)
                files += 1
                php_files += target.suffix.lower() == '.php'
        if sorted(links) != sorted(project.get('skipped_archive_links', [])):
            raise ValueError('omitted symlink inventory mismatch')
        # Declared directories must be archive structure, never synthetic additions.
        if any(directory not in aliases.values() for directory in sensitive):
            raise ValueError('declared case-sensitive directory absent from archive')
        verify_app(destination, project)
        for key, actual in (('file_count', files), ('source_bytes', total), ('php_files', php_files)):
            if key in project and actual != project[key]:
                raise ValueError('source inventory mismatch: ' + key)
    except Exception:
        # Keep failed extraction for diagnosis; never remove an existing source tree.
        raise
    return {'files': files, 'bytes': total, 'php_files': php_files, 'omitted_symlinks': links,
            'source_snapshot_algorithm': SOURCE_ALGORITHM,
            'source_snapshot_sha256': tree_snapshot(destination)['sha256']}


def acquire(project, root, max_archive=512 * 1024 * 1024, max_total=1024 * 1024 * 1024):
    root = Path(root)
    target = root / project['id']
    if target.exists():
        raise ValueError('refusing existing source directory: ' + str(target))
    root.mkdir(parents=True, exist_ok=True)
    url = f"https://codeload.github.com/{project['repository']}/tar.gz/{project['commit']}"
    descriptor, archive_name = tempfile.mkstemp(prefix='preflight-r4-archive-', suffix='.tar.gz')
    archive = Path(archive_name)
    try:
        with os.fdopen(descriptor, 'wb') as output, urllib.request.urlopen(url, timeout=60) as response:
            if response.url != url:
                raise ValueError('unexpected archive redirect')
            total = 0
            while True:
                block = response.read(1024 * 1024)
                if not block:
                    break
                total += len(block)
                if total > max_archive:
                    raise ValueError('compressed archive budget exceeded')
                output.write(block)
        result = extract(archive, target, project, max_total)
        return {'project': project['id'], 'url': url, 'archive_sha256': project['archive_sha256'], **result}
    finally:
        archive.unlink(missing_ok=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--projects', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True, help='fresh nonexistent acquisition root')
    args = parser.parse_args()
    items = projects(load_json(args.projects))
    args.output.mkdir(parents=True, exist_ok=False)
    write_new(args.output / 'intake.json', {'projects_sha256': sha256(args.projects), 'planned': [p['id'] for p in items]})
    for project in items:
        write_new(args.output / (project['id'] + '.intake.json'), acquire(project, args.output))


if __name__ == '__main__':
    main()
