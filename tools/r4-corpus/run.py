#!/usr/bin/env python3
"""Explicitly invoked R4 Docker experiment runner; Python standard library only."""
import argparse
import hashlib
import json
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
    if not args.app.is_dir() or not (args.repo / "vendor/autoload.php").is_file():
        raise ValueError("app directory and repo vendor/autoload.php must exist")
    if args.output.exists():
        raise ValueError("output must be a fresh nonexistent directory")
    paths = [args.app, args.output, args.cache, args.repo]
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


def resource_stats(path):
    result = {"available": False, "elapsed_seconds": None, "max_rss_kib": None, "exit_code": None, "termination_kind": "unknown", "termination_signal": None}
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
    except OSError:
        pass
    return result


def composer_records(output):
    records = []
    for path in sorted((output / "composer").glob("*.start")):
        parts = path.read_text().splitlines()
        stats = resource_stats(path.with_suffix(".time"))
        records.append({"id": path.stem, "started_unix_seconds": float(parts[0]), "kind": parts[1], "probe_applied": parts[2] if len(parts) > 2 else None, "resources": stats})
    return {"started_count": len(records), "timed_count": sum(r["resources"]["available"] for r in records), "invocations": records}


def leftovers(root):
    return snapshot(root)["shape"] if root.exists() else None


def write_record(path, data):
    path.write_text(json.dumps(data, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")


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


def provenance(repo, harness, image):
    def git(*command):
        completed = subprocess.run(["git", "-C", str(repo), *command], capture_output=True, timeout=30)
        return completed.stdout.decode("utf-8", "replace").strip() if completed.returncode == 0 else None
    hashes = {}
    for directory in (repo / "packages", repo / "vendor"):
        for path in sorted(directory.rglob("*")):
            if path.is_file():
                hashes[path.relative_to(repo).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    tool_digest = hashlib.sha256(json.dumps(hashes, sort_keys=True).encode()).hexdigest()
    image_result = subprocess.run(["docker", "image", "inspect", "--format", "{{.Id}}", image], capture_output=True, timeout=30)
    if image_result.returncode != 0:
        raise ValueError("selected image unavailable")
    lock = repo / "composer.lock"
    return {"tool_git_head": git("rev-parse", "HEAD"), "tool_git_dirty": bool(git("status", "--porcelain", "--untracked-files=no")), "tool_runtime_tree_sha256": tool_digest, "tool_runtime_files": len(hashes), "tool_composer_lock_sha256": hashlib.sha256(lock.read_bytes()).hexdigest() if lock.is_file() else None, "harness_files_sha256": {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(harness.iterdir()) if p.is_file() and p.suffix in (".py", ".php", ".sh") or p.name == "Dockerfile"}, "image_id": image_result.stdout.decode().strip()}


def run(args):
    validate(args)
    args.output.mkdir(parents=True)
    args.cache.mkdir(parents=True, exist_ok=True)
    state = Path(tempfile.mkdtemp(prefix="preflight-r4-"))
    scratch, home = state / "scratch", state / "composer-home"
    scratch.mkdir()
    home.mkdir()
    name = "preflight-r4-" + uuid.uuid4().hex
    record = {"harness_version": 1, "status": "incomplete", "source_digest_scope": "full host tree before/after; outside analysis and container wall timers", "input_before": None, "input_after": None, "immutable": None, "container_exit_code": None, "container_wall_seconds": None, "watchdog": False, "request": {key: value for key, value in vars(args).items() if key not in ("app", "repo", "output", "cache")}, "container_memory_limit_bytes": 2147483648, "composer_home": "isolated empty" if args.github_transport == "default" else "isolated public HTTPS configuration", "cleanup": {}}
    created = False
    cleanup_failed = False
    try:
        record["input_before"] = snapshot(args.app)
        record["github_transport"] = prepare_composer_home(home, args.github_transport)
        record["provenance"] = provenance(args.repo, Path(__file__).resolve().parent, args.image)
        command = ["create", "--name", name, "--label", "preflight.r4.task=" + name, "--init", "--memory", "2g", "--network", args.network, "--cap-drop", "ALL", "--security-opt", "no-new-privileges"]
        harness = Path(__file__).resolve().parent
        for source, destination, readonly in ((args.app, "/target", True), (args.repo, "/app", True), (harness, "/harness", True), (args.output, "/output", False), (args.cache, "/composer-cache", False), (home, "/composer-home", False), (scratch, "/scratch", False)):
            command += ["--mount", f"type=bind,source={source},target={destination}" + (",readonly" if readonly else "")]
        for key in ("target_major", "from_php", "target_php", "memory_limit", "composer_memory_limit", "scenario_timeout", "diagnostic_timeout", "probe", "github_transport"):
            command += ["--env", "R4_" + key.upper() + "=" + str(getattr(args, key))]
        if args.network == "none":
            command += ["--env", "COMPOSER_DISABLE_NETWORK=1"]
        command += [record["provenance"]["image_id"]]
        if docker_call(command):
            raise RuntimeError("container_create_failed")
        created = True
        started = time.monotonic()
        try:
            record["container_exit_code"] = subprocess.run(["docker", "start", "--attach", name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=args.overall_timeout).returncode
        except subprocess.TimeoutExpired:
            record["watchdog"] = True
            record["status"] = "watchdog_timeout"
            docker_call(["kill", "--signal", "TERM", name])
            try:
                subprocess.run(["docker", "wait", name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=3)
            except subprocess.TimeoutExpired:
                docker_call(["kill", "--signal", "KILL", name])
        record["container_wall_seconds"] = time.monotonic() - started
        inspection = subprocess.run(["docker", "inspect", "--format", "{{json .State}}", name], capture_output=True, timeout=30)
        if inspection.returncode == 0:
            container_state = json.loads(inspection.stdout)
            record["container_exit_code"] = container_state.get("ExitCode") if not container_state.get("Running") else None
            record["oom_killed"] = container_state.get("OOMKilled")
        collector = read_json(args.output / "collector.json")
        record["collector"] = collector
        record["container"] = read_json(args.output / "container.json")
        record["analyzer_resources"] = resource_stats(args.output / "analyzer.time")
        record["composer"] = composer_records(args.output)
        record["report_bytes"] = {name: (args.output / name).stat().st_size if (args.output / name).is_file() else None for name in ("report.json", "report.md")}
        if not record["watchdog"]:
            record["status"] = "completed" if collector and collector.get("status") == "completed" and record["container_exit_code"] == 0 else "failed"
    except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as failure:
        record["failure_class"] = type(failure).__name__
        record["status"] = "failed"
    finally:
        if created:
            try:
                cleanup_failed = docker_call(["rm", "--force", name]) != 0
            except (OSError, subprocess.SubprocessError):
                cleanup_failed = True
        record["cleanup"]["container_removed"] = not cleanup_failed
        try:
            record["input_after"] = snapshot(args.app)
            record["immutable"] = record["input_before"] == record["input_after"] if record["input_before"] is not None else None
            record["cleanup"]["scratch_before_host_cleanup"] = leftovers(scratch)
            record["composer_home_final"] = snapshot(home)
        except (OSError, ValueError):
            record["cleanup"]["inspection_failed"] = True
        if not cleanup_failed:
            try:
                shutil.rmtree(state)
                record["cleanup"]["host_scratch_removed"] = True
            except OSError:
                record["cleanup"]["host_scratch_removed"] = False
        if state.exists():
            # This is a dedicated task path, never a source, cache, or arbitrary Docker resource.
            record["cleanup"]["retained_state_path"] = str(state)
        if record["immutable"] is not True or cleanup_failed:
            record["status"] = "failed"
        write_record(args.output / "run.json", record)
    return 0 if record["status"] == "completed" else 1


def main(argv=None):
    args = arguments(argv)
    try:
        validate(args)
        if args.calibrate:
            from calibration import calibrate
            calibrate(args.image)
        return run(args)
    except (OSError, ValueError, subprocess.SubprocessError) as failure:
        print("R4 invocation failed (" + type(failure).__name__ + "); check paths, Docker and input constraints", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
