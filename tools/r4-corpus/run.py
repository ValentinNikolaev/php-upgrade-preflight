#!/usr/bin/env python3
"""Explicitly invoked R4 Docker experiment runner; Python standard library only."""
import argparse
import hashlib
import json
import math
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import time
import uuid


def exact_php(value):
    if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+", value):
        raise argparse.ArgumentTypeError("PHP version must be exact X.Y.Z")
    return value


def bounded(maximum):
    def parse(value):
        number = int(value)
        if not 1 <= number <= maximum:
            raise argparse.ArgumentTypeError(f"timeout must be 1..{maximum}")
        return number
    return parse


def arguments(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ("app", "output", "cache", "repo"):
        parser.add_argument("--" + name, type=Path, required=True)
    parser.add_argument('--runtime-vendor', type=Path, help='read-only dependency overlay for a frozen checkout; never installed or modified')
    parser.add_argument('--cache-seed', type=Path, help='read-only cold cache; v2 warm runs only')
    parser.add_argument('--cache-seed-proof', type=Path, help='frozen cold receipt hash and expected inventory JSON')
    parser.add_argument('--analysis-mode', choices=('standard', 'full'), help='optimization comparison; omitted uses the selected tool default')
    parser.add_argument('--source-profile', action='store_true', help='opt-in instrumented source profile; unsupported APIs fail before analysis')
    source_cache = parser.add_mutually_exclusive_group()
    source_cache.add_argument('--source-cache-dir', type=Path, help='dedicated analyzer-owned source-fact cache outside all input/output mounts')
    source_cache.add_argument('--disable-source-cache', action='store_true', help='disable source-fact reuse in tools supporting it')
    parser.add_argument("--target-major", type=int, choices=range(8, 14), required=True)
    parser.add_argument("--from-php", type=exact_php, required=True)
    parser.add_argument("--target-php", type=exact_php, required=True)
    parser.add_argument("--memory-limit", choices=("128M", "1024M"), required=True)
    parser.add_argument("--composer-memory-limit", choices=("128M", "1024M"), default="1024M")
    parser.add_argument("--network", choices=("bridge", "none"), required=True)
    parser.add_argument("--github-transport", choices=("default", "public-https"), default="default")
    parser.add_argument("--scenario-timeout", type=bounded(3600), required=True)
    parser.add_argument("--diagnostic-timeout", type=bounded(900), required=True)
    parser.add_argument("--overall-timeout", type=bounded(86400), required=True)
    parser.add_argument("--image", required=True)
    parser.add_argument("--probe", choices=("normal", "timeout", "child-failure", "cache-failure"), default="normal")
    parser.add_argument("--calibrate", action="store_true", help="run offline measurement probes in a separate container first")
    return parser.parse_args(argv)


def inside(child, parent):
    return child == parent or parent in child.parents


def validate(args):
    for name in ("app", "output", "cache", "repo"):
        setattr(args, name, getattr(args, name).resolve())
    vendor = getattr(args, 'runtime_vendor', None) or args.repo / 'vendor'
    if not args.app.is_dir() or not (vendor / 'autoload.php').is_file():
        raise ValueError("app directory and repo vendor/autoload.php must exist")
    if args.output.exists():
        raise ValueError("output must be a fresh nonexistent directory")
    paths = [args.app, args.output, args.cache, args.repo]
    if getattr(args, 'runtime_vendor', None) is not None:
        args.runtime_vendor = args.runtime_vendor.resolve()
        if not (args.runtime_vendor / 'autoload.php').is_file():
            raise ValueError('external runtime vendor autoload unavailable')
        paths.append(args.runtime_vendor)
    source_cache = getattr(args, 'source_cache_dir', None)
    if source_cache is not None:
        if source_cache.is_symlink() or source_cache.exists() and not source_cache.is_dir():
            raise ValueError('source cache must be a real dedicated directory')
        args.source_cache_dir = source_cache.resolve()
        paths.append(args.source_cache_dir)
    seed, proof = getattr(args, 'cache_seed', None), getattr(args, 'cache_seed_proof', None)
    if (seed is None) != (proof is None):
        raise ValueError('cache seed and proof must be supplied together')
    if seed is not None:
        if seed.is_symlink() or not seed.is_dir() or proof.is_symlink() or not proof.is_file():
            raise ValueError('cache seed directory and proof file must be real inputs')
        args.cache_seed, args.cache_seed_proof = seed.resolve(), proof.resolve()
        paths += [args.cache_seed, args.cache_seed_proof]
    for index, left in enumerate(paths):
        for right in paths[index + 1:]:
            if inside(left, right) or inside(right, left):
                raise ValueError("app, output, cache and repo must be separate non-nested paths")
    if args.cache.exists() and (not args.cache.is_dir() or args.cache.is_symlink()):
        raise ValueError("cache must be a dedicated directory")
    # Docker --mount uses comma delimiters; never interpret caller paths as options.
    if any("," in str(path) or "\n" in str(path) for path in paths):
        raise ValueError("mount paths cannot contain commas or newlines")


def snapshot(root):
    """Hash full tree including .git/vendor, directory shape and symlink text, without following links."""
    digest = hashlib.sha256()
    shape = {"files": 0, "directories": 0, "symlinks": 0, "bytes": 0, "php_files": 0, "php_bytes": 0}
    for current, dirs, files in os.walk(root, followlinks=False):
        dirs.sort()
        files.sort()
        for name in sorted(dirs + files):
            path = Path(current) / name
            relative = path.relative_to(root).as_posix()
            if path.is_symlink():
                kind, content = "link", os.readlink(path).encode("utf-8", "surrogateescape")
                shape["symlinks"] += 1
            elif path.is_dir():
                kind, content = "directory", b""
                shape["directories"] += 1
            elif path.is_file():
                kind = "file"
                hasher = hashlib.sha256()
                size = 0
                with path.open("rb") as stream:
                    for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                        hasher.update(chunk)
                        size += len(chunk)
                content = hasher.digest()
                shape["files"] += 1
                shape["bytes"] += size
                if path.suffix.lower() == ".php":
                    shape["php_files"] += 1
                    shape["php_bytes"] += size
            else:
                raise ValueError("source contains an unsupported filesystem entry")
            digest.update(json.dumps([relative, kind], ensure_ascii=True).encode() + b"\0" + content + b"\0")
    result = {"sha256": digest.hexdigest(), "shape": shape}
    for name in ("composer.json", "composer.lock"):
        path = root / name
        result[name + "_sha256"] = hashlib.sha256(path.read_bytes()).hexdigest() if path.is_file() else None
    return result


def read_json(path):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return None


def unavailable_resources():
    return {"available": False, "elapsed_seconds": None, "max_rss_kib": None, "exit_code": None, "termination_kind": "unknown", "termination_signal": None}


def resource_stats(path):
    result = unavailable_resources()
    signal_seen = False
    try:
        for line in path.read_text().splitlines(keepends=True):
            if line.startswith("Command terminated"):
                signal_seen = True
                match = re.fullmatch(r"Command terminated by signal ([1-9][0-9]*)\r?\n", line)
                result["termination_signal"] = int(match[1]) if match else None
            # A final unterminated field may be a partial write, not a measurement.
            if not line.endswith("\n"):
                continue
            line = line.rstrip("\r\n")
            key, separator, value = line.partition("=")
            if separator and key in ("elapsed_seconds", "user_seconds", "system_seconds", "max_rss_kib", "exit_code"):
                if re.fullmatch(r"[0-9]+(?:\.[0-9]+)?" if key.endswith("seconds") else r"[0-9]+", value):
                    result[key] = float(value) if key.endswith("seconds") else int(value)
        if signal_seen:
            result["exit_code"] = None
            result["termination_kind"] = "signalled" if result["termination_signal"] is not None else "unknown"
        elif result["exit_code"] is not None:
            result["termination_kind"] = "exited"
        result["available"] = result["elapsed_seconds"] is not None
    except (OSError, UnicodeError):
        pass
    return result


def composer_records(output):
    records = []
    for path in sorted((output / "composer").glob("*.start")):
        try:
            parts = [line.rstrip('\r\n') if line.endswith('\n') else '' for line in path.read_text().splitlines(keepends=True)]
        except (OSError, UnicodeError):
            parts = []
        started = None
        try:
            started = float(parts[0])
            if not math.isfinite(started) or started < 0:
                started = None
        except (IndexError, ValueError):
            pass
        kind = parts[1] if len(parts) > 1 and parts[1] in ('--version', 'show', 'update', 'validate', 'prohibits', 'why-not', 'other') else None
        probe = parts[2] if len(parts) > 2 and parts[2] in ('normal', 'timeout', 'child-failure', 'cache-failure') else None
        stats = resource_stats(path.with_suffix('.time'))
        metrics_path = path.with_suffix('.metrics.json')
        if metrics_path.exists():
            stats = unavailable_resources()
            metrics = read_json(metrics_path)
            if isinstance(metrics, dict) and metrics.get('scope') == 'composer_php_process':
                elapsed, rss = metrics.get('elapsed_seconds'), metrics.get('max_rss_kib')
                if type(elapsed) in (int, float) and math.isfinite(elapsed) and elapsed >= 0 and (rss is None or type(rss) is int and rss >= 0):
                    stats.update(available=True, elapsed_seconds=elapsed, max_rss_kib=rss, scope='composer_php_process')
        records.append({'id': path.stem, 'start_status': 'complete' if len(parts) == 3 and started is not None and kind is not None and probe is not None else 'malformed', 'started_unix_seconds': started, 'kind': kind, 'probe_applied': probe, 'resources': stats})
    return {"started_count": len(records), "timed_count": sum(r["resources"]["available"] for r in records), "invocations": records}


def leftovers(root):
    return snapshot(root)["shape"] if root.exists() else None


def write_record(path, data):
    path.write_text(json.dumps(data, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")


def atomic_record(path, data):
    pending = path.with_name(path.name + '.next')
    try:
        with pending.open('w', encoding='utf-8') as stream:
            stream.write(json.dumps(data, indent=2, ensure_ascii=True) + '\n')
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(pending, path)
    finally:
        pending.unlink(missing_ok=True)


def ownership_update(path, owner, lifecycle):
    owner['lifecycle'] = lifecycle
    atomic_record(path, owner)


def validate_owner(owner):
    if not isinstance(owner, dict) or owner.get('version') != 1:
        raise ValueError('invalid ownership record')
    token = owner.get('task_token')
    if not isinstance(token, str) or not re.fullmatch('[0-9a-f]{32}', token):
        raise ValueError('invalid ownership token')
    name = 'preflight-r4-' + token
    if not isinstance(owner.get('state_path'), str) or owner.get('lifecycle') not in ('prepared', 'creating', 'created', 'create_failed', 'exited', 'cleaning', 'cleaned', 'unknown') or any(type(owner.get(key)) is not bool for key in ('creation_started', 'create_settled')) or not isinstance(owner.get('cleanup'), dict):
        raise ValueError('invalid ownership lifecycle')
    state = Path(owner['state_path'])
    # Resolve the parent too: symlink/junction substitution must never broaden deletion.
    if owner.get('container_name') != name or not state.is_absolute() or state.name != name or state.is_symlink() or state.resolve() != Path(tempfile.gettempdir()).resolve() / name:
        raise ValueError('unsafe owned state path')
    if type(owner.get('host_pid')) is not int or owner['host_pid'] <= 0:
        raise ValueError('invalid owner PID')
    return state


def host_exited(pid):
    if os.name == 'nt':
        import ctypes
        from ctypes import wintypes
        kernel = ctypes.WinDLL('kernel32', use_last_error=True)
        kernel.OpenProcess.restype = wintypes.HANDLE
        kernel.OpenProcess.argtypes = [wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
        kernel.GetExitCodeProcess.argtypes = [wintypes.HANDLE, ctypes.POINTER(wintypes.DWORD)]
        kernel.CloseHandle.argtypes = [wintypes.HANDLE]
        handle = kernel.OpenProcess(0x1000, False, pid)
        if not handle:
            if ctypes.get_last_error() == 87:
                return True
            raise RuntimeError('host process state unknown')
        try:
            code = wintypes.DWORD()
            if not kernel.GetExitCodeProcess(handle, ctypes.byref(code)):
                raise RuntimeError('host process state unknown')
            return code.value != 259
        finally:
            kernel.CloseHandle(handle)
    try:
        os.kill(pid, 0)
        return False
    except ProcessLookupError:
        return True
    except PermissionError:
        raise RuntimeError('host process state unknown')


def owned_container(owner):
    name = owner['container_name']
    result = subprocess.run(['docker', 'inspect', '--type', 'container', name], capture_output=True, timeout=30)
    if result.returncode:
        # Distinguish positive absence from an unreachable daemon or inspect failure.
        listed = subprocess.run(['docker', 'container', 'ls', '--all', '--format', '{{.Names}}'], capture_output=True, timeout=30)
        if listed.returncode or name in listed.stdout.decode().splitlines():
            raise RuntimeError('container inspection unknown')
        return None
    data = json.loads(result.stdout)
    if not isinstance(data, list) or len(data) != 1:
        raise ValueError('invalid container inspection')
    container = data[0]
    if container.get('Name') != '/' + name or (container.get('Config', {}).get('Labels') or {}).get('preflight.r4.task') != owner['task_token'] or not re.fullmatch('[0-9a-f]{64}', container.get('Id', '')):
        raise ValueError('container ownership mismatch')
    return container


def remove_owned_container(path, owner):
    validate_owner(owner)
    container = owned_container(owner)
    if container is None and owner.get('creation_started') and not owner.get('create_settled'):
        raise RuntimeError('in-flight container create cannot be ruled out')
    if container is not None:
        owner['create_settled'] = True
        owner['container_id'] = container['Id']
        ownership_update(path, owner, 'cleaning')
        if docker_call(['rm', '--force', container['Id']]) != 0:
            raise RuntimeError('owned container removal failed')
        if owned_container(owner) is not None:
            raise RuntimeError('owned container still present')
    owner['cleanup']['container_removed'] = True
    ownership_update(path, owner, 'cleaning')


def remove_owned_state(path, owner):
    state = validate_owner(owner)
    if owner['cleanup'].get('container_removed') is not True:
        raise RuntimeError('container exit not proven')
    if state.exists():
        shutil.rmtree(state)
    owner['cleanup']['host_scratch_removed'] = not state.exists()
    owner['cleanup'].pop('retained_state_path', None)
    ownership_update(path, owner, 'cleaned')


def cleanup_unknown(path, owner, failure):
    owner['cleanup']['failure_class'] = type(failure).__name__
    owner['cleanup']['retained_state_path'] = owner.get('state_path')
    ownership_update(path, owner, 'unknown')


def recover(path):
    owner = read_json(path)
    try:
        validate_owner(owner)
        if not host_exited(owner['host_pid']):
            raise RuntimeError('owner host process has not exited')
        remove_owned_container(path, owner)
        remove_owned_state(path, owner)
        return 0
    except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as failure:
        if isinstance(owner, dict):
            if not isinstance(owner.get('cleanup'), dict):
                owner['cleanup'] = {}
            cleanup_unknown(path, owner, failure)
        return 1


def report_evidence(output, collector, container):
    """Require both same-object renderings and finalization, independently of exit 0."""
    if not isinstance(collector, dict) or collector.get('status') != 'completed' or not isinstance(container, dict) or container.get('status') != 'completed' or container.get('collector_exit_code') != 0:
        return False
    artifacts = collector.get('report_artifacts')
    if not isinstance(artifacts, dict):
        return False
    for name in ('report.json', 'report.md'):
        try:
            content = (output / name).read_bytes()
        except OSError:
            return False
        evidence = artifacts.get(name)
        if not content.strip() or not isinstance(evidence, dict) or evidence.get('bytes') != len(content) or evidence.get('sha256') != hashlib.sha256(content).hexdigest():
            return False
    canonical = read_json(output / 'report.json')
    if not isinstance(canonical, dict) or not isinstance(canonical.get('resolution'), dict) or not isinstance(canonical.get('staged_resolution'), dict) or not isinstance(canonical.get('transition'), dict):
        return False
    resolution, staged, transition = canonical['resolution'], canonical['staged_resolution'], canonical['transition']
    statuses = ('feasible', 'feasible_with_changes', 'blocked', 'unknown')
    if resolution.get('status') not in statuses or staged.get('status') not in statuses or staged.get('execution_state') not in ('evaluated', 'skipped') or 'stop_reason' not in staged or staged['stop_reason'] is not None and not isinstance(staged['stop_reason'], str) or not isinstance(transition.get('framework_guidance'), list):
        return False
    expected = {'resolution': resolution['status'], 'staged_resolution': staged['status'], 'staged_execution_state': staged['execution_state'], 'staged_stop_reason': staged['stop_reason'], 'framework_guidance': transition['framework_guidance']}
    return collector.get('report_states') == expected and isinstance(container.get('scratch_leftovers_before_container_exit'), dict) and type(container.get('sanitized_stderr_bytes')) is int


def docker_call(command, timeout=30):
    return subprocess.run(["docker", *command], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=timeout).returncode


def prepare_composer_home(home, policy):
    before = snapshot(home)
    config = None
    git_config = None
    if policy == "public-https":
        config = {"config": {"github-protocols": ["https"], "use-github-api": False}}
        write_record(home / "config.json", config)
        git_config = {"GIT_CONFIG_COUNT": "1", "GIT_CONFIG_KEY_0": "url.https://github.com/.insteadOf", "GIT_CONFIG_VALUE_0": "git@github.com:"}
    return {"policy": policy, "composer_config": config, "git_environment": git_config, "home_before_policy": before, "home_after_policy": snapshot(home)}


def provenance(repo, harness, image, runtime_vendor=None):
    def git(*command):
        completed = subprocess.run(["git", "-C", str(repo), *command], capture_output=True, timeout=30)
        return completed.stdout.decode("utf-8", "replace").strip() if completed.returncode == 0 else None
    hashes = {}
    for prefix, directory in (('packages', repo / 'packages'), ('vendor', runtime_vendor or repo / 'vendor')):
        for path in sorted(directory.rglob("*")):
            if path.is_file():
                hashes[prefix + '/' + path.relative_to(directory).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    tool_digest = hashlib.sha256(json.dumps(hashes, sort_keys=True).encode()).hexdigest()
    image_result = subprocess.run(["docker", "image", "inspect", "--format", "{{.Id}}", image], capture_output=True, timeout=30)
    if image_result.returncode != 0:
        raise ValueError("selected image unavailable")
    lock = repo / "composer.lock"
    return {"tool_git_head": git("rev-parse", "HEAD"), "tool_git_dirty": bool(git("status", "--porcelain", "--untracked-files=no")), "tool_runtime_tree_sha256": tool_digest, "tool_runtime_files": len(hashes), "tool_composer_lock_sha256": hashlib.sha256(lock.read_bytes()).hexdigest() if lock.is_file() else None, "harness_files_sha256": {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(harness.iterdir()) if p.is_file() and p.suffix in (".py", ".php", ".sh") or p.name == "Dockerfile"}, "image_id": image_result.stdout.decode().strip()}


def run(args):
    validate(args)
    args.output.mkdir(parents=True)
    token = uuid.uuid4().hex
    name = 'preflight-r4-' + token
    state = Path(tempfile.gettempdir()).resolve() / name
    ownership_path = args.output / 'ownership.json'
    owner = {'version': 1, 'task_token': token, 'container_name': name, 'state_path': str(state), 'host_pid': os.getpid(), 'creation_started': False, 'create_settled': False, 'cleanup': {'container_removed': False, 'host_scratch_removed': False}}
    ownership_update(ownership_path, owner, 'prepared')
    scratch, home = state / "scratch", state / "composer-home"
    record = {"harness_version": 1, "status": "incomplete", "source_digest_scope": "full host tree before/after; outside analysis and container wall timers", "input_before": None, "input_after": None, "immutable": None, "container_exit_code": None, "container_wall_seconds": None, "watchdog": False, "request": {key: value for key, value in vars(args).items() if key not in ("app", "repo", "output", "cache", "cache_seed", "cache_seed_proof", "source_cache_dir", "runtime_vendor")}, "container_memory_limit_bytes": 2147483648, "composer_home": "isolated empty" if args.github_transport == "default" else "isolated public HTTPS configuration", "cleanup": {}}
    try:
        state.mkdir()
        scratch.mkdir()
        home.mkdir()
        args.cache.mkdir(parents=True, exist_ok=True)
        if args.calibrate:
            from calibration import calibrate
            record['calibration'] = calibrate(args.image, ownership_path, owner)
        record["input_before"] = snapshot(args.app)
        record["github_transport"] = prepare_composer_home(home, args.github_transport)
        record["provenance"] = provenance(args.repo, Path(__file__).resolve().parent, args.image, args.runtime_vendor) if args.runtime_vendor is not None else provenance(args.repo, Path(__file__).resolve().parent, args.image)
        seed = getattr(args, 'cache_seed', None)
        if seed is not None:
            record['cache_seed_expected_sha256'] = hashlib.sha256(args.cache_seed_proof.read_bytes()).hexdigest()
        command = ["create", "--name", name, "--label", "preflight.r4.task=" + token, "--init", "--memory", "2g", "--network", args.network, "--cap-drop", "ALL", "--security-opt", "no-new-privileges"]
        harness = Path(__file__).resolve().parent
        for source, destination, readonly in ((args.app, "/target", True), (args.repo, "/app", True), (harness, "/harness", True), (args.output, "/output", False), (args.cache, "/composer-cache", False), (home, "/composer-home", False), (scratch, "/scratch", False)):
            command += ["--mount", f"type=bind,source={source},target={destination}" + (",readonly" if readonly else "")]
        if args.runtime_vendor is not None:
            command += ['--mount', f'type=bind,source={args.runtime_vendor},target=/app/vendor,readonly']
        if seed is not None:
            for source, destination in ((seed, '/cache-seed'), (args.cache_seed_proof, '/cache-seed-proof.json')):
                command += ['--mount', f'type=bind,source={source},target={destination},readonly']
            command += ['--env', 'R4_CACHE_SEED_POLICY=runtime-user-copy-v1']
        source_cache = getattr(args, 'source_cache_dir', None)
        if source_cache is not None:
            source_cache.mkdir(parents=True, exist_ok=True)
            record['source_cache_before'] = snapshot(source_cache)
            command += ['--mount', f'type=bind,source={source_cache},target=/source-cache', '--env', 'R4_SOURCE_CACHE_DIR=/source-cache']
        if getattr(args, 'disable_source_cache', False):
            command += ['--env', 'R4_DISABLE_SOURCE_CACHE=1']
        if getattr(args, 'source_profile', False):
            command += ['--env', 'R4_SOURCE_PROFILE=1']
        analysis_mode = getattr(args, 'analysis_mode', None)
        if analysis_mode is not None:
            command += ['--env', 'R4_ANALYSIS_MODE=' + analysis_mode]
        for key in ("target_major", "from_php", "target_php", "memory_limit", "composer_memory_limit", "scenario_timeout", "diagnostic_timeout", "probe", "github_transport"):
            command += ["--env", "R4_" + key.upper() + "=" + str(getattr(args, key))]
        if args.network == "none":
            command += ["--env", "COMPOSER_DISABLE_NETWORK=1"]
        command += [record["provenance"]["image_id"]]
        owner['creation_started'] = True
        ownership_update(ownership_path, owner, 'creating')
        create_result = docker_call(command)
        owner['create_settled'] = True
        ownership_update(ownership_path, owner, 'created' if create_result == 0 else 'create_failed')
        if create_result:
            raise RuntimeError("container_create_failed")
        created_container = owned_container(owner)
        if created_container is None:
            raise RuntimeError('created container not found')
        container_id = created_container['Id']
        owner['container_id'] = container_id
        ownership_update(ownership_path, owner, 'created')
        started = time.monotonic()
        try:
            record["container_exit_code"] = subprocess.run(["docker", "start", "--attach", container_id], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=args.overall_timeout).returncode
        except subprocess.TimeoutExpired:
            record["watchdog"] = True
            record["status"] = "watchdog_timeout"
            docker_call(["kill", "--signal", "TERM", container_id])
            try:
                subprocess.run(["docker", "wait", container_id], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=3)
            except subprocess.TimeoutExpired:
                docker_call(["kill", "--signal", "KILL", container_id])
        record["container_wall_seconds"] = time.monotonic() - started
        inspection = owned_container(owner)
        if inspection is not None:
            container_state = inspection['State']
            record["container_exit_code"] = container_state.get("ExitCode") if not container_state.get("Running") else None
            record["oom_killed"] = container_state.get("OOMKilled")
            if container_state.get('Running') is not False:
                raise RuntimeError('container exit not proven')
            ownership_update(ownership_path, owner, 'exited')
        collector = read_json(args.output / "collector.json")
        record["collector"] = collector
        record["container"] = read_json(args.output / "container.json")
        record["analyzer_resources"] = resource_stats(args.output / "analyzer.time")
        record["composer"] = composer_records(args.output)
        if seed is not None:
            record['cache_seed'] = read_json(args.output / 'cache-seed.json')
        record["report_bytes"] = {name: (args.output / name).stat().st_size if (args.output / name).is_file() else None for name in ("report.json", "report.md")}
        if not record["watchdog"]:
            seeded = seed is None or isinstance(record.get('cache_seed'), dict) and record['cache_seed'].get('status') == 'completed' and isinstance(collector, dict) and collector.get('cache_seed_verified') is True
            record["status"] = "completed" if seeded and report_evidence(args.output, collector, record['container']) and record["container_exit_code"] == 0 else "failed"
    except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as failure:
        record["failure_class"] = type(failure).__name__
        record["status"] = "failed"
    finally:
        try:
            remove_owned_container(ownership_path, owner)
            record["input_after"] = snapshot(args.app)
            record["immutable"] = record["input_before"] == record["input_after"] if record["input_before"] is not None else None
            record["cleanup"]["scratch_before_host_cleanup"] = leftovers(scratch)
            record["composer_home_final"] = snapshot(home)
            remove_owned_state(ownership_path, owner)
        except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as failure:
            record['cleanup']['inspection_failed'] = True
            cleanup_unknown(ownership_path, owner, failure)
        record['cleanup'].update(owner['cleanup'])
        source_cache = getattr(args, 'source_cache_dir', None)
        if source_cache is not None and source_cache.is_dir():
            try:
                record['source_cache_after'] = snapshot(source_cache)
            except (OSError, ValueError):
                record['source_cache_after'] = None
                record['source_cache_inspection_failed'] = True
                record['status'] = 'failed'
        # Preserve every observed start even if Docker inspection or setup failed.
        record['composer'] = composer_records(args.output)
        if getattr(args, 'cache_seed', None) is not None:
            record['cache_seed'] = read_json(args.output / 'cache-seed.json')
        record.setdefault('analyzer_resources', resource_stats(args.output / 'analyzer.time'))
        if record["immutable"] is not True or owner['cleanup'].get('container_removed') is not True or owner['cleanup'].get('host_scratch_removed') is not True:
            record["status"] = "failed"
        write_record(args.output / "run.json", record)
    return 0 if record["status"] == "completed" else 1


def main(argv=None):
    argv = sys.argv[1:] if argv is None else argv
    if argv and argv[0] == 'recover':
        parser = argparse.ArgumentParser(description='Recover only a stopped runner\'s durably owned resources')
        parser.add_argument('--ownership', type=Path, required=True)
        recovery = parser.parse_args(argv[1:])
        if not recovery.ownership.is_absolute():
            return 2
        return recover(recovery.ownership)
    args = arguments(argv)
    try:
        validate(args)
        return run(args)
    except (OSError, ValueError, subprocess.SubprocessError) as failure:
        print("R4 invocation failed (" + type(failure).__name__ + "); check paths, Docker and input constraints", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
