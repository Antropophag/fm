#!/usr/bin/env python3
"""Real compilation with PECL unreachable; no SQL Server or external credentials."""
import pathlib
import secrets
import shutil
import subprocess
import tempfile

ROOT=pathlib.Path(__file__).resolve().parents[2]
script=ROOT/'deploy/dependencies/install-sqlsrv'
assert script.is_file(),'PECL_DIRECT_FALLBACK_REQUIRED'
pins=dict(l.split('=',1) for l in (ROOT/'tools/delivery/dependencies.env').read_text().splitlines() if l and not l.startswith('#'))
# Source archive identity is an explicit approved runtime input, not a test-only mirror.
revision=pins['SQLSRV_SOURCE_REVISION'];digest=pins['SQLSRV_SOURCE_SHA256'];version=pins['PDO_SQLSRV_VERSION']
image='fmonitor2-pecl-fallback-test:'+secrets.token_hex(6)
with tempfile.TemporaryDirectory(prefix='pecl-fallback-live-') as tmp:
    folder=pathlib.Path(tmp)
    shutil.copyfile(script,folder/'install-sqlsrv')
    (folder/'Dockerfile').write_text('FROM '+pins['PHP_FPM_IMAGE']+'\nRUN apt-get update && apt-get install -y --no-install-recommends unixodbc-dev ca-certificates curl && rm -rf /var/lib/apt/lists/*\nCOPY install-sqlsrv /usr/local/bin/install-sqlsrv\nARG FALLBACK_PROBE\nRUN test -n \"$FALLBACK_PROBE\" && bash /usr/local/bin/install-sqlsrv --version '+version+' --source-revision '+revision+' --source-sha256 '+digest+'\n')
    try:
        r=subprocess.run(['docker','build','--progress=plain','--build-arg','FALLBACK_PROBE='+secrets.token_hex(8),'--add-host','pecl.php.net:127.0.0.1','-t',image,str(folder)],capture_output=True,text=True,timeout=900)
        assert r.returncode==0,r.stdout[-2500:]+r.stderr[-4500:]
        assert 'SQLSRV_SOURCE_FALLBACK' in r.stdout+r.stderr, 'fallback must actually execute, not be cached'
        # Inspect the actual installed extension version, entrypoints and PDO registration.
        code='foreach (["sqlsrv","pdo_sqlsrv"] as $e) {if (!extension_loaded($e) || phpversion($e)!=="'+version+'") exit(2);} if (!function_exists("sqlsrv_connect") || !in_array("sqlsrv",PDO::getAvailableDrivers(),true)) exit(3); echo "PECL_FALLBACK_LIVE_PASS\\n";'
        r=subprocess.run(['docker','run','--rm','--network','none',image,'php','-r',code],capture_output=True,text=True,timeout=30)
        assert r.returncode==0 and 'PECL_FALLBACK_LIVE_PASS' in r.stdout,r.stdout+r.stderr
        print('PASS PECL-DIRECT-FALLBACK-001 blocked PECL, verified source build, both exact driver versions')
    finally:
        subprocess.run(['docker','image','rm',image],capture_output=True)
