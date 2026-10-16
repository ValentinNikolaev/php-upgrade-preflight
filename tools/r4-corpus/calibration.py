"""Offline real-Composer calibration, separate from measured analysis."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import uuid


def calibrate(image, ownership_path=None, owner=None):
    from run import composer_records, snapshot, ownership_update, validate_owner, owned_container, remove_owned_container, remove_owned_state, cleanup_unknown, docker_call
    standalone = owner is None
    receipt = None
    if standalone:
        receipt = Path(tempfile.mkdtemp(prefix='preflight-r4-calibration-receipt-'))
        ownership_path = receipt / 'ownership.json'
        token = uuid.uuid4().hex
        name = 'preflight-r4-' + token
        owner = {'version': 1, 'task_token': token, 'container_name': name, 'state_path': str(Path(tempfile.gettempdir()).resolve() / name), 'host_pid': os.getpid(), 'creation_started': False, 'create_settled': False, 'cleanup': {'container_removed': False, 'host_scratch_removed': False}}
        ownership_update(ownership_path, owner, 'prepared')
        validate_owner(owner).mkdir()
    state = validate_owner(owner) / 'calibration'
    name = owner['container_name']
    try:
        state.mkdir()
        for directory in ('output', 'scratch', 'home', 'cache', 'target'):
            (state / directory).mkdir()
        (state / 'target/input.txt').write_text('immutable calibration input\n')
        before = snapshot(state / 'target')
        command = ['create', '--name', name, '--label', 'preflight.r4.task=' + owner['task_token'], '--network', 'none', '--init', '--entrypoint', '/bin/sh']
        for source, target, readonly in ((Path(__file__).resolve().parent, '/harness', True), (state / 'target', '/target', True), (state / 'output', '/output', False), (state / 'scratch', '/scratch', False), (state / 'home', '/composer-home', False), (state / 'cache', '/composer-cache', False)):
            command += ['--mount', f'type=bind,source={source},target={target}' + (',readonly' if readonly else '')]
        command += [image, '/harness/calibrate.sh']
        owner['creation_started'] = True
        ownership_update(ownership_path, owner, 'creating')
        code = docker_call(command)
        owner['create_settled'] = True
        ownership_update(ownership_path, owner, 'created' if code == 0 else 'create_failed')
        if code:
            raise ValueError('offline calibration create failed')
        container = owned_container(owner)
        if container is None:
            raise ValueError('offline calibration container absent')
        result = subprocess.run(['docker', 'start', '--attach', container['Id']], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=60)
        if result.returncode != 0:
            raise ValueError('offline calibration container failed')
        inspection = owned_container(owner)
        if inspection is None or inspection['State'].get('Running') is not False:
            raise ValueError('offline calibration exit unknown')
        ownership_update(ownership_path, owner, 'exited')
        records = composer_records(state / 'output')
        if records['started_count'] != 3 or records['timed_count'] != 2:
            raise ValueError('real Composer measurement calibration failed')
        if before != snapshot(state / 'target'):
            raise ValueError('calibration immutability failed')
        result = json.loads((state / 'output/calibration.json').read_text())
        result['composer'] = records
        result['immutable'] = True
        print(json.dumps({'calibration': result}))
        return result
    finally:
        try:
            remove_owned_container(ownership_path, owner)
            if standalone:
                remove_owned_state(ownership_path, owner)
                shutil.rmtree(receipt)
            else:
                # This exact subdirectory was created above inside the validated owned root.
                validate_owner(owner)
                shutil.rmtree(state)
                owner.update(creation_started=False, create_settled=False)
                owner.pop('container_id', None)
                owner['cleanup'] = {'container_removed': False, 'host_scratch_removed': False}
                ownership_update(ownership_path, owner, 'prepared')
        except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as failure:
            cleanup_unknown(ownership_path, owner, failure)
            raise ValueError('calibration cleanup unknown; recover using ' + str(ownership_path)) from failure
