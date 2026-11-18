#!/usr/bin/env python3
"""PRODUCTION-MAKE-ENV-001 A02: real Make/CLI, temporary paths, external fake Docker."""
import importlib.util
import pathlib
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('handoff', ROOT / 'tests/Deployment/production_handoff_001_test.py')
handoff = importlib.util.module_from_spec(spec)
spec.loader.exec_module(handoff)


class ProjectLock(unittest.TestCase):
    def setUp(self):
        self.f = handoff.Handoff()
        self.f.setUp()
        self.addCleanup(self.f.doCleanups)
        self.tmp = self.f.home / 'host-tmp'
        self.tmp.mkdir(mode=0o1777)
        self.tmp.chmod(0o1777)
        manage = self.f.repo / 'deploy/production/manage'
        source = manage.read_text()
        self.assertEqual(1, source.count('/tmp/'), 'fixture relocates the single lock namespace only')
        manage.write_text(source.replace('/tmp/', str(self.tmp) + '/'))
        self.f.commit('isolated host tmp')
        self.f.make('deploy')
        self.lock = self.tmp / (self.f.data['COMPOSE_PROJECT_NAME'] + '.lock')
        self.before = self.f.cfg.read_bytes()
        bindir = self.f.home / 'bin'
        (bindir / 'docker').rename(bindir / 'docker-original')
        (bindir / 'docker').write_text(
            '#!/usr/bin/env python3\nimport os,pathlib,sys\n'
            f'lock=pathlib.Path({str(self.lock)!r})\n'
            'if sys.argv[1:]==["info"]:\n'
            ' assert lock.is_dir(), "INTENDED_RED: direct project lock is not held"\n'
            ' assert lock.stat().st_mode & 0o777 == 0o700\n'
            f'os.execv(sys.executable,[sys.executable,{str(bindir / "docker-original")!r}]+sys.argv[1:])\n')
        (bindir / 'docker').chmod(0o755)

    def test_update_ignores_legacy_directory_and_releases_own_lock(self):
        legacy = self.tmp / 'fmonitor2-production-locks'
        legacy.mkdir(exist_ok=True, mode=0o700)
        marker = legacy / 'untouched'
        marker.write_text('keep')
        inode = legacy.stat().st_ino
        legacy.chmod(0)
        self.addCleanup(legacy.chmod, 0o700)
        neighbor = self.tmp / 'fm2-prod-neighbor.lock'
        neighbor.mkdir()
        r = self.f.make('update', ok=False)
        self.assertEqual(0, r.returncode, 'INTENDED_RED: legacy directory blocks Runner: ' + r.stderr)
        self.assertFalse(self.lock.exists())
        self.assertTrue(neighbor.is_dir())
        self.assertEqual((inode, 0), (legacy.stat().st_ino, legacy.stat().st_mode & 0o777))
        legacy.chmod(0o700)
        self.assertEqual('keep', marker.read_text())
        self.assertEqual(self.before, self.f.cfg.read_bytes())

    def test_existing_directory_file_and_symlink_block_without_docker(self):
        target = self.f.home / 'target'
        target.mkdir()
        for kind in ('directory', 'file', 'symlink'):
            with self.subTest(kind=kind):
                if kind == 'directory': self.lock.mkdir()
                elif kind == 'file': self.lock.write_text('keep')
                else: self.lock.symlink_to(target)
                inode = self.lock.lstat().st_ino
                before = len(self.f.calls())
                r = self.f.call(['bash', 'deploy/production/manage', 'update'])
                self.assertEqual(75, r.returncode, 'INTENDED_RED: existing project lock must block: ' + r.stderr)
                self.assertEqual(inode, self.lock.lstat().st_ino)
                self.assertEqual(before, len(self.f.calls()))
                self.assertEqual(self.before, self.f.cfg.read_bytes())
            if kind == 'directory': self.lock.rmdir()
            else: self.lock.unlink()

    def test_failed_update_releases_acquired_lock(self):
        self.f.commit('force build')
        self.f.env['HANDOFF_FAIL_BUILD'] = '1'
        before = len(self.f.calls())
        r = self.f.make('update', ok=False)
        self.assertNotEqual(0, r.returncode)
        self.assertTrue(any(c['args'][0] == 'build' for c in self.f.calls()[before:]), 'INTENDED_RED: build must be reached under the direct lock')
        self.assertNotIn('direct project lock is not held', r.stderr)
        self.assertFalse(self.lock.exists())
        self.assertEqual(self.before, self.f.cfg.read_bytes())


if __name__ == '__main__':
    unittest.main()
