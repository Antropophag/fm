#!/usr/bin/env python3
"""External Docker process fixture; never contacts a Docker daemon or network."""
import hashlib
import json
import os
import pathlib
import sys

a = sys.argv[1:]
root = pathlib.Path(os.environ['HANDOFF_FIXTURE'])
state_path = root / 'docker.json'
s = json.loads(state_path.read_text()) if state_path.exists() else {'images': {}, 'running': [], 'resources': False}
with (root / 'trace.jsonl').open('a') as out:
    out.write(json.dumps({'args': a, 'source': os.environ.get('FMONITOR_SOURCE_REVISION'),
                          'image': os.environ.get('FMONITOR_RUNTIME_IMAGE')}) + '\n')


def done(code=0, value=None):
    state_path.write_text(json.dumps(s))
    if value is not None:
        print(value)
    raise SystemExit(code)


if a[0] == 'info' or a[:2] == ['compose', 'version']:
    done(value='fixture')
if a[0] == 'build':
    tag = a[a.index('-t') + 1]
    revision = next(v.split('=', 1)[1] for v in a if v.startswith('FMONITOR_SOURCE_REVISION='))
    identity = 'sha256:' + hashlib.sha256(tag.encode()).hexdigest()
    s['images'][tag] = s['images'][identity] = {'source': revision, 'id': identity}
    done()
if a[0] == 'pull':
    ref = a[1]
    identity = 'sha256:' + hashlib.sha256(ref.encode()).hexdigest()
    s['images'][ref] = s['images'][identity] = {'source': os.environ['FMONITOR_SOURCE_REVISION'], 'id': identity}
    done()
if a[:2] == ['image', 'inspect']:
    ref = a[2]
    if ref.startswith('mariadb:'):
        value = {'source': '', 'id': 'sha256:' + ('e' if os.environ.get('HANDOFF_DB_DRIFT') else 'd') * 64}
    elif ref in ('sha256:' + 'd' * 64, 'sha256:' + 'e' * 64):
        value = {'source': '', 'id': ref}
    else:
        value = s['images'].get(ref)
    if value is None:
        done(1)
    fmt = a[a.index('--format') + 1] if '--format' in a else ''
    done(value=value['source'] if 'revision' in fmt else 'linux/amd64' if '.Os' in fmt else value['id'])
if a[0] in ('volume', 'network'):
    done(0 if s['resources'] else 1, a[0] + ':' + a[2] if s['resources'] else None)
if a[0] == 'ps':
    done(value='')
if a[0] == 'inspect':
    done(value='running:healthy')
if a[0] == 'run':
    if 'bin/fmonitor2-runtime-recovery.php' in a and 'backup' in a:
        if os.environ.get('HANDOFF_FAIL_BACKUP'):
            done(70)
        mount = next(x for x in a if 'target=/backup' in x and 'type=bind' in x)
        directory = pathlib.Path(mount.split('source=', 1)[1].split(',target=', 1)[0]) / 'runtime'
        directory.mkdir()
        manifest = {'sourceCommit': a[a.index('--source-commit') + 1],
                    'imageReference': a[a.index('--image-reference') + 1],
                    'imageId': a[a.index('--image-id') + 1]}
        (directory / 'manifest.json').write_text(json.dumps(manifest))
    done()
if a[0] == 'compose':
    op = next((x for x in a if x in ('up', 'stop', 'ps', 'run', 'exec', 'logs')), None)
    tail = a[a.index(op) + 1:] if op else []
    if op == 'up':
        services = [x for x in tail if x in ('db', 'php', 'web', 'jobs-worker', 'jobs-scheduler')]
        s['resources'] = True
        s['running'] = sorted(set(s['running']) | set(services))
        done()
    if op == 'stop':
        s['running'] = [x for x in s['running'] if x not in tail]
        done()
    if op == 'ps':
        done(value='\n'.join(s['running']) if '--services' in tail else 'fixture-container' if '-q' in tail else '')
    if op in ('run', 'exec'):
        stages = {'erp-equipment-facts-sync/probe': 'erp', 'workforce-sync/run': 'workforce',
                  'bitrix-order-document-links-sync/run': 'documents'}
        for command, stage in stages.items():
            if command in tail:
                if os.environ.get('HANDOFF_FAIL_INTEGRATION') == stage:
                    print('private-credential-do-not-print https://private.example/rest/1/secret/', file=sys.stderr)
                    done(70, '{"status":"failed"}')
                done(value='{"status":"completed","count":0,"matched":0}')
        done()
    if op in (None, 'logs'):
        done()
done(99, 'UNSUPPORTED_DOCKER_FIXTURE_CALL')
