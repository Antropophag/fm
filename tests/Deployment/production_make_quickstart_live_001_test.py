#!/usr/bin/env python3
"""Fresh clone, copied .env0644, make deploy; actual isolated Docker acceptance."""
import hashlib
import html
import http.cookiejar
import json
import os
import pathlib
import re
import secrets
import socket
import stat
import subprocess
import tempfile
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]


def run(args, cwd=ROOT, env=None, timeout=120, ok=True):
    r = subprocess.run(args, cwd=cwd, env=env, capture_output=True, text=True, timeout=timeout)
    if ok:
        assert r.returncode == 0, 'command failed: ' + str(args[:3]) + '\n' + r.stdout[-1500:] + '\n' + r.stderr[-1500:]
    return r


def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def login(base, email, password):
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    body = opener.open(base + '/pilot/login', timeout=15).read().decode()
    for fields in [{'email': email}, {'email': email, 'password': password}]:
        fields['_csrf'] = html.unescape(re.search(r'name="_csrf" value="([^"]+)"', body).group(1))
        body = opener.open(base + '/pilot/login', urllib.parse.urlencode(fields).encode(), timeout=15).read().decode()
    result = opener.open(base + '/pilot/admin/users', timeout=15)
    assert result.status == 200 and '/pilot/login' not in result.url
    return opener


run(['docker', 'info', '--format', '{{.ServerVersion}}'], timeout=20)
with tempfile.TemporaryDirectory(prefix='fm2-quick-live-') as raw:
    temp = pathlib.Path(raw).resolve()
    clone = temp / 'checkout'
    revision = run(['git', 'rev-parse', 'HEAD']).stdout.strip()
    # Prepare the upstream main that GitLab will receive after checks; the user's
    # clone below performs no checkout repair and does not share local objects.
    upstream = temp / 'upstream.git'
    run(['git', 'clone', '--quiet', '--bare', str(ROOT), str(upstream)])
    run(['git', '--git-dir', str(upstream), 'update-ref', 'refs/heads/main', revision])
    run(['git', '--git-dir', str(upstream), 'symbolic-ref', 'HEAD', 'refs/heads/main'])
    run(['git', 'clone', '--quiet', upstream.as_uri(), str(clone)])
    assert run(['git', 'symbolic-ref', '--short', 'HEAD'], cwd=clone).stdout.strip() == 'main'
    assert run(['git', 'rev-parse', 'HEAD'], cwd=clone).stdout.strip() == revision
    user_home = temp / 'operator'
    user_home.mkdir(mode=0o700)
    env = {k: v for k, v in os.environ.items() if not k.startswith(('FMONITOR_', 'COMPOSE_'))}
    env['DOCKER_CONFIG'] = os.environ.get('DOCKER_CONFIG', str(pathlib.Path.home() / '.docker'))
    env['HOME'] = str(user_home)
    seed_home = temp / 'seed'
    seed_home.mkdir(mode=0o700)
    seed = seed_home / 'handoff.env'
    # This is preparation of the transferable file, not a command required on the user's machine.
    run(['bash', 'deploy/production/manage', 'env-init', '--env-file', str(seed)], cwd=clone, env=env | {'HOME': str(seed_home)})
    data = dict(l.split('=', 1) for l in seed.read_text().splitlines() if l and not l.startswith('#'))
    token = secrets.token_hex(5)
    project = 'fm2-prod-quick-live-' + token
    image = 'fmonitor2-runtime:quick-live-' + token
    chosen_port = port()
    data.update(COMPOSE_PROJECT_NAME=project, FMONITOR_RUNTIME_IMAGE=image, FMONITOR_IMAGE_MODE='build',
                FMONITOR_SOURCE_REVISION=revision, FMONITOR_DEPLOY_STATE_DIR='auto', FMONITOR_BACKUP_ROOT='auto',
                FMONITOR_HTTP_PORT=str(chosen_port), FMONITOR_TRUSTED_REQUEST_HOST='127.0.0.1:' + str(chosen_port),
                FMONITOR_TRUSTED_REQUEST_SCHEME='http', FMONITOR_ALLOW_HTTP='true',
                FMONITOR_PUBLIC_BASE_URL='http://127.0.0.1:' + str(chosen_port),
                FMONITOR_INITIAL_OWNER_EMAIL='quickstart@shlz.ru', FMONITOR_JOBS_ENABLED='false')
    cfg = clone / '.env'
    cfg.write_text(''.join(k + '=' + v + '\n' for k, v in data.items()))
    cfg.chmod(0o644)
    before = cfg.read_bytes()
    identity = tuple(getattr(cfg.stat(), k) for k in ['st_dev', 'st_ino', 'st_uid', 'st_nlink'])
    assert not (user_home / '.fmonitor2-production').exists()

    def make(verb, timeout=300):
        return run(['make', '--no-print-directory', verb], cwd=clone, env=env, timeout=timeout)

    compose_env = env | data
    compose_args = ['docker', 'compose', '--env-file', '/dev/null', '-p', project, '-f', str(clone / 'deploy/production/compose.yaml')]
    try:
        result = make('deploy', timeout=1200)
        assert stat.S_IMODE(cfg.stat().st_mode) == 0o600
        assert cfg.read_bytes() == before, 'copied env contents must not be regenerated or rewritten'
        assert tuple(getattr(cfg.stat(), k) for k in ['st_dev', 'st_ino', 'st_uid', 'st_nlink']) == identity
        assert data['FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD'] not in result.stdout + result.stderr
        private = user_home / '.fmonitor2-production' / project
        for path in [private, private / 'state', private / 'backups']:
            assert path.is_dir() and stat.S_IMODE(path.stat().st_mode) == 0o700
        make('status')
        opener = login(data['FMONITOR_PUBLIC_BASE_URL'], data['FMONITOR_INITIAL_OWNER_EMAIL'], data['FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD'])
        sentinel = 'quickstart-' + token
        run(compose_args + ['exec', '-T', 'php', 'php', '-r',
                           'umask(0077);file_put_contents(getenv("FMONITOR_SESSION_STATE_ROOT")."/quickstart-proof",' + json.dumps(sentinel) + ');'],
            cwd=clone, env=compose_env)
        make('deploy')
        make('stop')
        make('start')
        make('status')
        observed = run(compose_args + ['exec', '-T', 'php', 'php', '-r',
                                      'echo file_get_contents(getenv("FMONITOR_SESSION_STATE_ROOT")."/quickstart-proof");'],
                       cwd=clone, env=compose_env).stdout
        assert observed == sentinel and cfg.read_bytes() == before
        assert tuple(getattr(cfg.stat(), k) for k in ['st_dev', 'st_ino', 'st_uid', 'st_nlink']) == identity
        response = opener.open(data['FMONITOR_PUBLIC_BASE_URL'] + '/pilot/admin/users', timeout=15)
        assert response.status == 200 and '/pilot/login' not in response.url
        running = run(compose_args + ['ps', '--status', 'running', '--services'], cwd=clone, env=compose_env).stdout.splitlines()
        assert not {'jobs-worker', 'jobs-scheduler'}.intersection(running)
        receipt = (private / 'state' / 'installed').read_text()
        assert 'config_hash=' + hashlib.sha256(before).hexdigest() in receipt
        assert revision in receipt
        print('PASS PRODUCTION-MAKE-QUICKSTART-001 fresh clone/copied env/plain make/login/replay/stop-start')
    finally:
        assert project.startswith('fm2-prod-quick-live-') and token in project
        run(compose_args + ['--profile', 'deployment', '--profile', 'jobs', 'down', '--volumes', '--remove-orphans'], cwd=clone, env=compose_env, ok=False)
        run(['docker', 'image', 'rm', image], ok=False)
