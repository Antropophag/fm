#!/usr/bin/env python3
"""PRODUCTION-HANDOFF-001 H08: execute the GitLab job with isolated adapters."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

import yaml

ROOT = Path(__file__).resolve().parents[2]


class ProductionRunner(unittest.TestCase):
    def config(self):
        path = ROOT / '.gitlab-ci.yml'
        self.assertTrue(path.is_file(), 'INTENDED_RED: corporate GitLab deployment job is missing')
        value = yaml.safe_load(path.read_text())
        self.assertEqual({'stages', 'deploy_fm2_production'}, set(value))
        self.assertEqual(['deploy'], value['stages'])
        job = value['deploy_fm2_production']
        self.assertEqual('deploy', job['stage'])
        self.assertEqual(['web-03'], job['tags'])
        self.assertEqual(['main'], job['only'])
        self.assertEqual('web-03-fm2', job['resource_group'])
        self.assertIs(False, job['interruptible'])
        self.assertFalse(job.get('allow_failure', False))
        self.assertNotIn('after_script', job, 'deployment steps must preserve failure status')
        self.assertNotIn('before_script', job, 'preflight and deployment share one checked script')
        self.assertEqual('cd /opt/fm2', job['script'][0])
        policy = json.loads((ROOT / '.quality-graph/verification-policy.json').read_text())
        self.assertIn('.gitlab-ci.yml', next(b for b in policy['boundaries'] if b['name'] == 'delivery-policy')['patterns'])
        return job

    def run_job(self, mode='success'):
        job = self.config()
        with tempfile.TemporaryDirectory(prefix='fm-runner-') as temp:
            root = Path(temp)
            bin_dir = root / 'bin'
            bin_dir.mkdir()
            env_file = root / '.env'
            env_file.write_text('PRIVATE_SENTINEL=never-log-or-rewrite\n')
            before = env_file.read_bytes()
            adapter = '''#!/usr/bin/env python3
import json,os,pathlib,sys
name=pathlib.Path(sys.argv[0]).name;args=sys.argv[1:];root=pathlib.Path(os.environ['FM_RUNNER_FIXTURE']);trace=root/'trace'
prior=[json.loads(x) for x in trace.read_text().splitlines()] if trace.exists() else []
with trace.open('a') as out:out.write(json.dumps([name,*args])+'\\n')
mode=os.environ['FM_RUNNER_MODE']
if name=='git':
 if args==['branch','--show-current']:print('feature' if mode=='branch' else 'main')
 elif args==['pull','--ff-only']:sys.exit(13 if mode=='pull' else 0)
 elif args==['rev-parse','HEAD']:print('a'*40 if mode=='source' else os.environ['CI_COMMIT_SHA'])
 else:sys.exit(91)
elif args==['status']:
 count=sum(x==['make','status'] for x in prior)
 sys.exit(12 if (mode=='preflight' and count==0) or (mode=='postflight' and count==1) else 0)
elif args==['update']:sys.exit(14 if mode=='update' else 0)
else:sys.exit(92)
'''
            for name in ['git', 'make']:
                p = bin_dir / name
                p.write_text(adapter)
                p.chmod(0o700)
            env = os.environ.copy()
            env.update(PATH=str(bin_dir) + os.pathsep + env['PATH'],
                       FM_RUNNER_FIXTURE=str(root), FM_RUNNER_MODE=mode,
                       CI_COMMIT_SHA='b' * 40)
            # The real job's fixed server cwd is mapped to an isolated temporary checkout.
            script = '\n'.join(['cd "$FM_RUNNER_FIXTURE"', *job['script'][1:]])
            result = subprocess.run(['bash', '-eo', 'pipefail', '-c', script], env=env,
                                    capture_output=True, text=True, timeout=10)
            calls = [json.loads(x) for x in (root / 'trace').read_text().splitlines()]
            self.assertEqual(before, env_file.read_bytes())
            self.assertNotIn('never-log-or-rewrite', result.stdout + result.stderr)
            return result.returncode, calls

    def test_successful_update_uses_preflight_exact_source_and_postflight(self):
        code, calls = self.run_job()
        self.assertEqual(0, code)
        self.assertEqual([['git', 'branch', '--show-current'], ['make', 'status'],
                          ['git', 'pull', '--ff-only'], ['git', 'rev-parse', 'HEAD'],
                          ['make', 'update'], ['make', 'status']], calls)

    def test_wrong_branch_stops_before_preflight_and_pull(self):
        code, calls = self.run_job('branch')
        self.assertNotEqual(0, code)
        self.assertEqual([['git', 'branch', '--show-current']], calls)

    def test_preflight_failure_does_not_pull_or_update(self):
        code, calls = self.run_job('preflight')
        self.assertNotEqual(0, code)
        self.assertEqual([['git', 'branch', '--show-current'], ['make', 'status']], calls)

    def test_pull_failure_does_not_update(self):
        code, calls = self.run_job('pull')
        self.assertNotEqual(0, code)
        self.assertEqual(['git', 'pull', '--ff-only'], calls[-1])
        self.assertNotIn(['make', 'update'], calls)

    def test_superseded_source_does_not_update(self):
        code, calls = self.run_job('source')
        self.assertNotEqual(0, code)
        self.assertEqual(['git', 'rev-parse', 'HEAD'], calls[-1])
        self.assertNotIn(['make', 'update'], calls)

    def test_update_failure_stays_failed_without_start_or_retry(self):
        code, calls = self.run_job('update')
        self.assertNotEqual(0, code)
        self.assertEqual(['make', 'update'], calls[-1])
        self.assertEqual(1, calls.count(['make', 'update']))

    def test_postflight_failure_is_not_reported_successful(self):
        code, calls = self.run_job('postflight')
        self.assertNotEqual(0, code)
        self.assertEqual(['make', 'status'], calls[-1])
        self.assertEqual(1, calls.count(['make', 'update']))


if __name__ == '__main__':
    unittest.main()
