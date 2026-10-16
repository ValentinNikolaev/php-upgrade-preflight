"""Offline real-Composer calibration, separate from measured analysis."""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import uuid


def calibrate(image):
    from run import composer_records, snapshot
    state = Path(tempfile.mkdtemp(prefix="preflight-r4-calibration-"))
    name = "preflight-r4-calibration-" + uuid.uuid4().hex
    try:
        for directory in ("output", "scratch", "home", "cache", "target"):
            (state / directory).mkdir()
        (state / "target/input.txt").write_text("immutable calibration input\n")
        before = snapshot(state / "target")
        command = ["docker", "run", "--name", name, "--network", "none", "--init", "--entrypoint", "/bin/sh"]
        for source, target, readonly in ((Path(__file__).resolve().parent, "/harness", True), (state / "target", "/target", True), (state / "output", "/output", False), (state / "scratch", "/scratch", False), (state / "home", "/composer-home", False), (state / "cache", "/composer-cache", False)):
            command += ["--mount", f"type=bind,source={source},target={target}" + (",readonly" if readonly else "")]
        command += [image, "/harness/calibrate.sh"]
        result = subprocess.run(command, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=60)
        if result.returncode != 0:
            raise ValueError("offline calibration container failed")
        records = composer_records(state / "output")
        exits = [item["resources"]["exit_code"] for item in records["invocations"]]
        if records["started_count"] != 3 or 0 not in exits or not any(code not in (0, None) for code in exits):
            raise ValueError("real Composer measurement calibration failed")
        if before != snapshot(state / "target"):
            raise ValueError("calibration immutability failed")
        result = json.loads((state / "output/calibration.json").read_text())
        result["composer"] = records
        result["immutable"] = True
        print(json.dumps({"calibration": result}))
        return result
    finally:
        removed = subprocess.run(["docker", "rm", "--force", name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=30)
        if removed.returncode == 0:
            shutil.rmtree(state)
        else:
            raise ValueError("calibration cleanup failed; inspect " + str(state))
