#!/usr/bin/env python3
"""EMAIL-FIRST-PASSWORD-RECOVERY-001: actual operator parser and Compose rendering; no service start."""
import base64
import json
import os
import re
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]

def invoke(verb, path):
    environment = {k: v for k, v in os.environ.items() if not k.startswith(('FMONITOR_', 'COMPOSE_'))}
    return subprocess.run(['bash', str(ROOT/'deploy/production/manage'), verb, '--env-file', str(path)], cwd=ROOT, env=environment, text=True, capture_output=True, timeout=25)

def write(path, data):
    path.write_text(''.join(k+'='+v+'\n' for k, v in data.items()))
    path.chmod(0o600)

with tempfile.TemporaryDirectory(prefix='fm2-recovery-config-') as raw:
    folder=Path(raw); path=folder/'runtime.env'
    result=invoke('env-init', path)
    assert result.returncode==0, result.stdout+result.stderr
    data=dict(line.split('=',1) for line in path.read_text().splitlines() if line and not line.startswith('#'))
    data.update(COMPOSE_PROJECT_NAME='fm2-prod-recovery-test', FMONITOR_IMAGE_MODE='local', FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:test', FMONITOR_SOURCE_REVISION='a'*40, FMONITOR_RUNTIME_ENV='production', FMONITOR_TRUSTED_REQUEST_HOST='portal.example.test', FMONITOR_TRUSTED_REQUEST_SCHEME='https', FMONITOR_PUBLIC_BASE_URL='https://portal.example.test', FMONITOR_INITIAL_OWNER_EMAIL='owner@shlz.ru', FMONITOR_JOBS_ENABLED='false', FMONITOR_ALLOW_HTTP='false', FMONITOR_DEPLOY_STATE_DIR=str(folder/'state'), FMONITOR_BACKUP_ROOT=str(folder/'backups'))
    for key in data:
        if key.startswith(('FMONITOR_BITRIX_', 'FMONITOR_ERP_', 'FMONITOR_SMTP_')): data[key]=''
    secret=base64.b64encode(b'R'*32).decode()
    assert len(base64.b64decode(secret))==32
    data.update(FMONITOR_PASSWORD_RECOVERY_KEY=secret, FMONITOR_PASSWORD_RECOVERY_KEY_ID='recovery-test-v1')
    write(path,data); result=invoke('check-config',path)
    assert result.returncode==0, 'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 operator accepts recovery configuration: '+result.stdout+result.stderr
    assert secret not in result.stdout+result.stderr, 'operator output hides recovery key'
    data.update(FMONITOR_SMTP_HOST='smtp.example.test', FMONITOR_SMTP_PORT='587', FMONITOR_SMTP_ENCRYPTION='tls', FMONITOR_SMTP_USERNAME='fixture@shlz.ru', FMONITOR_SMTP_PASSWORD='synthetic-smtp-secret', FMONITOR_SMTP_FROM_ADDRESS='fixture@shlz.ru', FMONITOR_SMTP_FROM_NAME='FMonitor', FMONITOR_SMTP_TIMEOUT_SECONDS='5', FMONITOR_SMTP_VERIFY_PEER='true')
    write(path,data)
    # Render every supported operator contour; scheduler is a real SMTP consumer.
    issues=[]
    for contour in ['deploy/production/compose.yaml','deploy/runtime/compose.yaml','tools/delivery/compose.runtime.yaml.in']:
        compose=ROOT/contour; values=dict(data)
        for key in re.findall(r'\$\{([A-Z0-9_]+):\?',compose.read_text()):
            if not values.get(key): values[key]='synthetic-fixture'
        envfile=folder/'contour.env';write(envfile,values)
        rendered=subprocess.run(['docker','compose','--env-file',str(envfile),'-f',str(compose),'--profile','*','config','--format','json'],cwd=ROOT,text=True,capture_output=True,timeout=25)
        assert rendered.returncode==0, 'compose fixture renders '+contour+' (details kept private)'
        services=json.loads(rendered.stdout)['services']
        recovery_consumers={'php','jobs-worker'}
        smtp_consumers={'php','jobs-worker','jobs-scheduler'}
        for name,service in services.items():
            env=service.get('environment',{})
            if name in recovery_consumers:
                for key in ['FMONITOR_PASSWORD_RECOVERY_KEY','FMONITOR_PASSWORD_RECOVERY_KEY_ID','FMONITOR_PUBLIC_BASE_URL']:
                    if env.get(key)!=data[key]: issues.append(contour+': '+name+' missing '+key)
            elif secret in json.dumps(env): issues.append(contour+': recovery key exposed to '+name)
            if name in smtp_consumers:
                for key in [key for key in data if key.startswith('FMONITOR_SMTP_')]:
                    if env.get(key)!=data[key]: issues.append(contour+': '+name+' missing '+key)
            elif 'synthetic-smtp-secret' in json.dumps(env): issues.append(contour+': SMTP secret exposed to '+name)
        assert secret not in rendered.stderr, 'compose diagnostics hide key'
    assert not issues, 'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 consumer wiring: '+', '.join(issues)
    # Optional missing recovery key must not prevent deployment of ordinary login.
    data.pop('FMONITOR_PASSWORD_RECOVERY_KEY');data.pop('FMONITOR_PASSWORD_RECOVERY_KEY_ID');write(path,data)
    result=invoke('check-config',path)
    assert result.returncode==0, 'optional recovery configuration must not break ordinary deployment'
print('PASS: recovery operator allowlist, Compose propagation and optional configuration')
