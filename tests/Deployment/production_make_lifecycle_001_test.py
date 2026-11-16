#!/usr/bin/env python3
"""PRODUCTION-MAKE-ENV-001: production Make seam and zero-effect rejection."""
import os,pathlib,subprocess,tempfile
ROOT=pathlib.Path(__file__).resolve().parents[2]
assert (ROOT/'deploy/production/manage').is_file(), 'INTENDED_RED: production lifecycle missing'
with tempfile.TemporaryDirectory(prefix='fm2-prod-no-effect-') as raw:
    home=pathlib.Path(raw);binpath=home/'bin';binpath.mkdir();trace=home/'called'
    fake=binpath/'docker';fake.write_text('#!/bin/sh\nprintf docker >> "'+str(trace)+'"\nexit 99\n');fake.chmod(0o755)
    env=dict(os.environ,PATH=str(binpath)+os.pathsep+os.environ['PATH'])
    for verb in ['deploy','status','stop','start','backup','update','restore','release']:
        r=subprocess.run(['make','--no-print-directory',verb,'ENV_FILE='+str(home/'absent.env')],cwd=ROOT,env=env,text=True,capture_output=True,timeout=15)
        assert r.returncode!=0,'missing env fails: '+verb
        assert 'No rule to make target' not in r.stderr,'public Make target missing: '+verb
        assert not trace.exists(),'missing configuration must fail before Docker '+verb
    # Symlink config cannot redirect any lifecycle operation into another installation.
    foreign=home/'foreign';foreign.write_text('COMPOSE_PROJECT_NAME=fm2-local-foreign\n');foreign.chmod(0o600)
    linked=home/'linked.env';linked.symlink_to(foreign)
    for verb in ['deploy','backup','restore','update']:
        r=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),verb,'--env-file',str(linked)],cwd=ROOT,env=env,text=True,capture_output=True,timeout=15)
        assert r.returncode!=0 and not trace.exists()
    # Exercise image acquisition with a Docker process spy; all images have wrong source labels.
    config=home/'image.env'
    subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'env-init','--env-file',str(config)],cwd=ROOT,check=True,capture_output=True)
    d=dict(l.split('=',1) for l in config.read_text().splitlines() if l and not l.startswith('#'))
    d.update(COMPOSE_PROJECT_NAME='fm2-prod-modes',FMONITOR_RUNTIME_ENV='production',FMONITOR_SOURCE_REVISION='a'*40,FMONITOR_TRUSTED_REQUEST_HOST='fmonitor.example.org',FMONITOR_TRUSTED_REQUEST_SCHEME='https',FMONITOR_PUBLIC_BASE_URL='https://fmonitor.example.org',FMONITOR_INITIAL_OWNER_EMAIL='owner@shlz.ru',FMONITOR_JOBS_ENABLED='false',FMONITOR_ALLOW_HTTP='false',FMONITOR_DEPLOY_STATE_DIR=str(home/'state'),FMONITOR_BACKUP_ROOT=str(home/'backups'))
    for key in list(d):
        if key.startswith(('FMONITOR_ERP_','FMONITOR_SMTP_','FMONITOR_BITRIX_')):d[key]=''
    fake.write_text('''#!/usr/bin/env python3
import sys,json,os
from pathlib import Path
args=sys.argv[1:]
with open(os.environ['FM2_TRACE'],'a') as f:f.write(json.dumps(args)+'\\n')
if args and args[0]=='build':Path(os.environ['FM2_IMAGE_CREATED']).touch()
if args and args[0]=='compose' and 'up' in args and os.environ.get('FM2_FAIL_DB')=='1':sys.exit(77)
if args[:2]==['image','inspect']:
 if os.environ.get('FM2_MODE')=='build' and not Path(os.environ['FM2_IMAGE_CREATED']).exists():sys.exit(1)
 if '--format' in args:
  fmt=args[args.index('--format')+1]
  print((('a' if os.environ.get('FM2_CORRECT_LABEL')=='1' else 'b')*40) if 'revision' in fmt else ('linux/arm64' if '.Os' in fmt else ('sha256:'+'b'*64 if '.Id' in fmt else json.dumps({'org.opencontainers.image.revision':'b'*40}))))
 else:print(json.dumps([{'Id':'sha256:'+'b'*64,'Config':{'Labels':{'org.opencontainers.image.revision':'b'*40}}}]))
elif args and args[0] in ['ps','images']:pass
elif args and args[0] in ['volume','network','container','inspect']:sys.exit(1)
elif args and args[0]=='info':print('{}')
elif args[:2]==['compose','version']:print('2.40.0')
''')
    git=binpath/'git';git.write_text('#!/bin/sh\ncase " $* " in *" status "*) if [ "${FM2_UNTRACKED:-}" = 1 ]; then case " $* " in *--untracked-files=no*) :;; *) printf "?? app/untracked.php\\n";; esac; fi;; *" --show-toplevel "*) pwd;; *" --show-prefix "*) :;; *" rev-parse "*) printf "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\\n";; esac\n');git.chmod(0o755)
    import json
    env['FM2_TRACE']=str(trace);env['FM2_IMAGE_CREATED']=str(home/'image-created')
    for mode in ['build','pull','local']:
        trace.unlink(missing_ok=True);pathlib.Path(env['FM2_IMAGE_CREATED']).unlink(missing_ok=True);env['FM2_MODE']=mode
        image='registry.example.org/fmonitor@sha256:'+'a'*64 if mode=='pull' else 'fmonitor2-runtime:fixture'
        d.update(FMONITOR_IMAGE_MODE=mode,FMONITOR_RUNTIME_IMAGE=image)
        config.write_text(''.join(k+'='+v+'\n' for k,v in d.items()));config.chmod(0o600)
        result=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'deploy','--env-file',str(config)],cwd=ROOT,env=env,text=True,capture_output=True,timeout=30)
        assert result.returncode!=0,'wrong image source must reject'
        calls=[json.loads(l) for l in trace.read_text().splitlines()] if trace.exists() else []
        assert any(c[:2]==['image','inspect'] for c in calls),'must inspect actual image identity for '+mode
        assert not any('up' in c or 'run' in c for c in calls),'identity mismatch must precede DB effects'
        if mode=='build':assert any(c[0]=='build' or c[:2]==['buildx','build'] for c in calls)
        if mode=='pull':assert any(c[0]=='pull' for c in calls)
        if mode=='local':assert not any(c[0] in ['build','pull','load'] for c in calls)
    # A labelled build must not include untracked source bytes.
    trace.unlink(missing_ok=True);pathlib.Path(env['FM2_IMAGE_CREATED']).unlink(missing_ok=True)
    env.update(FM2_UNTRACKED='1',FM2_MODE='build');d.update(FMONITOR_IMAGE_MODE='build',FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:unique-untracked')
    config.write_text(''.join(k+'='+v+'\n' for k,v in d.items()));config.chmod(0o600)
    r=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'deploy','--env-file',str(config)],cwd=ROOT,env=env,capture_output=True,text=True,timeout=20)
    assert r.returncode!=0
    calls=[json.loads(l) for l in trace.read_text().splitlines()] if trace.exists() else []
    assert not any(c and c[0]=='build' for c in calls),'INTENDED_RED: untracked source must reject before labelled build'
    env.pop('FM2_UNTRACKED');env['FM2_MODE']='local'
    # Matching revision labels alone cannot authenticate a transferred image.
    env.update(FM2_CORRECT_LABEL='1',FM2_MODE='local',FM2_FAIL_DB='1')
    d.update(FMONITOR_IMAGE_MODE='local',FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:release-identity',FMONITOR_APPLICATION_IMAGE_ID='sha256:'+'a'*64,FMONITOR_DEPLOY_STATE_DIR=str(home/'expected-image-state'))
    config.write_text(''.join(k+'='+v+'\n' for k,v in d.items()));config.chmod(0o600)
    r=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'check-config','--env-file',str(config)],cwd=ROOT,env=env,capture_output=True,text=True,timeout=15)
    assert r.returncode==0,'INTENDED_RED: expected application image identity is a supported release input'
    trace.unlink(missing_ok=True)
    r=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'deploy','--env-file',str(config)],cwd=ROOT,env=env,capture_output=True,text=True,timeout=20)
    assert r.returncode!=0
    calls=[json.loads(l) for l in trace.read_text().splitlines()]
    assert not any('up' in c or 'run' in c for c in calls),'INTENDED_RED: same revision but wrong image ID must fail before DB'
    d['FMONITOR_APPLICATION_IMAGE_ID']='sha256:'+'b'*64
    config.write_text(''.join(k+'='+v+'\n' for k,v in d.items()));trace.unlink(missing_ok=True)
    r=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'deploy','--env-file',str(config)],cwd=ROOT,env=env,capture_output=True,text=True,timeout=20)
    calls=[json.loads(l) for l in trace.read_text().splitlines()]
    assert r.returncode!=0 and any('up' in c for c in calls),'matching exact image ID passes identity checks and reaches controlled DB boundary'
    env.pop('FM2_CORRECT_LABEL');env.pop('FM2_FAIL_DB')
    d['FMONITOR_DEPLOY_STATE_DIR']=str(home/'archive-state')
    # An explicitly selected archive is an integrity input even if an image tag exists locally.
    import hashlib
    archive=home/'images.tar';archive.write_bytes(b'synthetic image archive')
    d.update(FMONITOR_IMAGE_MODE='local',FMONITOR_IMAGE_ARCHIVE=str(archive),FMONITOR_IMAGE_ARCHIVE_SHA256=hashlib.sha256(archive.read_bytes()).hexdigest())
    config.write_text(''.join(k+'='+v+'\n' for k,v in d.items()));config.chmod(0o600)
    r=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'check-config','--env-file',str(config)],cwd=ROOT,env=env,capture_output=True,text=True,timeout=15)
    assert r.returncode==0,r.stdout+r.stderr
    archive.write_bytes(b'tampered')
    trace.unlink(missing_ok=True)
    r=subprocess.run(['bash',str(ROOT/'deploy/production/manage'),'deploy','--env-file',str(config)],cwd=ROOT,env=env,capture_output=True,text=True,timeout=15)
    assert r.returncode!=0
    calls=[json.loads(l) for l in trace.read_text().splitlines()] if trace.exists() else []
    assert not any('up' in c or 'run' in c or (c and c[0]=='load') for c in calls),'bad archive cannot be loaded or provision DB'
compose=(ROOT/'deploy/production/compose.yaml').read_text()
assert 'build:' not in compose,'runtime Compose must never implicitly build'
assert '127.0.0.1:' in compose,'only loopback HTTP publication'
assert 'max-size' in compose and 'max-file' in compose,'container log growth bounded'
assert 'bin/yii' in compose and 'schema-migrate/run' in compose,'canonical migration seam'
assert 'FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD' not in compose,'bootstrap password only one-shot injection'
print('PASS PRODUCTION-MAKE-ENV-001 Make/lifecycle closed preflight')
