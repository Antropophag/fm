#!/usr/bin/env python3
"""PRODUCTION-MAKE-ENV-001 actual disposable Docker acceptance (never a live server).
One built image, two unique projects. Tests may seed synthetic bytes/credentials;
product flow upload regression is supplied by production_runtime_browser_001_test.php.
"""
import atexit,hashlib,html,http.cookiejar,json,os,pathlib,re,secrets,shutil,socket,subprocess,tempfile,urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[2];MANAGE=ROOT/'deploy/production/manage'
assert MANAGE.is_file(), 'INTENDED_RED: production deployment public workflow missing'

def command(args,env=None,ok=True,timeout=300):
    r=subprocess.run(args,cwd=ROOT,env=env,text=True,capture_output=True,timeout=timeout)
    if ok: assert r.returncode==0, 'command failed: '+str(args[:4])+'\n'+r.stdout[-2000:]+'\n'+r.stderr[-2000:]
    return r

def port():
    with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]

def write(cfg,d):
    cfg.write_text(''.join(k+'='+v+'\n' for k,v in d.items()));cfg.chmod(0o600)

def read(cfg):
    return dict(l.split('=',1) for l in cfg.read_text().splitlines() if l and not l.startswith('#'))

def make(verb,cfg,*extra,**kw):
    return command(['make','--no-print-directory',verb,'ENV_FILE='+str(cfg),*extra],**kw)

def compose(d,*args,**kw):
    env={k:v for k,v in os.environ.items() if not k.startswith(('FMONITOR_','COMPOSE_'))};env.update(d)
    return command(['docker','compose','--env-file','/dev/null','-p',d['COMPOSE_PROJECT_NAME'],'-f',str(ROOT/'deploy/production/compose.yaml'),*args],env=env,**kw)

def login(d,password):
    jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    base=d['FMONITOR_PUBLIC_BASE_URL']
    body=opener.open(base+'/pilot/login',timeout=15).read().decode()
    import urllib.parse
    for fields in [{'email':d['FMONITOR_INITIAL_OWNER_EMAIL']},{'email':d['FMONITOR_INITIAL_OWNER_EMAIL'],'password':password}]:
        token=html.unescape(re.search(r'name="_csrf" value="([^"]+)"',body).group(1))
        fields['_csrf']=token
        body=opener.open(base+'/pilot/login',urllib.parse.urlencode(fields).encode(),timeout=15).read().decode()
    r=opener.open(base+'/pilot/admin/users',timeout=15)
    assert r.status==200 and '/pilot/login' not in r.url,'initial/restored owner must authenticate'
    return opener

command(['docker','info','--format','{{.ServerVersion}}'],timeout=20)
token=secrets.token_hex(5);projects=[];owned_image=None;extra_images=[]
def remove_owned_images():
    for tag in extra_images+([owned_image] if owned_image else []):
        subprocess.run(['docker','image','rm',tag],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=30)
atexit.register(remove_owned_images)
with tempfile.TemporaryDirectory(prefix='fm2-prod-live-') as raw:
    home=pathlib.Path(raw).resolve();cfg=home/'source.env'
    make('env-init',cfg);d=read(cfg);source=command(['git','rev-parse','HEAD']).stdout.strip()
    image=os.environ.get('FMONITOR_PRODUCTION_TEST_IMAGE')
    if not image:
        image='fmonitor2-runtime:prod-acceptance-'+token;owned_image=image
        command(['docker','build','--build-arg','FMONITOR_SOURCE_REVISION='+source,'-f','deploy/runtime/Dockerfile','-t',image,'.'],timeout=900)
    # Force normal Composer failure, then prove actual plugin/autoload installation offline.
    archive_dir=home/'archives';archive_dir.mkdir()
    manifest=json.loads((ROOT/'deploy/dependencies/direct-dist-sha256.json').read_text())
    for package in manifest['packages']:
        archive=archive_dir/(package['sha256']+'.zip')
        with urllib.request.urlopen(package['url'],timeout=90) as response: data=response.read(100_000_000)
        assert len(data)==package['byteSize'] and hashlib.sha256(data).hexdigest()==package['sha256']
        archive.write_bytes(data)
    fixture=home/'fallback';fixture.mkdir()
    (fixture/'composer-first-fails').write_text('#!/bin/sh\ncase " $* " in *" install "*) if [ ! -f /tmp/first-composer-failed ]; then touch /tmp/first-composer-failed; exit 42; fi;; esac\nexec /usr/local/bin/composer "$@"\n')
    (fixture/'composer-first-fails').chmod(0o755)
    (fixture/'composer-primary-success').write_text('#!/bin/sh\ncase " $* " in *" install "*) mkdir -p "${COMPOSER_VENDOR_DIR:-vendor}"; cp -a /workspace/fmonitor-2/vendor/. "${COMPOSER_VENDOR_DIR:-vendor}/"; exit 0;; esac\nexec /usr/local/bin/composer "$@"\n')
    (fixture/'composer-platform-fails').write_text('#!/bin/sh\ncase " $* " in *" check-platform-reqs "*) exit 43;; esac\nexec /usr/local/bin/composer "$@"\n')
    curl='#!/bin/sh\noutput=; url=\nwhile [ "$#" -gt 0 ]; do case "$1" in -o|--output) shift; output=$1;; https://*) url=$1;; esac; shift; done\nprintf "%s\\n" "$url" >> /tmp/fetch-log\ncase "$url" in\n'
    for package in manifest['packages']:
        curl+=package['url']+') cp /archives/'+package['sha256']+'.zip "$output";;\n'
    curl+='*) exit 99;;\nesac\n'
    (fixture/'curl').write_text(curl)
    for f in fixture.iterdir():f.chmod(0o755)
    script=r'''set -eu
mkdir /tmp/project
cp composer.json composer.lock /tmp/project/
cp -a app /tmp/project/
sha256sum composer.json composer.lock > /tmp/before
bash deploy/dependencies/install --project /tmp/project --manifest /workspace/fmonitor-2/deploy/dependencies/direct-dist-sha256.json --composer /fixture/composer-first-fails --mode auto --archive-dir /archives
test -f /tmp/first-composer-failed
test -f /tmp/project/vendor/bower-asset/jquery/dist/jquery.js
php -r 'require "/tmp/project/vendor/autoload.php"; require "/tmp/project/vendor/yiisoft/yii2/Yii.php"; if(!class_exists("Yii")||!class_exists("TCPDF"))exit(1);'
cd /tmp/project
composer check-platform-reqs --no-dev
sha256sum composer.json composer.lock > /tmp/after
cmp /tmp/before /tmp/after
cd /workspace/fmonitor-2
# Same cache repeat must preserve exact installed package graph and original lock.
bash deploy/dependencies/install --project /tmp/project --mode direct --archive-dir /archives
# Corrupt existing cache fails and preserves previously working vendor.
cp -a /archives /tmp/corrupt-cache
archive=$(find /tmp/corrupt-cache -name '*.zip' | head -1)
printf broken > "$archive"
printf sentinel > /tmp/project/vendor/retained-sentinel
if bash deploy/dependencies/install --project /tmp/project --mode direct --archive-dir /tmp/corrupt-cache; then exit 91; fi
test "$(cat /tmp/project/vendor/retained-sentinel)" = sentinel
test "$(cat "$archive")" = broken
# Composer platform failure also cannot publish partial vendor.
if bash deploy/dependencies/install --project /tmp/project --mode direct --archive-dir /archives --composer /fixture/composer-platform-fails; then exit 92; fi
test "$(cat /tmp/project/vendor/retained-sentinel)" = sentinel
# Primary success never calls fallback downloader, even with empty cache/network disabled.
rm -f /tmp/fetch-log
PATH=/fixture:$PATH bash deploy/dependencies/install --project /tmp/project --mode auto --archive-dir /tmp/unused-cache --composer /fixture/composer-primary-success
test ! -e /tmp/fetch-log
# Missing cache downloads the exact approved URL through a transport fixture.
PATH=/fixture:$PATH bash deploy/dependencies/install --project /tmp/project --mode direct --archive-dir /tmp/download-cache
test "$(wc -l </tmp/fetch-log)" -eq 9
# A bad download must be rejected before Composer and keep the working vendor.
mkdir /tmp/bad-transport
printf '#!/bin/sh\nwhile [ "$#" -gt 0 ]; do case "$1" in -o|--output) shift; printf wrong > "$1";; esac; shift; done\n' > /tmp/bad-transport/curl
chmod +x /tmp/bad-transport/curl
printf sentinel > /tmp/project/vendor/retained-sentinel
if PATH=/tmp/bad-transport:$PATH bash deploy/dependencies/install --project /tmp/project --mode direct --archive-dir /tmp/bad-download; then exit 93; fi
test "$(cat /tmp/project/vendor/retained-sentinel)" = sentinel
'''
    command(['docker','run','--rm','--network','none','--user','0:0','-v',str(archive_dir)+':/archives:ro','-v',str(fixture)+':/fixture:ro','--entrypoint','sh',image,'-c',script],timeout=240)
    p=port();d.update(COMPOSE_PROJECT_NAME='fm2-prod-test-'+token,FMONITOR_IMAGE_MODE='local',FMONITOR_RUNTIME_IMAGE=image,FMONITOR_SOURCE_REVISION=source,FMONITOR_RUNTIME_ENV='production',FMONITOR_HTTP_PORT=str(p),FMONITOR_TRUSTED_REQUEST_HOST='127.0.0.1:'+str(p),FMONITOR_TRUSTED_REQUEST_SCHEME='http',FMONITOR_PUBLIC_BASE_URL='http://127.0.0.1:'+str(p),FMONITOR_ALLOW_HTTP='true',FMONITOR_INITIAL_OWNER_EMAIL='acceptance@shlz.ru',FMONITOR_JOBS_ENABLED='false',FMONITOR_DEPLOY_STATE_DIR=str(home/'deployment'),FMONITOR_BACKUP_ROOT=str(home/'backups'))
    for k in list(d):
        if k.startswith(('FMONITOR_BITRIX_','FMONITOR_ERP_','FMONITOR_SMTP_')):d[k]=''
    write(cfg,d);projects.append(d)
    def docker_boundary(name,body):
        folder=home/name;folder.mkdir();script=folder/'docker'
        script.write_text('#!/bin/sh\n'+body+'\nexec '+shutil.which('docker')+' "$@"\n');script.chmod(0o755)
        return dict(os.environ,PATH=str(folder)+os.pathsep+os.environ['PATH'])
    def running(data):return set(compose(data,'ps','--status','running','--services').stdout.splitlines())
    try:
        # A partially completed initial deployment must resume through exact provisioning,
        # while unrelated existing volumes cannot be adopted.
        foreign=dict(d,COMPOSE_PROJECT_NAME='fm2-prod-foreign-'+token,FMONITOR_DEPLOY_STATE_DIR=str(home/'foreign-state'))
        foreign_cfg=home/'foreign.env';write(foreign_cfg,foreign)
        foreign_volume=foreign['COMPOSE_PROJECT_NAME']+'_database'
        command(['docker','volume','create','--label','acceptance-token='+token,foreign_volume])
        try:
            before=command(['docker','volume','inspect',foreign_volume]).stdout
            assert make('deploy',foreign_cfg,ok=False).returncode!=0
            assert command(['docker','volume','inspect',foreign_volume]).stdout==before
        finally:command(['docker','volume','rm',foreign_volume])
        partial_env=docker_boundary('partial-start','case " $* " in *" up "*" php "*) exit 44;; esac')
        assert make('deploy',cfg,env=partial_env,ok=False,timeout=400).returncode!=0
        make('deploy',cfg,timeout=400)
        make('status',cfg)
        opener=login(d,d['FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD'])
        # Actual loaded settings, not presence of an unused ini file.
        ini=compose(d,'exec','-T','php','php','-r','echo json_encode([ini_get("upload_max_filesize"),ini_get("post_max_size"),class_exists("TCPDF")]);').stdout
        vals=json.loads(ini);assert int(vals[0].rstrip('M'))>=20 and int(vals[1].rstrip('M'))>=21
        # Add an immutable synthetic evidence file through runtime filesystem permissions.
        sentinel='evidence-'+token
        compose(d,'exec','-T','php','php','-r','umask(0077);file_put_contents(getenv("FMONITOR_SESSION_STATE_ROOT")."/acceptance-evidence",'+json.dumps(sentinel)+');')
        make('deploy',cfg,timeout=300)
        make('stop',cfg);make('start',cfg,timeout=300)
        assert opener.open(d['FMONITOR_PUBLIC_BASE_URL']+'/pilot/admin/users',timeout=15).status==200
        observed=compose(d,'exec','-T','php','php','-r','echo file_get_contents(getenv("FMONITOR_SESSION_STATE_ROOT")."/acceptance-evidence");').stdout
        assert observed==sentinel
        # Running backup restores the observed writer set; injected backup failure reports failure.
        before_running=running(d)
        make('backup',cfg,'BACKUP='+str(home/'backups'/'running-point'),timeout=300)
        assert running(d)==before_running
        failbackup=docker_boundary('fail-backup','case " $* " in *" fmonitor2-runtime-recovery.php "*" backup "*) exit 45;; esac')
        failure=make('backup',cfg,'BACKUP='+str(home/'backups'/'failed-point'),env=failbackup,ok=False,timeout=300)
        assert failure.returncode!=0
        assert not (home/'backups'/'failed-point').exists()
        assert running(d)==before_running or not {'web','php'}.intersection(running(d))
        assert any(word in (failure.stdout+failure.stderr).lower() for word in ['stopped','resum','останов','возобнов'])
        if running(d)!=before_running:make('start',cfg,timeout=300)
        # Hold a backup inside Docker after project lock acquisition. Contender may read but not mutate.
        held=home/'held';release_lock=home/'release-lock'
        hold=docker_boundary('hold-backup','case " $* " in *" fmonitor2-runtime-recovery.php "*" backup "*) touch '+str(held)+'; while [ ! -f '+str(release_lock)+' ]; do sleep 0.1; done;; esac')
        first=subprocess.Popen(['make','--no-print-directory','backup','ENV_FILE='+str(cfg),'BACKUP='+str(home/'backups'/'concurrent-point')],cwd=ROOT,env=hold,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
        import time
        deadline=time.monotonic()+90
        while not held.exists() and first.poll() is None and time.monotonic()<deadline:time.sleep(.1)
        try:
            assert held.exists(),'first operation must reach held recovery boundary'
            before=command(['docker','ps','-a','--filter','label=com.docker.compose.project='+d['COMPOSE_PROJECT_NAME'],'--format','{{.ID}} {{.State}}']).stdout
            contender_trace=home/'contender-trace'
            contender=docker_boundary('contender',"printf '%s\\n' \"$*\" >> "+str(contender_trace))
            second=make('start',cfg,env=contender,ok=False,timeout=20)
            assert second.returncode!=0,'contending operation cannot bypass project lock'
            after=command(['docker','ps','-a','--filter','label=com.docker.compose.project='+d['COMPOSE_PROJECT_NAME'],'--format','{{.ID}} {{.State}}']).stdout
            assert after==before,'contender cannot mutate container identity/state while backup lock held'
            calls=contender_trace.read_text().splitlines() if contender_trace.exists() else []
            assert not any(re.search(r'(?:^| )(?:run|up|start|stop|restart|down|rm|load|pull|build)(?: |$)',line) for line in calls),'contender must not invoke Docker mutations'
        finally:
            release_lock.touch();out,err=first.communicate(timeout=300)
        assert first.returncode==0,out+err
        assert running(d)==before_running
        # A backup requested while stopped must not start HTTP writers implicitly.
        make('stop',cfg)
        backup=home/'backups'/'point-one'
        make('backup',cfg,'BACKUP='+str(backup),timeout=300)
        running=compose(d,'ps','--status','running','--services').stdout.splitlines()
        assert not {'php','web','jobs-worker','jobs-scheduler'}.intersection(running)
        assert backup.is_dir(),'explicit backup destination must be published'
        manifests=list(backup.rglob('manifest.json'))
        canonical=[json.loads(m.read_text()) for m in manifests if json.loads(m.read_text()).get('formatVersion')=='fmonitor-runtime-backup-v1']
        assert len(canonical)==1 and canonical[0]['database']['schemaVersion']==39
        assert canonical[0]['deferred']==[]
        make('start',cfg,timeout=300)
        # Restore collision cannot overwrite this source.
        assert make('restore',cfg,'BACKUP='+str(backup),ok=False).returncode!=0
        assert opener.open(d['FMONITOR_PUBLIC_BASE_URL']+'/pilot/admin/users',timeout=15).status==200
        target=home/'target.env';td=dict(d);tp=port()
        td.update(COMPOSE_PROJECT_NAME='fm2-prod-restore-'+token,FMONITOR_HTTP_PORT=str(tp),FMONITOR_TRUSTED_REQUEST_HOST='127.0.0.1:'+str(tp),FMONITOR_PUBLIC_BASE_URL='http://127.0.0.1:'+str(tp),FMONITOR_DEPLOY_STATE_DIR=str(home/'target-deployment'),FMONITOR_DB_PASSWORD=secrets.token_hex(32),FMONITOR_MIGRATION_DB_PASSWORD=secrets.token_hex(32))
        write(target,td);projects.append(td)
        make('restore',target,'BACKUP='+str(backup),timeout=400)
        login(td,d['FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD'])
        actual=compose(td,'exec','-T','php','php','-r','echo hash("sha256",getenv("FMONITOR_DB_PASSWORD"));').stdout
        assert actual==hashlib.sha256(td['FMONITOR_DB_PASSWORD'].encode()).hexdigest()
        assert td['FMONITOR_DB_PASSWORD']!=d['FMONITOR_DB_PASSWORD']
        assert compose(td,'exec','-T','php','php','-r','echo hash("sha256",getenv("FMONITOR_YII_IDENTITY_KEY"));').stdout==hashlib.sha256(d['FMONITOR_YII_IDENTITY_KEY'].encode()).hexdigest()
        assert compose(td,'exec','-T','php','php','-r','echo file_get_contents(getenv("FMONITOR_SESSION_STATE_ROOT")."/acceptance-evidence");').stdout==sentinel
        assert make('restore',target,'BACKUP='+str(backup),ok=False).returncode!=0
        # Corrupt a copy: a brand-new target must not even get a DB container.
        corrupt=home/'corrupt';shutil.copytree(backup,corrupt)
        sql=next(corrupt.rglob('database.sql'));sql.write_bytes(sql.read_bytes()+b'corrupt')
        bad=dict(td,COMPOSE_PROJECT_NAME='fm2-prod-corrupt-'+token,FMONITOR_DEPLOY_STATE_DIR=str(home/'bad-state'));badcfg=home/'bad.env';write(badcfg,bad)
        assert make('restore',badcfg,'BACKUP='+str(corrupt),ok=False).returncode!=0
        assert command(['docker','ps','-aq','--filter','label=com.docker.compose.project='+bad['COMPOSE_PROJECT_NAME']]).stdout.strip()==''
        # Self-consistent but invalid schema must also be rejected BEFORE target resources.
        semantic=home/'semantic-invalid';shutil.copytree(backup,semantic)
        mf=next(p for p in semantic.rglob('manifest.json') if json.loads(p.read_text()).get('formatVersion')=='fmonitor-runtime-backup-v1')
        md=json.loads(mf.read_text());md['database']['schemaVersion']=38;mf.write_text(json.dumps(md,separators=(',',':'))+'\n')
        outer=semantic/'private-manifest.json';outer_data=json.loads(outer.read_text());outer_data['runtimeManifestSha256']=hashlib.sha256(mf.read_bytes()).hexdigest();outer.write_text(json.dumps(outer_data,separators=(',',':'))+'\n')
        bad['COMPOSE_PROJECT_NAME']='fm2-prod-semantic-'+token;bad['FMONITOR_DEPLOY_STATE_DIR']=str(home/'semantic-state');write(badcfg,bad)
        assert make('restore',badcfg,'BACKUP='+str(semantic),ok=False).returncode!=0
        assert command(['docker','ps','-aq','--filter','label=com.docker.compose.project='+bad['COMPOSE_PROJECT_NAME']]).stdout.strip()==''
        for suffix in ['database','state','secrets']:
            assert command(['docker','volume','inspect',bad['COMPOSE_PROJECT_NAME']+'_'+suffix],ok=False).returncode!=0
        assert command(['docker','network','inspect',bad['COMPOSE_PROJECT_NAME']+'_default'],ok=False).returncode!=0
        # Release contains deployment tooling and images, never this private env or evidence.
        release=home/'release'
        make('release',cfg,'OUTPUT='+str(release),timeout=300)
        assert release.is_dir() and (release/'Makefile').is_file()
        command(['make','--no-print-directory','-C',str(release),'env-init'])
        release_env=read(release/'.env')
        assert release_env['FMONITOR_IMAGE_MODE']=='local' and release_env['FMONITOR_SOURCE_REVISION']==source
        assert release_env['FMONITOR_RUNTIME_IMAGE']==image
        archive=pathlib.Path(release_env['FMONITOR_IMAGE_ARCHIVE'])
        assert archive.is_file() and hashlib.sha256(archive.read_bytes()).hexdigest()==release_env['FMONITOR_IMAGE_ARCHIVE_SHA256']
        # Newly generated operator config is removed before checking the distributed package.
        (release/'.env').unlink()
        assert not any(x.name=='.env' for x in release.rglob('*'))
        for f in release.rglob('*'):
            if f.is_file() and f.stat().st_size<2_000_000:
                assert d['FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD'].encode() not in f.read_bytes()
        # Complete a changed-image update on the restored target; source project is unaffected.
        updated='fmonitor2-runtime:prod-success-'+token
        updated_file=home/'Success.Dockerfile';updated_file.write_text('FROM '+image+'\nLABEL org.opencontainers.image.revision="'+'c'*40+'"\n')
        command(['docker','build','-f',str(updated_file),'-t',updated,str(home)],timeout=120);extra_images.append(updated)
        try:
            before_secrets={k:v for k,v in td.items() if any(t in k for t in ('PASSWORD','KEY','TOKEN'))}
            td.update(FMONITOR_RUNTIME_IMAGE=updated,FMONITOR_SOURCE_REVISION='c'*40);write(target,td)
            source_before=command(['docker','ps','-q','--filter','label=com.docker.compose.project='+d['COMPOSE_PROJECT_NAME']]).stdout
            prior_manifests=set(str(p) for p in (home/'backups').rglob('manifest.json'))
            observer=home/'update-observer';observer.mkdir();trace=home/'update-trace.jsonl'
            observer_script=observer/'docker'
            observer_script.write_text('#!'+shutil.which('python3')+'\n'+
                'import sys,subprocess,json,pathlib\n'+
                'a=sys.argv[1:]\n'+
                'with open('+repr(str(trace))+',"a") as f:f.write(json.dumps(a)+"\\n")\n'+
                'if "migrate" in a or "schema-migrate/run" in a:\n'+
                ' running=subprocess.check_output(['+repr(shutil.which('docker'))+',"ps","--filter",'+repr('label=com.docker.compose.project='+td['COMPOSE_PROJECT_NAME'])+',"--format",'+repr('{{.Label "com.docker.compose.service"}}')+'],text=True).splitlines()\n'+
                ' assert not set(running)&{"web","php","jobs-worker","jobs-scheduler"}, "UPDATE_OBSERVER_REJECTED_WRITERS_RUNNING"\n'+
                ' previous=set('+repr(sorted(prior_manifests))+')\n'+
                ' new=[p for p in pathlib.Path('+repr(str(home/'backups'))+').rglob("manifest.json") if str(p) not in previous]\n'+
                ' assert any(json.loads(p.read_text()).get("sourceCommit")=='+repr(source)+' for p in new), "UPDATE_OBSERVER_REJECTED_NO_OLD_BACKUP"\n'+
                'sys.exit(subprocess.call(['+repr(shutil.which('docker'))+']+a))\n')
            observer_script.chmod(0o755)
            env=dict(os.environ,PATH=str(observer)+os.pathsep+os.environ['PATH'])
            make('update',target,env=env,timeout=400);make('status',target)
            traced=[json.loads(l) for l in trace.read_text().splitlines()]
            acquire=next(i for i,a in enumerate(traced) if a[:2]==['image','inspect'] and updated in a)
            backups=[i for i,a in enumerate(traced) if 'backup' in a and any('fmonitor2-runtime-recovery.php' in x for x in a)]
            migrations=[i for i,a in enumerate(traced) if 'migrate' in a or 'schema-migrate/run' in a]
            assert backups and migrations and acquire<backups[0]<migrations[0],'acquire -> completed old backup -> stopped migration'
            assert set(str(p) for p in (home/'backups').rglob('manifest.json'))>prior_manifests

            assert {k:v for k,v in read(target).items() if k in before_secrets}==before_secrets
            assert compose(td,'exec','-T','php','php','-r','echo file_get_contents(getenv("FMONITOR_SESSION_STATE_ROOT")."/acceptance-evidence");').stdout==sentinel
            login(td,d['FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD'])
            assert command(['docker','ps','-q','--filter','label=com.docker.compose.project='+d['COMPOSE_PROJECT_NAME']]).stdout==source_before
            assert any('c'*40 in p.read_text(errors='ignore') for p in pathlib.Path(td['FMONITOR_DEPLOY_STATE_DIR']).rglob('*') if p.is_file()),'successful receipt names new source'
            make('deploy',target,timeout=300)
        finally:command(['docker','image','rm',updated],ok=False)
        # Failed migration after backup must leave all target writers stopped.
        newimage='fmonitor2-runtime:prod-update-'+token
        dockerfile=home/'Update.Dockerfile'
        dockerfile.write_text('FROM '+image+'\nLABEL org.opencontainers.image.revision="'+'b'*40+'"\n')
        command(['docker','build','-f',str(dockerfile),'-t',newimage,str(home)],timeout=120);extra_images.append(newimage)
        try:
            failed=dict(d,FMONITOR_RUNTIME_IMAGE=newimage,FMONITOR_SOURCE_REVISION='b'*40)
            write(cfg,failed)
            stubdir=home/'docker-stub';stubdir.mkdir();real_docker=shutil.which('docker')
            stub=stubdir/'docker'
            stub.write_text('#!/bin/sh\ncase " $* " in *" run "*" migrate "*|*" schema-migrate/run "*) exit 42;; esac\nexec '+real_docker+' "$@"\n')
            stub.chmod(0o755)
            env=dict(os.environ,PATH=str(stubdir)+os.pathsep+os.environ['PATH'])
            assert make('update',cfg,env=env,ok=False,timeout=400).returncode!=0
            running=compose(d,'ps','--status','running','--services').stdout.splitlines()
            assert not {'php','web','jobs-worker','jobs-scheduler'}.intersection(running),'failed update cannot leave writers active'
            assert make('start',cfg,ok=False).returncode!=0,'failed update cannot bypass via start'
            assert (backup/'manifest.json').exists() or list(backup.rglob('manifest.json'))
        finally:
            write(cfg,d);command(['docker','image','rm',newimage],ok=False)
    finally:
        # These names are generated above and cannot refer to an existing/shared stand.
        for x in reversed(projects):
            assert x['COMPOSE_PROJECT_NAME'].endswith(token)
            compose(x,'--profile','jobs','--profile','deployment','down','--volumes','--remove-orphans',ok=False)
        for extra_image in extra_images:command(['docker','image','rm',extra_image],ok=False)
        if owned_image:command(['docker','image','rm',owned_image],ok=False)
print('PASS PRODUCTION-MAKE-ENV-001 real empty deploy/replay/login/restart/v39 backup/empty restore')
