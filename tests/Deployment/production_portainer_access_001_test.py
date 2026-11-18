#!/usr/bin/env python3
"""PRODUCTION-MAKE-ENV-001 A05-portainer: resolved Compose, no live Docker effects."""
import json
import os
import pathlib
import re
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[2]
compose = ROOT / 'deploy/production/compose.yaml'
env = {k: v for k, v in os.environ.items() if not k.startswith(('FMONITOR_', 'COMPOSE_'))}
env.update({key: 'synthetic' for key in re.findall(r'\$\{(FMONITOR_[A-Z0-9_]+)', compose.read_text())})
env.update(FMONITOR_HTTP_PORT='18093', FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:fixture',
           FMONITOR_DATABASE_IMAGE='mariadb:11.4.7-noble', FMONITOR_BITRIX_CONFIG_HOST_FILE='/dev/null')
result = subprocess.run(
    ['docker', 'compose', '--env-file', '/dev/null', '-p', 'fm2-portainer-fixture',
     '-f', str(compose), '--profile', '*', 'config', '--format', 'json'],
    cwd=ROOT, env=env, text=True, capture_output=True, timeout=20)
assert result.returncode == 0, 'SETUP_FAILURE: ' + result.stderr
config = json.loads(result.stdout)
expected = {'db', 'php', 'web', 'jobs-worker', 'jobs-scheduler'}
assert expected <= config['services'].keys()
for name, service in config['services'].items():
    access = {k: v for k, v in service.get('labels', {}).items()
              if k.startswith('io.portainer.accesscontrol.')}
    wanted = {'io.portainer.accesscontrol.users': 'dev_3d-dev'} if name in expected else {}
    assert access == wanted, f'INTENDED_RED: {name} access labels {access!r}, expected {wanted!r}'
for kind in ('networks', 'volumes'):
    for name, resource in config.get(kind, {}).items():
        assert not any(k.startswith('io.portainer.accesscontrol.') for k in resource.get('labels', {})), (kind, name)
print('PASS production container access: five services, existing user only, helpers/resources excluded')
