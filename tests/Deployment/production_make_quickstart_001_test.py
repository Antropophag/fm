#!/usr/bin/env python3
"""PRODUCTION-MAKE-QUICKSTART-001: real Make/CLI, bounded Docker boundary spy."""
import json
import os
import pathlib
import shutil
import stat
import subprocess
import sys
import tempfile
import time
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]


class Quickstart(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='fm2-quickstart-')
        self.addCleanup(self.tmp.cleanup)
        self.base = pathlib.Path(self.tmp.name).resolve()
        self.home = self.base / 'operator'
        self.home.mkdir(mode=0o700)
        self.repo = self.base / 'checkout'
        self.repo.mkdir()
        shutil.copy2(ROOT / 'Makefile', self.repo / 'Makefile')
        shutil.copytree(ROOT / 'deploy/production', self.repo / 'deploy/production')
        seed = self.base / 'seed'
        self.seed = seed
        seed.mkdir(mode=0o700)
        cfg = seed / 'seed.env'
        subprocess.run(['bash', str(ROOT / 'deploy/production/manage'), 'env-init', '--env-file', str(cfg)],
                       cwd=ROOT, env=dict(os.environ, HOME=str(seed)), check=True, capture_output=True)
        self.data = dict(line.split('=', 1) for line in cfg.read_text().splitlines() if line and not line.startswith('#'))
        self.data.update(COMPOSE_PROJECT_NAME='fm2-prod-quick-' + self.base.name.rsplit('-', 1)[-1].replace('_', 'a'),
                         FMONITOR_IMAGE_MODE='local', FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:quick-fixture',
                         FMONITOR_SOURCE_REVISION='a' * 40, FMONITOR_DEPLOY_STATE_DIR='auto', FMONITOR_BACKUP_ROOT='auto',
                         FMONITOR_TRUSTED_REQUEST_HOST='fmonitor.example.org', FMONITOR_PUBLIC_BASE_URL='https://fmonitor.example.org',
                         FMONITOR_INITIAL_OWNER_EMAIL='owner@shlz.ru', FMONITOR_JOBS_ENABLED='false')
        self.cfg = self.repo / '.env'
        self.bin = self.base / 'bin'
        self.bin.mkdir()
        self.trace = self.base / 'docker.jsonl'
        fake = self.bin / 'docker'
        fake.write_text('#!' + sys.executable + '\n' + '''import json,os,pathlib,sys,time
p=pathlib.Path(os.environ['FM2_TRACE'])
with p.open('a') as f:f.write(json.dumps(sys.argv[1:])+'\\n')
if os.environ.get('FM2_HOLD')=='1':
 end=time.monotonic()+15
 while not pathlib.Path(os.environ['FM2_RELEASE']).exists() and time.monotonic()<end:time.sleep(.05)
print('STUB_DOCKER_BOUNDARY')
sys.exit(97)
''')
        fake.chmod(0o755)
        self.env = dict(os.environ, HOME=str(self.home), PATH=str(self.bin) + os.pathsep + os.environ['PATH'],
                        FM2_TRACE=str(self.trace), FM2_RELEASE=str(self.base / 'release'))
        self.write()

    def write(self, changes=None, mode=0o644):
        data = self.data | (changes or {})
        self.cfg.write_text(''.join(k + '=' + v + '\n' for k, v in data.items()))
        self.cfg.chmod(mode)

    def call(self, verb='deploy', env=None):
        args = ['make', '--no-print-directory', verb] if verb != 'check-config' else ['bash', 'deploy/production/manage', verb]
        return subprocess.run(args, cwd=self.repo, env=self.env | (env or {}), text=True, capture_output=True, timeout=20)

    @property
    def managed(self):
        return self.home / '.fmonitor2-production' / self.data['COMPOSE_PROJECT_NAME']

    def assert_boundary(self, result):
        self.assertTrue(self.trace.exists(), 'INTENDED_RED: copied root env and auto paths must reach Docker without manual setup\n' + result.stderr)
        self.assertIn('STUB_DOCKER_BOUNDARY', result.stdout)
        self.assertNotEqual(result.returncode, 0, 'spy deliberately stops before real deployment')

    def assert_rejected(self, result):
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(self.trace.exists(), 'unsafe input must fail before Docker')

    def test_copied_env_plain_make_and_replay(self):
        original = self.cfg.read_bytes()
        identity = tuple(getattr(self.cfg.stat(), k) for k in ['st_dev', 'st_ino', 'st_uid', 'st_nlink'])
        secret = self.data['FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD']
        first = self.call()
        self.assert_boundary(first)
        self.assertEqual(stat.S_IMODE(self.cfg.stat().st_mode), 0o600)
        self.assertEqual(self.cfg.read_bytes(), original)
        self.assertEqual(tuple(getattr(self.cfg.stat(), k) for k in ['st_dev', 'st_ino', 'st_uid', 'st_nlink']), identity)
        self.assertNotIn(secret, first.stdout + first.stderr)
        for path in [self.home / '.fmonitor2-production', self.managed, self.managed / 'state', self.managed / 'backups']:
            self.assertTrue(path.is_dir(), str(path))
            self.assertEqual(stat.S_IMODE(path.stat().st_mode), 0o700)
            self.assertEqual(path.stat().st_uid, os.getuid())
        sentinel = self.managed / 'state' / 'retained'
        sentinel.write_text('retained')
        self.trace.unlink()
        self.assert_boundary(self.call())
        self.assertEqual(self.cfg.read_bytes(), original)
        self.assertEqual(tuple(getattr(self.cfg.stat(), k) for k in ['st_dev', 'st_ino', 'st_uid', 'st_nlink']), identity)
        self.assertEqual(sentinel.read_text(), 'retained')
        self.trace.unlink()
        self.write({'COMPOSE_PROJECT_NAME': self.data['COMPOSE_PROJECT_NAME'] + '-second'})
        self.assert_boundary(self.call())
        self.assertTrue(self.managed.with_name(self.managed.name + '-second').is_dir())
        self.assertEqual(sentinel.read_text(), 'retained')

    def test_env_init_portable_defaults_without_directories(self):
        generated = dict(line.split('=', 1) for line in (self.seed / 'seed.env').read_text().splitlines() if line and not line.startswith('#'))
        for key in ['FMONITOR_DEPLOY_STATE_DIR', 'FMONITOR_BACKUP_ROOT']:
            self.assertEqual(generated[key], 'auto', 'INTENDED_RED: env-init must emit portable managed paths')
        self.assertFalse((self.seed / '.fmonitor2-production').exists())

    def test_readonly_check_and_other_verbs_do_not_prepare(self):
        self.assert_rejected(self.call('check-config'))
        self.assertEqual(stat.S_IMODE(self.cfg.stat().st_mode), 0o644)
        self.cfg.chmod(0o600)
        r = self.call('check-config')
        self.assertEqual(r.returncode, 0, 'auto settings can be checked before first deploy: ' + r.stderr)
        self.assertFalse((self.home / '.fmonitor2-production').exists())
        for verb in ['status', 'start', 'stop', 'backup']:
            self.assert_rejected(self.call(verb))
            self.assertFalse((self.home / '.fmonitor2-production').exists())

    def test_invalid_values_and_home_do_not_create_managed_hierarchy(self):
        for changes in [{'COMPOSE_PROJECT_NAME': 'fm2-prod-../../escape'},
                        {'FMONITOR_PUBLIC_BASE_URL': 'http://fmonitor.example.org'},
                        {'FMONITOR_JOBS_ENABLED': 'true'}]:
            self.write(changes)
            self.assert_rejected(self.call())
            self.assertFalse((self.home / '.fmonitor2-production').exists())
        self.write()
        self.assert_rejected(self.call(env={'HOME': 'relative-home'}))
        alternate = self.base / 'alternate'
        alternate.mkdir(mode=0o700)
        link = self.base / 'linked-home'
        link.symlink_to(alternate, target_is_directory=True)
        self.assert_rejected(self.call(env={'HOME': str(link)}))
        self.assertFalse((alternate / '.fmonitor2-production').exists())

    def test_symlink_hardlink_and_nonregular_env_preserved(self):
        self.cfg.unlink()
        foreign = self.base / 'foreign.env'
        foreign.write_text('PRIVATE_SENTINEL')
        foreign.chmod(0o644)
        for kind in ['symlink', 'hardlink', 'directory', 'fifo']:
            with self.subTest(kind=kind):
                if kind == 'symlink': self.cfg.symlink_to(foreign)
                elif kind == 'hardlink': os.link(foreign, self.cfg)
                elif kind == 'directory': self.cfg.mkdir()
                else: os.mkfifo(self.cfg)
                self.assert_rejected(self.call())
                self.assertEqual(foreign.read_text(), 'PRIVATE_SENTINEL')
                self.assertEqual(stat.S_IMODE(foreign.stat().st_mode), 0o644)
                if kind == 'directory': self.cfg.rmdir()
                else: self.cfg.unlink()

    def test_unsafe_managed_components_are_not_adopted(self):
        root = self.home / '.fmonitor2-production'
        outside = self.base / 'outside'
        outside.mkdir(mode=0o700)
        for kind in ['public', 'symlink', 'file']:
            with self.subTest(kind=kind):
                if kind == 'public': root.mkdir(mode=0o755)
                elif kind == 'symlink': root.symlink_to(outside, target_is_directory=True)
                else: root.write_text('retained')
                self.assert_rejected(self.call())
                self.assertFalse((outside / self.data['COMPOSE_PROJECT_NAME']).exists())
                if kind == 'public':
                    self.assertEqual(stat.S_IMODE(root.stat().st_mode), 0o755)
                    root.rmdir()
                else: root.unlink()
        root.mkdir(mode=0o700)
        for kind in ['public', 'symlink', 'file']:
            with self.subTest(project=kind):
                if kind == 'public': self.managed.mkdir(mode=0o755)
                elif kind == 'symlink': self.managed.symlink_to(outside, target_is_directory=True)
                else: self.managed.write_text('retained')
                before = self.managed.lstat()
                self.assert_rejected(self.call())
                self.assertEqual(list(outside.iterdir()), [])
                after = self.managed.lstat()
                self.assertEqual((after.st_ino, after.st_mode, after.st_uid), (before.st_ino, before.st_mode, before.st_uid))
                if kind == 'public': self.managed.rmdir()
                else: self.managed.unlink()

    def foreign_owner_shim(self):
        real_stat = shutil.which('stat')
        shim = self.bin / 'stat'
        shim.write_text('#!' + sys.executable + '\n' + 'import os,sys\na=sys.argv[1:]\n'
                        'if a and a[-1].endswith(os.environ.get("FM2_FOREIGN_SUFFIX",".env")):\n'
                        ' for i in range(len(a)-1):\n'
                        '  if a[i] in ("-c","-f"):a[i+1]=a[i+1].replace("%u","424242")\n'
                        'os.execv(' + repr(real_stat) + ',[' + repr(real_stat) + ']+a)\n')
        shim.chmod(0o755)

    def test_foreign_owner_rejected_without_permission_change(self):
        self.foreign_owner_shim()
        self.assert_rejected(self.call())
        self.assertEqual(stat.S_IMODE(self.cfg.stat().st_mode), 0o644)
        self.cfg.chmod(0o600)
        managed_root = self.home / '.fmonitor2-production'
        managed_root.mkdir(mode=0o700)
        self.assert_rejected(self.call(env={'FM2_FOREIGN_SUFFIX': '/.fmonitor2-production'}))
        self.assertEqual(stat.S_IMODE(managed_root.stat().st_mode), 0o700)

    def test_foreign_home_project_and_leaves_rejected(self):
        self.foreign_owner_shim()
        self.cfg.chmod(0o600)
        self.assert_rejected(self.call(env={'FM2_FOREIGN_SUFFIX': '/operator'}))
        self.assertFalse((self.home / '.fmonitor2-production').exists())
        root = self.home / '.fmonitor2-production'
        root.mkdir(mode=0o700)
        self.managed.mkdir(mode=0o700)
        for path in [self.managed, self.managed / 'state', self.managed / 'backups']:
            if not path.exists(): path.mkdir(mode=0o700)
            marker = path / 'retained'
            marker.write_text('untouched')
            identity = (path.stat().st_ino, path.stat().st_uid, stat.S_IMODE(path.stat().st_mode))
            self.assert_rejected(self.call(env={'FM2_FOREIGN_SUFFIX': '/' + path.name}))
            self.assertEqual((path.stat().st_ino, path.stat().st_uid, stat.S_IMODE(path.stat().st_mode)), identity)
            self.assertEqual(marker.read_text(), 'untouched')

    def test_unsafe_managed_leaf_is_not_followed(self):
        root = self.home / '.fmonitor2-production'
        root.mkdir(mode=0o700)
        self.managed.mkdir(mode=0o700)
        outside = self.base / 'untouched'
        outside.mkdir(mode=0o700)
        for name in ['state', 'backups']:
            leaf = self.managed / name
            for kind in ['symlink', 'public', 'file']:
                with self.subTest(leaf=name, kind=kind):
                    # A prior rejection may have prepared the other safe leaf.
                    if leaf.is_dir() and not leaf.is_symlink(): leaf.rmdir()
                    if kind == 'symlink': leaf.symlink_to(outside, target_is_directory=True)
                    elif kind == 'public': leaf.mkdir(mode=0o755)
                    else: leaf.write_text('untouched')
                    before = leaf.lstat()
                    self.assert_rejected(self.call())
                    self.assertEqual(list(outside.iterdir()), [])
                    after = leaf.lstat()
                    self.assertEqual((after.st_ino, after.st_mode, after.st_uid), (before.st_ino, before.st_mode, before.st_uid))
                    if kind == 'public': leaf.rmdir()
                    else: leaf.unlink()

    def test_chmod_time_path_swap_cannot_modify_unrelated_target(self):
        self.chmod_swap('symlink')

    def test_chmod_time_same_bytes_replacement_rejected(self):
        self.chmod_swap('regular')

    def chmod_swap(self, kind):
        victim = self.base / 'unrelated.env'
        victim.write_text('UNRELATED_PRIVATE_BYTES')
        victim.chmod(0o644)
        old_bytes = self.cfg.read_bytes()
        old_inode = self.cfg.stat().st_ino
        saved = self.base / 'original.env'
        hit = self.base / 'swap-reached'
        real_chmod = shutil.which('chmod')
        shim = self.bin / 'chmod'
        shim.write_text('#!' + sys.executable + '\nimport os,pathlib,sys\n'
                        + 'cfg=pathlib.Path(' + repr(str(self.cfg)) + ')\n'
                        + 'hit=pathlib.Path(' + repr(str(hit)) + ')\n'
                        + 'if not hit.exists():\n'
                        + ' cfg.rename(' + repr(str(saved)) + ')\n'
                        + (' cfg.symlink_to(' + repr(str(victim)) + ')\n' if kind == 'symlink'
                           else ' cfg.write_bytes(pathlib.Path(' + repr(str(saved)) + ').read_bytes())\n os.chmod(cfg,0o600)\n')
                        + ' hit.touch()\n'
                        + 'os.execv(' + repr(real_chmod) + ',[' + repr(real_chmod) + ']+sys.argv[1:])\n')
        shim.chmod(0o755)
        result = self.call()
        self.assertTrue(hit.exists(), 'INTENDED_RED: deploy must reach owned-file permission preparation')
        self.assert_rejected(result)
        self.assertEqual(victim.read_text(), 'UNRELATED_PRIVATE_BYTES')
        self.assertEqual(stat.S_IMODE(victim.stat().st_mode), 0o644, 'chmod must be bound to original open file, not swapped pathname')
        self.assertEqual(saved.stat().st_ino, old_inode)
        self.assertEqual(saved.read_bytes(), old_bytes)
        self.assertNotIn('UNRELATED_PRIVATE_BYTES', result.stdout + result.stderr)

    def test_unfixable_mode_has_linux_filesystem_guidance(self):
        fake = self.bin / 'chmod'
        fake.write_text('#!/bin/sh\nexit 0\n')
        fake.chmod(0o755)
        r = self.call()
        self.assert_rejected(r)
        self.assertRegex(r.stdout + r.stderr, r'(?i)(WSL|Linux.filesystem)')
        self.assertEqual(stat.S_IMODE(self.cfg.stat().st_mode), 0o644)

    def test_literals_invalid_inputs_and_absolute_compatibility(self):
        injected = '$(touch ' + str(self.base / 'pwned') + ')`id` $VALUE # literal'
        self.write({'FMONITOR_DB_PASSWORD': injected})
        self.assert_boundary(self.call())
        self.assertFalse((self.base / 'pwned').exists())
        self.trace.unlink()
        self.write({'COMPOSE_PROJECT_NAME': 'fm2-prod-../../escape'})
        self.assert_rejected(self.call())
        self.write({'FMONITOR_DEPLOY_STATE_DIR': injected})
        self.assert_rejected(self.call())
        self.assertFalse((self.base / 'pwned').exists())
        absolute = self.base / 'explicit'
        absolute.mkdir(mode=0o700)
        changes = {'FMONITOR_DEPLOY_STATE_DIR': str(absolute / 'state'), 'FMONITOR_BACKUP_ROOT': str(absolute / 'backups')}
        self.write(changes)
        self.assert_boundary(self.call())
        self.trace.unlink()
        absolute.chmod(0o755)
        self.assert_rejected(self.call())
        self.assertEqual(stat.S_IMODE(absolute.stat().st_mode), 0o755)

    def test_concurrent_bootstrap_keeps_existing_project_lock(self):
        first = subprocess.Popen(['make', '--no-print-directory', 'deploy'], cwd=self.repo,
                                 env=self.env | {'FM2_HOLD': '1'}, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        try:
            deadline = time.monotonic() + 10
            while not self.trace.exists() and first.poll() is None and time.monotonic() < deadline: time.sleep(.05)
            self.assertTrue(self.trace.exists(), 'INTENDED_RED: first plain deploy must reach held Docker boundary')
            before = self.trace.read_bytes()
            second = self.call()
            self.assertNotEqual(second.returncode, 0)
            self.assertEqual(self.trace.read_bytes(), before, 'contender cannot reach Docker')
        finally:
            pathlib.Path(self.env['FM2_RELEASE']).touch()
            first.communicate(timeout=20)
        self.assertEqual(stat.S_IMODE(self.cfg.stat().st_mode), 0o600)


if __name__ == '__main__':
    unittest.main()
