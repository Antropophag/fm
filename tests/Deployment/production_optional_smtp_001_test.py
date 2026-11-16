#!/usr/bin/env python3
"""OPTIONAL-SMTP-001 S02: public production config, synthetic values, no Docker."""
import itertools
import os
import pathlib
import subprocess
import tempfile

ROOT = pathlib.Path(__file__).resolve().parents[2]
KEYS = ['FMONITOR_SMTP_' + suffix for suffix in ('HOST', 'USERNAME', 'PASSWORD', 'FROM_ADDRESS')]

def invoke(verb, cfg):
    env = {k: v for k, v in os.environ.items() if not k.startswith(('FMONITOR_', 'COMPOSE_'))}
    return subprocess.run(['bash', str(ROOT/'deploy/production/manage'), verb, '--env-file', str(cfg)],
                          cwd=ROOT, env=env, text=True, capture_output=True, timeout=20)

with tempfile.TemporaryDirectory(prefix='fm2-optional-smtp-') as raw:
    home = pathlib.Path(raw)
    cfg = home/'runtime.env'
    result = invoke('env-init', cfg)
    assert result.returncode == 0, result.stderr
    data = dict(line.split('=', 1) for line in cfg.read_text().splitlines() if line and not line.startswith('#'))
    data.update(COMPOSE_PROJECT_NAME='fm2-prod-smtp-test', FMONITOR_IMAGE_MODE='local',
        FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:test', FMONITOR_SOURCE_REVISION='a'*40,
        FMONITOR_RUNTIME_ENV='production', FMONITOR_TRUSTED_REQUEST_HOST='fmonitor.example.org',
        FMONITOR_TRUSTED_REQUEST_SCHEME='https', FMONITOR_PUBLIC_BASE_URL='https://fmonitor.example.org',
        FMONITOR_INITIAL_OWNER_EMAIL='owner@shlz.ru', FMONITOR_JOBS_ENABLED='true', FMONITOR_ALLOW_HTTP='false',
        FMONITOR_DEPLOY_STATE_DIR=str(home/'state'), FMONITOR_BACKUP_ROOT=str(home/'backups'),
        FMONITOR_BITRIX_WEBHOOK_URL='https://bitrix.example.org/rest/1/synthetic/',
        FMONITOR_BITRIX_DEPARTMENT_IDS_JSON='[1,2]', FMONITOR_ERP_HOST='erp.example.org',
        FMONITOR_ERP_DATABASE='erp', FMONITOR_ERP_USER='reader', FMONITOR_ERP_PASSWORD='synthetic-erp-value')
    complete = dict(zip(KEYS, ('smtp.example.org', 'mailer@example.org', 'synthetic-mail-value', 'mailer@example.org')))
    def check(overrides, success):
        candidate = dict(data, **overrides)
        cfg.write_text(''.join(k+'='+v+'\n' for k,v in candidate.items()))
        cfg.chmod(0o600)
        result = invoke('check-config', cfg)
        assert (result.returncode == 0) is success, 'INTENDED_RED optional SMTP config '+str(list(overrides))+' '+result.stdout+result.stderr
        assert 'synthetic-mail-value' not in result.stdout+result.stderr
        assert 'synthetic-erp-value' not in result.stdout+result.stderr
    check(dict.fromkeys(KEYS, ''), True)
    check(dict.fromkeys(KEYS, "'   '"), True)
    check(complete, True)
    for size in range(1,4):
        for subset in itertools.combinations(KEYS, size):
            check({key: complete[key] if key in subset else '' for key in KEYS}, False)
    check(dict(complete, FMONITOR_SMTP_PASSWORD="'   '"), False)
    for required in ('FMONITOR_BITRIX_WEBHOOK_URL', 'FMONITOR_ERP_PASSWORD'):
        check(dict(dict.fromkeys(KEYS, ''), **{required: ''}), False)
    check(dict(dict.fromkeys(KEYS, ''), FMONITOR_JOBS_ENABLED='false', FMONITOR_ERP_PASSWORD=''), True)
print('PASS OPTIONAL-SMTP-001 production blank/partial/complete credentials')
