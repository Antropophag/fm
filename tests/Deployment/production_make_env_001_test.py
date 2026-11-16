#!/usr/bin/env python3
"""PRODUCTION-MAKE-ENV-001 A01,A02,A04,A05: real public CLI, no Docker effects."""
import os, pathlib, subprocess, tempfile, re, stat
ROOT=pathlib.Path(__file__).resolve().parents[2]
MANAGE=ROOT/'deploy/production/manage'
assert MANAGE.is_file(), 'INTENDED_RED: one-env production operator is missing'

def invoke(verb, envfile, environ=None):
    env={k:v for k,v in os.environ.items() if not k.startswith(('FMONITOR_', 'COMPOSE_'))}
    env.update(environ or {})
    return subprocess.run(['bash',str(MANAGE),verb,'--env-file',str(envfile)],cwd=ROOT,env=env,text=True,capture_output=True,timeout=20)

def values(path):
    return dict(line.split('=',1) for line in path.read_text().splitlines() if line and not line.startswith('#'))

def write(path, data):
    path.write_text(''.join(k+'='+v+'\n' for k,v in data.items()));path.chmod(0o600)

with tempfile.TemporaryDirectory(prefix='fm2-production-env-') as raw:
    home=pathlib.Path(raw);cfg=home/'runtime.env'
    result=invoke('env-init',cfg)
    assert result.returncode==0, result.stderr
    original=cfg.read_bytes(); d=values(cfg)
    assert stat.S_IMODE(cfg.stat().st_mode)==0o600
    secret_keys=['FMONITOR_DB_PASSWORD','FMONITOR_MIGRATION_DB_PASSWORD','FMONITOR_YII_COOKIE_VALIDATION_KEY','FMONITOR_YII_IDENTITY_KEY','FMONITOR_ERP_EQUIPMENT_FACTS_HMAC_KEY','FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD']
    secrets=[d[k].strip("'\"") for k in secret_keys]
    assert all(re.fullmatch('[0-9a-f]{64,}',s) for s in secrets), 'generated secrets are independent >=32 random bytes'
    assert len(set(secrets))==len(secrets)
    assert all(s not in result.stdout+result.stderr for s in secrets), 'env-init must not display secrets'
    invoke('env-init',cfg)
    assert cfg.read_bytes()==original, 'repeat env-init must preserve keys'
    d.update(COMPOSE_PROJECT_NAME='fm2-prod-unit',FMONITOR_IMAGE_MODE='local',FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:test',FMONITOR_SOURCE_REVISION='a'*40,FMONITOR_RUNTIME_ENV='production',FMONITOR_TRUSTED_REQUEST_HOST='fmonitor.example.org',FMONITOR_TRUSTED_REQUEST_SCHEME='https',FMONITOR_PUBLIC_BASE_URL='https://fmonitor.example.org',FMONITOR_INITIAL_OWNER_EMAIL='owner@shlz.ru',FMONITOR_JOBS_ENABLED='false',FMONITOR_ALLOW_HTTP='false',FMONITOR_DEPLOY_STATE_DIR=str(home/'state'),FMONITOR_BACKUP_ROOT=str(home/'backups'))
    # Empty opt-out integrations MUST not need dummy credentials.
    for k in list(d):
        if k.startswith(('FMONITOR_BITRIX_','FMONITOR_ERP_','FMONITOR_SMTP_')): d[k]=''
    write(cfg,d)
    result=invoke('check-config',cfg)
    assert result.returncode==0, result.stdout+result.stderr
    assert invoke('is-production',cfg).returncode==0
    # All literals are data, even when a shell would execute them.
    evil="$(touch "+str(home/'pwned')+")`id`$PASSWORD # literal=with spaces"
    literal=dict(d,FMONITOR_DB_PASSWORD="'"+evil+"'")
    write(cfg,literal)
    result=invoke('check-config',cfg)
    assert result.returncode==0, result.stdout+result.stderr
    assert not (home/'pwned').exists()
    assert evil not in result.stdout+result.stderr
    write(cfg,d)
    # Shell inherited credentials must not repair missing file inputs.
    missing=dict(d);del missing['FMONITOR_DB_PASSWORD'];write(cfg,missing)
    assert invoke('check-config',cfg,{'FMONITOR_DB_PASSWORD':'inherited-secret'}).returncode!=0
    for change in [{'COMPOSE_PROJECT_NAME':'fm2-local-neighbor'}, {'FMONITOR_JOBS_ENABLED':'true'}, {'FMONITOR_INITIAL_OWNER_EMAIL':'bad@example.org'}, {'FMONITOR_TRUSTED_REQUEST_SCHEME':'http'}, {'FMONITOR_SOURCE_REVISION':'main'}, {'FMONITOR_DB_USER':'root'}, {'FMONITOR_YII_IDENTITY_KEY':'short'}, {'UNRECOGNIZED_SETTING':'private-secret'}]:
        write(cfg,dict(d,**change));r=invoke('check-config',cfg)
        assert r.returncode!=0, 'invalid configuration accepted: '+str(list(change))
        assert 'private-secret' not in r.stdout+r.stderr
    write(cfg,d);cfg.write_text(cfg.read_text()+'FMONITOR_DB_PASSWORD=duplicate-secret\n')
    assert invoke('check-config',cfg).returncode!=0
    write(cfg,d);cfg.chmod(0o644)
    assert invoke('check-config',cfg).returncode!=0, 'world-readable secret config rejected'
    cfg.chmod(0o600);link=home/'linked.env';link.symlink_to(cfg)
    assert invoke('check-config',link).returncode!=0
    assert invoke('env-init',link).returncode!=0
    assert cfg.read_bytes()==(''.join(k+'='+v+'\n' for k,v in d.items())).encode()
    # The parser accepts all supported literal quoting styles without executing any bytes.
    for literal in ["plain # literal=with spaces", "'single $VALUE `id` # = spaces'", '\"double $VALUE `id` # = spaces\"']:
        write(cfg,dict(d,FMONITOR_DB_PASSWORD=literal));r=invoke('check-config',cfg)
        assert r.returncode==0,r.stdout+r.stderr
        assert literal.strip("'\"") not in r.stdout+r.stderr
    for bad in ["BROKEN-IDENTIFIER=value\n", "1KEY=value\n", "FMONITOR_DB_PASSWORD='unclosed\n", 'FMONITOR_DB_PASSWORD="unclosed\n', 'FMONITOR_DB_PASSWORD=bad\x01value\n']:
        write(cfg,d);cfg.write_text(cfg.read_text()+bad)
        assert invoke('check-config',cfg).returncode!=0
    write(cfg,d);cfg.write_bytes(cfg.read_bytes().replace(b'\n',b'\r\n'))
    assert invoke('check-config',cfg).returncode!=0
    write(cfg,dict(d,FMONITOR_DB_PASSWORD='replace_me'))
    assert invoke('check-config',cfg).returncode!=0
    for name,creator in [('directory',lambda p:p.mkdir()),('fifo',lambda p:os.mkfifo(p))]:
        invalid=home/name;creator(invalid)
        assert invoke('check-config',invalid).returncode!=0
        assert invoke('env-init',invalid).returncode!=0
    private=home/'custom.env';private.write_text('private bytes\n');private.chmod(0o600)
    invoke('env-init',private);assert private.read_text()=='private bytes\n'
    concurrent=home/'race.env'
    args=['bash',str(MANAGE),'env-init','--env-file',str(concurrent)]
    jobs=[subprocess.Popen(args,cwd=ROOT,stdout=subprocess.PIPE,stderr=subprocess.PIPE) for _ in range(2)]
    outcomes=[p.communicate(timeout=20) for p in jobs]
    assert any(p.returncode==0 for p in jobs) and concurrent.is_file()
    racebytes=concurrent.read_bytes();assert 'FMONITOR_DB_PASSWORD' in values(concurrent)
    invoke('env-init',concurrent);assert concurrent.read_bytes()==racebytes
print('PASS PRODUCTION-MAKE-ENV-001 environment/literal/opt-out/rejections')
