#!/usr/bin/env python3
"""PRODUCTION-RUNNER-REPAIR-001 A01-A04: real Make, isolated external Docker seam.

The stat adapter simulates another UID without elevated host rights. Docker run
executes the actual helper shell against the temporary bind, rather than faking
lock acquisition. This does not qualify a real Docker mount/UID boundary.
"""
import importlib.util
import json
import os
import pathlib
import subprocess
import time
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('handoff', ROOT / 'tests/Deployment/production_handoff_001_test.py')
handoff = importlib.util.module_from_spec(spec)
spec.loader.exec_module(handoff)

STAT = '''#!/usr/bin/env python3
import os,pathlib,sys
a=[v for v in sys.argv[1:] if v != '--']
if a[0] != '-c': os.execv('/usr/bin/stat',['stat']+sys.argv[1:])
p=pathlib.Path(a[-1]);s=p.stat();root=pathlib.Path(os.environ['HANDOFF_FIXTURE'])
uid=s.st_uid
if p == root/'locks' and (root/'foreign').exists(): uid+=1
v={'%d':str(s.st_dev),'%i':str(s.st_ino),'%u':str(uid),'%g':str(s.st_gid),'%a':oct(s.st_mode & 0o7777)[2:],'%h':str(s.st_nlink)}
out=a[1]
for k,val in v.items(): out=out.replace(k,val)
print(out)
'''

DOCKER = '''#!/usr/bin/env python3
import json,os,pathlib,subprocess,sys,time
r=pathlib.Path(os.environ['HANDOFF_FIXTURE']);a=sys.argv[1:]
mount=next((x for x in a if 'target=/locks' in x),None)
if a[0]=='run' and mount:
    with (r/'helper.jsonl').open('a') as f: f.write(json.dumps(a)+'\\n')
    if (r/'helper-fails').exists(): sys.exit(125)
    if 'release' in a and (r/'release-fails').exists(): sys.exit(125)
    if 'release' in a and (r/'tamper-token').exists():
        for directory in (r/'locks').glob('*.lock'):
            for child in directory.iterdir():
                if child.is_file(): child.write_text('foreign-holder')
    if 'release' in a and (r/'swap-lock').exists():
        import shutil
        for directory in (r/'locks').glob('*.lock'):
            original=directory.with_suffix('.original');directory.rename(original)
            shutil.copytree(original,directory)
    assert '--privileged' not in a and '--env-file' not in a and '-e' not in a
    assert '--read-only' in a and '--rm' in a
    assert '--pull=never' in a or ('--pull' in a and a[a.index('--pull')+1]=='never')
    for flag,value in [('--network','none'),('--cap-drop','ALL'),('--security-opt','no-new-privileges'),('--entrypoint','sh')]:
        assert flag+'='+value in a or (flag in a and a[a.index(flag)+1]==value), (flag,a)
    mounts=[a[i+1] for i,v in enumerate(a) if v=='--mount']
    assert mounts==['type=bind,source='+str(r/'locks')+',target=/locks']
    assert '--user' in a and a[a.index('--user')+1].split(':')[0]==str(os.getuid()+1)
    i=a.index('-c'); assert a[i-1].startswith('sha256:')
    script=a[i+1].replace('/locks',str(r/'locks'))
    if (r/'mount-swapped').exists():
        swapped=r/'replacement';swapped.mkdir(exist_ok=True,mode=0o700)
        script=script.replace(str(r/'locks'),str(swapped))
    sys.exit(subprocess.call(['sh','-c',script]+a[i+2:]))
if a==['info'] and (r/'hold').exists():
    (r/'entered').touch()
    limit=time.monotonic()+15
    while (r/'hold').exists() and time.monotonic()<limit: time.sleep(.02)
os.execv(sys.executable,[sys.executable,str(r/'bin/docker-original')]+a)
'''


class RunnerLock(unittest.TestCase):
    def setUp(self):
        self.f = handoff.Handoff()
        self.f.setUp()
        self.addCleanup(self.f.doCleanups)
        self.root = self.f.home
        # Only the fixed lock path is relocated; production source stays intact.
        for p in (self.f.repo / 'deploy/production').glob('*'):
            if p.is_file() and p.suffix in ('', '.sh'):
                p.write_text(p.read_text().replace('/tmp/fmonitor2-production-locks', str(self.root / 'locks')))
        self.f.commit('isolated lock namespace')
        self.f.make('deploy')
        self.lock = self.root / 'locks' / (self.f.data['COMPOSE_PROJECT_NAME'] + '.lock')
        bindir = self.root / 'bin'
        (bindir / 'docker').rename(bindir / 'docker-original')
        for name, source in [('stat', STAT), ('docker', DOCKER)]:
            p = bindir / name
            p.write_text(source)
            p.chmod(0o755)
        self.before = self.f.cfg.read_bytes()

    def foreign(self):
        (self.root / 'foreign').touch()

    def helpers(self):
        p = self.root / 'helper.jsonl'
        return [json.loads(x) for x in p.read_text().splitlines()] if p.exists() else []

    def assert_no_application_mutation(self, calls):
        for call in calls:
            a = call['args']
            self.assertNotIn(a[0], ['run', 'build', 'pull', 'load', 'rm'])
            if a[0] == 'compose':
                self.assertFalse(set(a) & {'up', 'stop', 'run', 'exec', 'start', 'down'})

    def test_foreign_owner_update_preserves_env_and_releases(self):
        self.foreign()
        self.f.make('status')
        self.f.commit('next version')
        result = self.f.make('update', ok=False)
        self.assertEqual(0, result.returncode, 'INTENDED_RED: installed Runner handover failed: ' + result.stderr)
        self.assertFalse(self.lock.exists())
        self.assertEqual(self.before, self.f.cfg.read_bytes())
        self.assertGreaterEqual(len(self.helpers()), 2)

    def test_old_native_holder_blocks_helper_and_is_preserved(self):
        self.foreign()
        self.lock.mkdir()
        n = len(self.f.calls())
        r = self.f.call(['bash', 'deploy/production/manage', 'update'], ok=False)
        self.assertEqual(75, r.returncode, 'INTENDED_RED: contention must be reported as busy: ' + r.stderr)
        self.assertIn('already running', r.stderr)
        self.assertTrue(self.lock.is_dir())
        self.assertEqual([], list(self.lock.iterdir()))
        self.assertFalse(any('release' in a for a in self.helpers()))
        self.assert_no_application_mutation(self.f.calls()[n:])

    def test_helper_holder_blocks_old_native_and_releases_after_failure(self):
        self.foreign()
        self.f.commit('force build')
        (self.root / 'hold').touch()
        env = dict(self.f.env, HANDOFF_FAIL_BUILD='1')
        p = subprocess.Popen(['bash', 'deploy/production/manage', 'update'], cwd=self.f.repo,
                             env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        try:
            limit = time.monotonic() + 10
            while not (self.root / 'entered').exists() and p.poll() is None and time.monotonic() < limit:
                time.sleep(.02)
            self.assertTrue((self.root / 'entered').exists(), 'INTENDED_RED: helper never acquired lock')
            with self.assertRaises(FileExistsError):
                self.lock.mkdir()
        finally:
            (self.root / 'hold').unlink(missing_ok=True)
            out, err = p.communicate(timeout=20)
        self.assertNotEqual(0, p.returncode)
        self.assertFalse(self.lock.exists(), out + err)

    def test_invalid_root_reports_lock_not_project_and_preserves_it(self):
        (self.root / 'locks').chmod(0o755)
        n = len(self.f.calls())
        r = self.f.make('update', ok=False)
        self.assertNotEqual(0, r.returncode)
        self.assertIn('PRODUCTION_LOCK_INVALID', r.stderr, 'INTENDED_RED: misleading project-name error')
        self.assertNotIn('COMPOSE_PROJECT_NAME', r.stderr)
        self.assertEqual(0o755, (self.root / 'locks').stat().st_mode & 0o777)
        self.assert_no_application_mutation(self.f.calls()[n:])

    def test_helper_failure_and_mount_substitution_fail_closed(self):
        self.foreign()
        for marker in ('helper-fails', 'mount-swapped'):
            with self.subTest(marker=marker):
                (self.root / marker).touch()
                n = len(self.f.calls())
                r = self.f.make('update', ok=False)
                self.assertNotEqual(0, r.returncode)
                self.assertIn('PRODUCTION_LOCK_', r.stderr, 'INTENDED_RED: missing helper diagnostic')
                self.assertFalse(self.lock.exists())
                self.assert_no_application_mutation(self.f.calls()[n:])
                (self.root / marker).unlink()

    def test_existing_symlink_lock_cannot_be_removed(self):
        self.foreign()
        target = self.root / 'untouched'
        target.mkdir()
        (target / 'sentinel').write_text('preserve')
        self.lock.symlink_to(target)
        r = self.f.call(['bash', 'deploy/production/manage', 'update'], ok=False)
        self.assertEqual(75, r.returncode, 'INTENDED_RED: existing lock must block')
        self.assertTrue(self.lock.is_symlink())
        self.assertEqual('preserve', (target / 'sentinel').read_text())
        self.lock.unlink()
        self.lock.write_text('existing-file')
        r = self.f.call(['bash', 'deploy/production/manage', 'update'], ok=False)
        self.assertEqual(75, r.returncode)
        self.assertEqual('existing-file', self.lock.read_text())
        self.assertFalse(any('release' in a for a in self.helpers()))

    def test_other_project_lock_does_not_block_update(self):
        self.foreign()
        self.f.commit('independent project update')
        neighbor = self.lock.with_name('fm2-prod-another.lock')
        neighbor.mkdir()
        r = self.f.make('update', ok=False)
        self.assertEqual(0, r.returncode, 'INTENDED_RED: independent project blocked: ' + r.stderr)
        self.assertTrue(neighbor.is_dir())
        self.assertFalse(self.lock.exists())

    def test_root_symlink_and_unverified_image_fail_closed(self):
        self.foreign()
        real = self.root / 'original-locks'
        (self.root / 'locks').rename(real)
        (self.root / 'locks').symlink_to(real)
        r = self.f.make('update', ok=False)
        self.assertIn('PRODUCTION_LOCK_INVALID', r.stderr, 'INTENDED_RED: unsafe lock root diagnostic')
        self.assertTrue((self.root / 'locks').is_symlink())
        (self.root / 'locks').unlink(); real.rename(self.root / 'locks')
        n = len(self.f.calls())
        self.f.env['HANDOFF_DB_DRIFT'] = '1'
        r = self.f.make('update', ok=False)
        self.assertNotEqual(0, r.returncode)
        self.assertIn('PRODUCTION_LOCK_HELPER_UNAVAILABLE', r.stderr)
        self.assertEqual([], self.helpers())
        self.assert_no_application_mutation(self.f.calls()[n:])

    def test_cleanup_failure_or_changed_holder_preserves_lock_and_fails(self):
        self.foreign()
        for marker in ('release-fails', 'tamper-token', 'swap-lock'):
            with self.subTest(marker=marker):
                (self.root / marker).touch()
                r = self.f.make('update', ok=False)
                self.assertNotEqual(0, r.returncode)
                self.assertIn('PRODUCTION_LOCK_', r.stderr)
                self.assertTrue(self.lock.is_dir(), 'INTENDED_RED: failed cleanup must preserve lock')
                (self.root / marker).unlink()
                # Test-only cleanup of our own temporary fixture between cases.
                import shutil
                shutil.rmtree(self.lock)


if __name__ == '__main__':
    unittest.main()
