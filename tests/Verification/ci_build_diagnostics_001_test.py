#!/usr/bin/env python3
"""PRODUCTION-RUNNER-REPAIR-001 C01,C02: actual executable boundary and rendered CI."""
import importlib.util
import json
import os
import pathlib
import subprocess
import tempfile
import time
import signal
import unittest
from concurrent.futures import ThreadPoolExecutor
import yaml

ROOT = pathlib.Path(__file__).resolve().parents[2]
WRAPPER = ROOT / 'tools/verification/docker-diagnostics/docker'


class BuildDiagnostics(unittest.TestCase):
    def test_workflow_budget_and_failure_only_artifact(self):
        job = yaml.safe_load((ROOT / '.github/workflows/quality-graph.yml').read_text())['jobs']['e2e']
        self.assertEqual(30, job['timeout-minutes'], 'INTENDED_RED: E2E setup consumes the 20-minute budget')
        self.assertEqual("needs.plan.outputs.full == 'true'", job['if'])
        self.assertNotIn('continue-on-error', job)
        self.assertEqual(1, sum(s.get('run') == 'make test CATEGORY=e2e' for s in job['steps']))
        uploads = [s for s in job['steps'] if s.get('uses', '').startswith('actions/upload-artifact@')]
        self.assertEqual(1, len(uploads))
        step = uploads[0]
        self.assertEqual('actions/upload-artifact@b7c566a772e6b6bfb58ed0dc250532a479d7789f', step['uses'])
        self.assertEqual('failure() || cancelled()', step['if'])
        self.assertEqual('ignore', step['with']['if-no-files-found'])
        self.assertEqual(7, step['with']['retention-days'])
        self.assertIn('fmonitor-e2e-diagnostics', step['with']['path'])
        self.assertNotIn('**', step['with']['path'])
        self.assertTrue(any('docker-diagnostics' in s.get('run', '') and 'GITHUB_PATH' in s['run'] for s in job['steps']))

    def test_renderer_restores_e2e_budget(self):
        spec = importlib.util.spec_from_file_location('render', ROOT / 'tools/delivery/render-current-quality-graph.py')
        render = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(render)
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            for path in render.BOUNDARIES:
                p = root / path
                p.parent.mkdir(parents=True, exist_ok=True)
                p.write_bytes((ROOT / path).read_bytes())
            path = root / '.github/workflows/quality-graph.yml'
            text = path.read_text(); start = text.index('\n  e2e:'); end = text.index('\n  governance:', start)
            import re
            block = re.sub('timeout-minutes: [0-9]+', 'timeout-minutes: 1', text[start:end])
            path.write_text(text[:start] + block + text[end:])
            render.ROOT = root
            actual = yaml.safe_load(render.expected()['.github/workflows/quality-graph.yml'])['jobs']['e2e']
            self.assertEqual(30, actual['timeout-minutes'], 'INTENDED_RED: workflow is its own budget authority')

    def test_full_streams_exit_status_and_nonbuild_passthrough(self):
        self.assertTrue(WRAPPER.is_file(), 'INTENDED_RED: Docker build diagnostics boundary missing')
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            real = root / 'real-docker'
            real.write_text('#!/usr/bin/env python3\nimport json,os,pathlib,sys\n'
                            'with pathlib.Path(os.environ["TRACE"]).open("a") as f: f.write(json.dumps(sys.argv[1:])+"\\n")\n'
                            'sys.stdout.write("FIRST\\n"+"x"*10000+"\\nLAST\\n");sys.stderr.write("ERROR-FIRST\\n"+"y"*10000+"\\nERROR-LAST\\n")\n'
                            'sys.exit(int(os.environ.get("EXIT","17")))\n')
            real.chmod(0o755)
            logs = root / 'logs'
            env = dict(os.environ, FM_CI_REAL_DOCKER=str(real), FM_CI_DOCKER_LOG_DIR=str(logs), TRACE=str(root / 'trace'))
            def invoke(args, code=17):
                return subprocess.run([str(WRAPPER)] + args, env=dict(env, EXIT=str(code)), capture_output=True, timeout=10)
            for args in [['build', '--quiet', '.'], ['buildx', 'build', '.'], ['compose', '--progress', 'quiet', '-p', 'fixture', 'build']]:
                r = invoke(args)
                self.assertEqual(17, r.returncode)
                self.assertTrue(r.stdout.startswith(b'FIRST\n') and r.stdout.endswith(b'LAST\n'))
                self.assertTrue(r.stderr.startswith(b'ERROR-FIRST\n') and r.stderr.endswith(b'ERROR-LAST\n'))
            content = b'\n'.join(p.read_bytes() for p in logs.iterdir() if p.is_file())
            self.assertIn(b'FIRST\n' + b'x' * 10000 + b'\nLAST', content)
            self.assertIn(b'ERROR-FIRST\n' + b'y' * 10000 + b'\nERROR-LAST', content)
            stdout_logs = list(logs.glob('*.stdout.log'))
            stderr_logs = list(logs.glob('*.stderr.log'))
            self.assertEqual(3, len(stdout_logs))
            self.assertEqual(3, len(stderr_logs))
            for p in stdout_logs:
                self.assertEqual(b'FIRST\n' + b'x' * 10000 + b'\nLAST\n', p.read_bytes())
            for p in stderr_logs:
                self.assertEqual(b'ERROR-FIRST\n' + b'y' * 10000 + b'\nERROR-LAST\n', p.read_bytes())
            for p in logs.iterdir():
                self.assertEqual(0o600, p.stat().st_mode & 0o777)
            previous = {p.name: p.read_bytes() for p in logs.iterdir()}
            self.assertEqual(0, invoke(['build', '.'], 0).returncode)
            self.assertEqual(17, invoke(['compose', 'run', 'php', 'secret-argument']).returncode)
            self.assertEqual(previous, {p.name: p.read_bytes() for p in logs.iterdir()})
            calls = [json.loads(x) for x in (root / 'trace').read_text().splitlines()]
            self.assertEqual(5, len(calls), 'each Docker invocation executes once')
            self.assertEqual(['compose', 'run', 'php', 'secret-argument'], calls[-1])
            self.assertNotIn('quiet', calls[2])
            self.assertNotIn('--quiet', calls[0])
            for n, args in enumerate(calls[:4]):
                self.assertTrue('--progress=plain' in args or
                                any(args[i:i+2] == ['--progress', 'plain'] for i in range(len(args))), args)
                progress = args.index('--progress=plain') if '--progress=plain' in args else args.index('--progress')
                if n == 2:
                    self.assertLess(progress, args.index('build'), 'Compose progress is a global option')
                else:
                    self.assertGreater(progress, args.index('build'))
                    self.assertLess(progress, args.index('.'))
            for args in [['image', 'inspect', 'build'], ['compose', 'exec', 'php', 'echo', 'build']]:
                self.assertEqual(17, invoke(args).returncode)
            self.assertEqual(previous, {p.name: p.read_bytes() for p in logs.iterdir()})
            with ThreadPoolExecutor(max_workers=2) as pool:
                results = list(pool.map(lambda _: invoke(['build', '.']), range(2)))
            self.assertEqual([17, 17], [r.returncode for r in results])
            self.assertEqual(5, len(list(logs.glob('*.stdout.log'))))
            self.assertEqual(5, len(list(logs.glob('*.stderr.log'))))
            self.assertTrue(all((logs / name).read_bytes() == data for name, data in previous.items()))

    def test_capture_failure_preserves_docker_exit(self):
        self.assertTrue(WRAPPER.is_file(), 'INTENDED_RED: Docker diagnostic boundary missing')
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw); real = root / 'docker'; logpath = root / 'not-directory'
            real.write_text('#!/bin/sh\necho original-output\nexit 23\n'); real.chmod(0o755)
            logpath.write_text('keep')
            env = dict(os.environ, FM_CI_REAL_DOCKER=str(real), FM_CI_DOCKER_LOG_DIR=str(logpath))
            r = subprocess.run([str(WRAPPER), 'build', '.'], env=env, capture_output=True, timeout=10)
            self.assertEqual(23, r.returncode)
            self.assertIn(b'original-output', r.stdout)
            self.assertEqual('keep', logpath.read_text())

    def test_interrupted_build_retains_partial_streams(self):
        self.assertTrue(WRAPPER.is_file(), 'INTENDED_RED: Docker diagnostic boundary missing')
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw); real = root / 'real-docker'; logs = root / 'logs'
            real.write_text('#!/usr/bin/env python3\nimport os,pathlib,sys,time\n'
                            'print("partial-out",flush=True);print("partial-error",file=sys.stderr,flush=True)\n'
                            'pathlib.Path(os.environ["READY"]).touch()\ntime.sleep(30)\n')
            real.chmod(0o755)
            env = dict(os.environ, FM_CI_REAL_DOCKER=str(real), FM_CI_DOCKER_LOG_DIR=str(logs), READY=str(root / 'ready'))
            p = subprocess.Popen([str(WRAPPER), 'build', '.'], env=env, stdout=subprocess.PIPE,
                                 stderr=subprocess.PIPE, start_new_session=True)
            try:
                deadline = time.monotonic() + 5
                while not (root / 'ready').exists() and p.poll() is None and time.monotonic() < deadline:
                    time.sleep(.02)
                self.assertTrue((root / 'ready').exists())
                p.terminate()
                p.communicate(timeout=5)
                self.assertIn(p.returncode, (-signal.SIGTERM, 128 + signal.SIGTERM))
                self.assertIn(b'partial-out\n', b''.join(x.read_bytes() for x in logs.glob('*.stdout.log')))
                self.assertIn(b'partial-error\n', b''.join(x.read_bytes() for x in logs.glob('*.stderr.log')))
            finally:
                if p.poll() is None:
                    os.killpg(p.pid, signal.SIGKILL)
                    p.communicate(timeout=5)


if __name__ == '__main__':
    unittest.main()
