#!/usr/bin/env python3
"""PRODUCTION-MAKE-ENV-001 A11/A12: exact-lock manifest, rejected inputs, no network."""
import copy,hashlib,json,os,pathlib,shutil,subprocess,tempfile
ROOT=pathlib.Path(__file__).resolve().parents[2]
INSTALL=ROOT/'deploy/dependencies/install';MANIFEST=ROOT/'deploy/dependencies/direct-dist-sha256.json'
assert INSTALL.is_file() and MANIFEST.is_file(), 'INTENDED_RED: verified direct dependency fallback missing'
manifest=json.loads(MANIFEST.read_text()); lock=json.loads((ROOT/'composer.lock').read_text())
assert manifest['schemaVersion']==1
assert manifest['composerJsonSha256']==hashlib.sha256((ROOT/'composer.json').read_bytes()).hexdigest()
assert manifest['composerLockSha256']==hashlib.sha256((ROOT/'composer.lock').read_bytes()).hexdigest()
expected={(p['name'],p['version'],p['dist']['reference']) for p in lock['packages']}
assert {(p['name'],p['version'],p['reference']) for p in manifest['packages']}==expected
assert len(manifest['packages'])==len(expected)
with tempfile.TemporaryDirectory(prefix='fm2-direct-contract-') as raw:
    home=pathlib.Path(raw); project=home/'project';project.mkdir()
    for f in ['composer.json','composer.lock']:shutil.copyfile(ROOT/f,project/f)
    destination=project/'vendor';destination.mkdir();(destination/'retain').write_text('previous-vendor')
    cfg=home/'manifest.json'
    def run(m):
        cfg.write_text(json.dumps(m))
        return subprocess.run(['bash',str(INSTALL),'--project',str(project),'--manifest',str(cfg),'--check-manifest'],cwd=ROOT,text=True,capture_output=True,timeout=30)
    r=run(manifest);assert r.returncode==0,r.stdout+r.stderr
    mutations=[]
    m=copy.deepcopy(manifest);m['packages'].pop();mutations.append(m)
    m=copy.deepcopy(manifest);m['packages'].append(m['packages'][0]);mutations.append(m)
    for key,value in [('sha256',''),('reference','0'*40),('version','latest'),('url','http://codeload.github.com/repo.zip'),('url','https://attacker.invalid/archive'),('url','https://codeload.github.com/evil/repo/legacy.zip/'+manifest['packages'][0]['reference']),('byteSize',0)]:
        m=copy.deepcopy(manifest);m['packages'][0][key]=value;mutations.append(m)
    m=copy.deepcopy(manifest);m['composerLockSha256']='0'*64;mutations.append(m)
    m=copy.deepcopy(manifest);m['composerJsonSha256']='0'*64;mutations.append(m)
    for m in mutations:
        assert run(m).returncode!=0, 'manifest mismatch must fail before fetch'
        assert (destination/'retain').read_text()=='previous-vendor'
    # Primary success/fallback extraction/platform/network-none are tested on the built artifact
    # by production_make_live_001_test; this test never downloads packages.
assert '--ignore-platform-req' not in INSTALL.read_text(), 'platform checks must not be bypassed'
print('PASS PRODUCTION-MAKE-ENV-001 exact dependency manifest rejections')
