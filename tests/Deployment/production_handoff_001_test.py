#!/usr/bin/env python3
"""PRODUCTION-HANDOFF-001: real Make/Git plus external Docker executable witness."""
import hashlib
import json
import os
import pathlib
import shutil
import subprocess
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]


class Handoff(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='fm2-handoff-')
        self.addCleanup(self.temp.cleanup)
        self.home = pathlib.Path(self.temp.name).resolve()
        self.repo = self.home / 'checkout'
        self.repo.mkdir()
        shutil.copytree(ROOT / 'deploy/production', self.repo / 'deploy/production')
        shutil.copy2(ROOT / 'Makefile', self.repo / 'Makefile')
        (self.repo / '.gitignore').write_text('.env\n')
        self.env = {k: v for k, v in os.environ.items() if not k.startswith(('FMONITOR_', 'COMPOSE_'))}
        self.env.update(HOME=str(self.home), HANDOFF_FIXTURE=str(self.home))
        self.git('init', '-q')
        self.a = self.commit('A')
        self.cfg = self.repo / '.env'
        self.call(['bash', 'deploy/production/manage', 'env-init'], ok=True)
        self.data = dict(l.split('=', 1) for l in self.cfg.read_text().splitlines() if l and not l.startswith('#'))
        self.data.update(COMPOSE_PROJECT_NAME='fm2-prod-' + self.home.name.lower().replace('_', '-'),
                         FMONITOR_TRUSTED_REQUEST_HOST='portal.example.org', FMONITOR_PUBLIC_BASE_URL='https://portal.example.org',
                         FMONITOR_INITIAL_OWNER_EMAIL='owner@shlz.ru', FMONITOR_JOBS_ENABLED='true',
                         FMONITOR_BITRIX_WEBHOOK_URL='https://bitrix.example/rest/1/workforce/',
                         FMONITOR_BITRIX_ORDER_DOCUMENT_WEBHOOK_URL='https://bitrix.example/rest/2/documents/',
                         FMONITOR_BITRIX_ORDER_DOCUMENT_ROOT_ID='123', FMONITOR_BITRIX_DEPARTMENT_IDS_JSON='[1]',
                         FMONITOR_ERP_HOST='erp.example', FMONITOR_ERP_DATABASE='erp', FMONITOR_ERP_USER='reader',
                         FMONITOR_ERP_PASSWORD='private-credential-do-not-print')
        self.write()
        bindir = self.home / 'bin'
        bindir.mkdir()
        shutil.copy2(ROOT / 'tests/Support/production_handoff_docker.py', bindir / 'docker')
        (bindir / 'docker').chmod(0o755)
        (bindir / 'awk').write_text('#!/bin/sh\nif [ "${HANDOFF_FAIL_REWRITE:-}" = 1 ]; then case "$*" in */restore.env*) exit 73;; esac; fi\nexec /usr/bin/awk "$@"\n')
        (bindir / 'awk').chmod(0o755)
        self.env['PATH'] = str(bindir) + os.pathsep + os.environ['PATH']
        self.state = self.home / '.fmonitor2-production' / self.data['COMPOSE_PROJECT_NAME'] / 'state'

    def call(self, args, ok=False):
        r = subprocess.run(args, cwd=self.repo, env=self.env, capture_output=True, text=True, timeout=45)
        if ok:
            self.assertEqual(0, r.returncode, r.stdout + r.stderr)
        self.assertNotIn('private-credential-do-not-print', r.stdout + r.stderr)
        self.assertNotIn('https://private.example', r.stdout + r.stderr)
        return r

    def git(self, *args):
        return self.call(['git', *args], ok=True).stdout.strip()

    def commit(self, text):
        (self.repo / 'source.txt').write_text(text)
        self.git('add', '.')
        self.git('-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.org', 'commit', '-qm', text)
        return self.git('rev-parse', 'HEAD')

    def write(self):
        self.cfg.write_text(''.join(k + '=' + v + '\n' for k, v in self.data.items()))
        self.cfg.chmod(0o600)
        self.before = self.cfg.read_bytes()

    def make(self, verb, ok=True):
        return self.call(['make', '--no-print-directory', verb], ok)

    def calls(self):
        p = self.home / 'trace.jsonl'
        return [json.loads(x) for x in p.read_text().splitlines()] if p.exists() else []

    def receipt(self):
        return dict(l.split('=', 1) for l in (self.state / 'installed').read_text().splitlines())

    def test_same_env_follows_git_and_preserves_installed_operations(self):
        # Old handoffs remain valid even after main advanced before first clone.
        self.data.update(FMONITOR_SOURCE_REVISION='a' * 40, FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:old-handoff')
        self.write()
        r = self.make('deploy')
        self.assertEqual(self.a, self.receipt()['source'])
        for stage in ('erp', 'workforce', 'documents'):
            self.assertIn('INTEGRATION_CHECK ' + stage + ' OK', r.stdout)
        old_image = self.receipt()['image']
        calls = len(self.calls())
        self.make('deploy')
        self.assertFalse(any('workforce-sync/run' in x['args'] or x['args'][0] == 'build' for x in self.calls()[calls:]))
        b = self.commit('B')
        self.make('status')
        self.make('stop')
        self.make('start')
        self.assertEqual(self.a, self.receipt()['source'])
        self.make('update')
        self.assertEqual(b, self.receipt()['source'])
        self.assertNotEqual(old_image, self.receipt()['image'])
        self.assertEqual(self.before, self.cfg.read_bytes())
        bundles = list(self.state.parent.glob('backups/update-*/restore.env'))
        self.assertEqual(1, len(bundles))
        restored = dict(l.split('=', 1) for l in bundles[0].read_text().splitlines() if l and not l.startswith('#'))
        self.assertEqual(self.a, restored['FMONITOR_SOURCE_REVISION'])
        self.assertEqual(old_image, restored['FMONITOR_RUNTIME_IMAGE'])
        self.assertEqual('local', restored['FMONITOR_IMAGE_MODE'])
        self.assertTrue(restored['FMONITOR_APPLICATION_IMAGE_ID'].startswith('sha256:'))
        self.assertEqual('linux/amd64', restored['FMONITOR_APPLICATION_PLATFORM'])
        self.assertEqual('sha256:' + 'd' * 64, restored['FMONITOR_DATABASE_IMAGE_ID'])
        self.assertEqual('linux/amd64', restored['FMONITOR_DATABASE_PLATFORM'])
        manifest = json.loads((bundles[0].parent / 'runtime/manifest.json').read_text())
        self.assertEqual(self.a, manifest['sourceCommit'])
        self.assertEqual(old_image, manifest['imageReference'])
        self.assertEqual(restored['FMONITOR_APPLICATION_IMAGE_ID'], manifest['imageId'])
        trace = self.calls()
        stop = next(i for i, x in enumerate(trace) if 'stop' in x['args'])
        backup = next(i for i, x in enumerate(trace) if 'bin/fmonitor2-runtime-recovery.php' in x['args'])
        migration = max(i for i, x in enumerate(trace) if 'migrate' in x['args'])
        self.assertLess(stop, backup)
        self.assertLess(backup, migration)
        calls = len(trace)
        self.make('update')
        self.assertFalse(any(x['args'][0] == 'build' or 'migrate' in x['args'] for x in self.calls()[calls:]))
        self.assertFalse(any('legacy-import/run' in x['args'] or 'schedule-once' in x['args'] for x in self.calls()))

    def test_all_integration_failures_are_visible_before_success(self):
        for stage in ('erp', 'workforce', 'documents'):
            with self.subTest(stage=stage):
                # Independent failed deployment, then repair using the same candidate.
                self.env['HANDOFF_FAIL_INTEGRATION'] = stage
                r = self.make('deploy', ok=False)
                self.assertNotEqual(0, r.returncode, 'INTENDED_RED: integration failure accepted')
                self.assertIn('INTEGRATION_CHECK ' + stage + ' FAILED', r.stdout + r.stderr)
                for other in ('erp', 'workforce', 'documents'):
                    self.assertIn('INTEGRATION_CHECK ' + other + (' FAILED' if other == stage else ' OK'), r.stdout + r.stderr)
                self.assertNotEqual('installed', self.receipt()['state'])
                running = json.loads((self.home / 'docker.json').read_text())['running']
                self.assertFalse(set(running) & {'php', 'web', 'jobs-worker', 'jobs-scheduler'})
        self.env.pop('HANDOFF_FAIL_INTEGRATION')
        result = self.make('deploy')
        for stage in ('erp', 'workforce', 'documents'):
            self.assertIn('INTEGRATION_CHECK ' + stage + ' OK', result.stdout)
        self.assertEqual('installed', self.receipt()['state'])
        self.assertEqual(self.before, self.cfg.read_bytes())

    def test_update_failure_retains_backup_and_blocks_start(self):
        self.make('deploy')
        self.commit('B')
        self.env['HANDOFF_FAIL_INTEGRATION'] = 'erp'
        self.assertNotEqual(0, self.make('update', ok=False).returncode)
        self.assertEqual('failed', self.receipt()['state'])
        self.assertTrue(list(self.state.parent.glob('backups/update-*/restore.env')))
        self.assertNotEqual(0, self.make('start', ok=False).returncode)
        self.assertNotEqual(0, self.make('status', ok=False).returncode)
        self.assertEqual(self.before, self.cfg.read_bytes())

    def test_jobs_disabled_and_optional_documents(self):
        self.data['FMONITOR_JOBS_ENABLED'] = 'false'
        self.write()
        result = self.make('deploy')
        for stage in ('erp', 'workforce', 'documents'):
            self.assertIn('INTEGRATION_CHECK ' + stage + ' SKIPPED', result.stdout)
        self.assertFalse(any(any('sync/' in arg for arg in x['args']) for x in self.calls()))
        self.data.update(FMONITOR_JOBS_ENABLED='true', FMONITOR_BITRIX_ORDER_DOCUMENT_WEBHOOK_URL='', FMONITOR_BITRIX_ORDER_DOCUMENT_ROOT_ID='')
        self.write()
        r = self.make('update')
        self.assertIn('INTEGRATION_CHECK documents SKIPPED', r.stdout)
        self.assertFalse(any('bitrix-order-document-links-sync/run' in x['args'] for x in self.calls()))

    def test_dirty_source_rejects_without_build_or_database(self):
        (self.repo / 'untracked.php').write_text('unreviewed')
        self.assertNotEqual(0, self.make('deploy', ok=False).returncode)
        self.assertFalse(any(x['args'][0] == 'build' or 'up' in x['args'] or 'run' in x['args'] for x in self.calls()))

    def assert_drift_rejected(self, verb):
        self.make('deploy')
        before_receipt = (self.state / 'installed').read_bytes()
        self.commit('B')
        self.env['HANDOFF_DB_DRIFT'] = '1'
        count = len(self.calls())
        args = ['make', '--no-print-directory', verb]
        if verb == 'release':
            args.append('OUTPUT=' + str(self.home / 'release'))
        result = self.call(args)
        self.assertNotEqual(0, result.returncode, 'INTENDED_RED DB drift must reject ' + verb)
        effects = [x for x in self.calls()[count:] if x['args'][0] in ('build', 'run', 'save', 'pull') or any(y in x['args'] for y in ('up', 'stop', 'migrate'))]
        self.assertEqual([], effects, 'INTENDED_RED DB identity before candidate/backup/release effects')
        self.assertEqual(before_receipt, (self.state / 'installed').read_bytes())

    def test_database_tag_drift_rejects_before_update(self):
        self.assert_drift_rejected('update')

    def test_database_tag_drift_rejects_before_release(self):
        self.assert_drift_rejected('release')

    def test_build_to_pinned_mode_backup_uses_old_installed_identity(self):
        self.data.update(FMONITOR_SOURCE_REVISION='a' * 40, FMONITOR_RUNTIME_IMAGE='fmonitor2-runtime:obsolete')
        self.write()
        self.make('deploy')
        old = self.receipt()
        self.data.update(FMONITOR_IMAGE_MODE='local', FMONITOR_SOURCE_REVISION=old['source'],
                         FMONITOR_RUNTIME_IMAGE=old['image'], FMONITOR_APPLICATION_IMAGE_ID=old['image_id'])
        self.write()
        self.make('update')
        bundle = next(self.state.parent.glob('backups/update-*/restore.env'))
        restored = dict(l.split('=', 1) for l in bundle.read_text().splitlines() if l and not l.startswith('#'))
        self.assertEqual(old['source'], restored['FMONITOR_SOURCE_REVISION'], 'INTENDED_RED recovery follows old installation not candidate mode')
        self.assertEqual(old['image'], restored['FMONITOR_RUNTIME_IMAGE'])
        self.assertEqual('local', restored['FMONITOR_IMAGE_MODE'])
        self.assertEqual(old['image_id'], restored['FMONITOR_APPLICATION_IMAGE_ID'])
        for field, key in [('application_platform', 'FMONITOR_APPLICATION_PLATFORM'), ('database_image', 'FMONITOR_DATABASE_IMAGE'), ('database_image_id', 'FMONITOR_DATABASE_IMAGE_ID'), ('database_platform', 'FMONITOR_DATABASE_PLATFORM')]:
            self.assertEqual(old[field], restored[key])
        manifest = json.loads((bundle.parent / 'runtime/manifest.json').read_text())
        self.assertEqual([old['source'], old['image'], old['image_id']], [manifest['sourceCommit'], manifest['imageReference'], manifest['imageId']])
        self.assertEqual(self.before, self.cfg.read_bytes())

    def test_failed_backup_config_rewrite_resumes_and_cleans_stage(self):
        self.make('deploy')
        running = json.loads((self.home / 'docker.json').read_text())['running']
        self.env['HANDOFF_FAIL_REWRITE'] = '1'
        result = self.make('backup', ok=False)
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(running, json.loads((self.home / 'docker.json').read_text())['running'], 'INTENDED_RED manual backup failure resumes original writers')
        self.assertEqual([], list(self.state.parent.glob('backups/*.stage.*')), 'incomplete recovery stage cleaned')
        self.assertIn('resumed', result.stdout + result.stderr)
        self.assertEqual('installed', self.receipt()['state'])

    def test_update_backup_rewrite_failure_keeps_writers_stopped(self):
        self.make('deploy')
        before_receipt = (self.state / 'installed').read_bytes()
        self.commit('B')
        self.env['HANDOFF_FAIL_REWRITE'] = '1'
        count = len(self.calls())
        result = self.make('update', ok=False)
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(['db'], json.loads((self.home / 'docker.json').read_text())['running'])
        self.assertEqual([], list(self.state.parent.glob('backups/*')), 'no partial/published backup after failed rewrite')
        self.assertIn('stopped', result.stdout + result.stderr)
        self.assertEqual(before_receipt, (self.state / 'installed').read_bytes())
        self.assertFalse(any('migrate' in x['args'] for x in self.calls()[count:]))

    def assert_pinned_backup_mode(self, mode):
        image = 'registry.example/fmonitor@sha256:' + 'f' * 64
        self.data.update(FMONITOR_IMAGE_MODE=mode, FMONITOR_SOURCE_REVISION=self.a, FMONITOR_RUNTIME_IMAGE=image)
        self.write()
        if mode == 'local':
            self.call(['docker', 'build', '-t', image, '--build-arg', 'FMONITOR_SOURCE_REVISION=' + self.a], ok=True)
        self.make('deploy')
        old = self.receipt()
        self.make('backup')
        bundle = next(self.state.parent.glob('backups/backup-*/restore.env'))
        restored = dict(l.split('=', 1) for l in bundle.read_text().splitlines() if l and not l.startswith('#'))
        self.assertEqual(mode, restored['FMONITOR_IMAGE_MODE'], 'INTENDED_RED pinned recovery mode preserved')
        self.assertEqual(image, restored['FMONITOR_RUNTIME_IMAGE'])
        self.assertEqual(old['source'], restored['FMONITOR_SOURCE_REVISION'])
        self.assertEqual(self.before, self.cfg.read_bytes())

    def test_pinned_pull_recovery_mode_is_preserved(self):
        self.assert_pinned_backup_mode('pull')

    def test_pinned_local_recovery_mode_is_preserved(self):
        self.assert_pinned_backup_mode('local')


if __name__ == '__main__':
    unittest.main()
