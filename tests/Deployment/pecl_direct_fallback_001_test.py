#!/usr/bin/env python3
"""Public extension installer: primary/fallback, integrity and failure boundaries."""
import hashlib
import io
import json
import os
import pathlib
import shutil
import subprocess
import tarfile
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
INSTALL = ROOT / 'deploy/dependencies/install-sqlsrv'
REV = 'fd6d71d987dddcea413d9fc94234191cb82e4a28'
SHIM = r'''#!/usr/bin/env python3
import json,os,pathlib,shutil,subprocess,sys
name=pathlib.Path(sys.argv[0]).name;args=sys.argv[1:]
with open(os.environ['TRACE'],'a') as f:f.write(json.dumps([name,args,os.getcwd()])+'\n')
if name=='timeout':
 assert 0<float(args[0])<=180
 sys.exit(subprocess.call(args[1:]))
if name=='pecl':sys.exit(int(os.environ.get('PRIMARY_CODE','1')))
if name=='curl':
 if os.environ.get('DOWNLOAD_FAIL'):sys.exit(28)
 assert args[-1]=='https://codeload.github.com/microsoft/msphpsql/tar.gz/'+os.environ['REV'] or 'https://codeload.github.com/microsoft/msphpsql/tar.gz/'+os.environ['REV'] in args
 assert '-k' not in args and '--insecure' not in args
 assert '--connect-timeout' in args and '--max-time' in args
 out=args[args.index('-o')+1] if '-o' in args else args[args.index('--output')+1]
 shutil.copyfile(os.environ['ARCHIVE'],out);sys.exit(0)
if name=='phpize':
 if '--clean' not in args:
  p=pathlib.Path('configure');p.write_text('#!/bin/sh\ntest -f shared/core_sqlsrv.h\n');p.chmod(0o755)
 sys.exit(0)
if name=='make':sys.exit(1 if os.environ.get('BUILD_FAIL') else 0)
if name=='docker-php-ext-enable':
 assert set(args)=={'sqlsrv','pdo_sqlsrv'} and len(args)==2
 sys.exit(0)
if name=='php':
 assert '-r' in args
 code=args[args.index('-r')+1]
 assert all(token in code for token in ['extension_loaded','phpversion','sqlsrv','pdo_sqlsrv','sqlsrv_connect','getAvailableDrivers'])
 assert '5.13.0' in code or '5.13.0' in args or os.environ.get('SQLSRV_EXPECTED_VERSION')=='5.13.0'
 sys.exit(1 if os.environ.get('VERIFY_FAIL') else 0)
raise AssertionError(name)
'''


class Installer(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='pecl-fallback-unit-')
        self.addCleanup(self.temp.cleanup)
        self.root = pathlib.Path(self.temp.name).resolve()
        self.bin = self.root / 'bin'; self.bin.mkdir()
        self.work = self.root / 'work'; self.work.mkdir()
        self.trace = self.root / 'trace'
        self.archive = self.root / 'source.tgz'
        with tarfile.open(self.archive, 'w:gz') as tar:
            for name, body in {'shared/core_sqlsrv.h':'fixture', 'shared/version.h':'#define PHP_SQLSRV_VERSION "5.13.0"\n#define PHP_PDO_SQLSRV_VERSION "5.13.0"\n', 'sqlsrv/config.m4':'fixture', 'pdo_sqlsrv/config.m4':'fixture'}.items():
                data=body.encode();info=tarfile.TarInfo('msphpsql-'+REV+'/source/'+name);info.size=len(data);tar.addfile(info,io.BytesIO(data))
        self.sha = hashlib.sha256(self.archive.read_bytes()).hexdigest()
        for name in ['timeout','pecl','curl','phpize','make','docker-php-ext-enable','php']:
            p=self.bin/name;p.write_text(SHIM);p.chmod(0o755)
        self.env=dict(os.environ,PATH=str(self.bin)+os.pathsep+os.environ['PATH'],TMPDIR=str(self.work),TRACE=str(self.trace),ARCHIVE=str(self.archive),REV=REV)

    def run_installer(self, extra=(), _version="5.13.0", _revision=REV, _digest=None, **environment):
        self.assertTrue(INSTALL.is_file(),'PECL_DIRECT_FALLBACK_REQUIRED')
        env=self.env | environment
        result=subprocess.run(['bash',str(INSTALL),'--version',_version,'--source-revision',_revision,'--source-sha256',_digest or self.sha,*extra],env=env,capture_output=True,text=True,timeout=15)
        events=[json.loads(l) for l in self.trace.read_text().splitlines()] if self.trace.exists() else []
        self.assertEqual(list(self.work.iterdir()), [], 'private temporary work must be cleaned')
        return result, events

    def test_primary_success_does_not_download(self):
        r,e=self.run_installer(PRIMARY_CODE='0');self.assertEqual(r.returncode,0,r.stderr)
        self.assertTrue(any(n=='pecl' and 'sqlsrv-5.13.0' in a and 'pdo_sqlsrv-5.13.0' in a for n,a,_ in e))
        self.assertFalse(any(n in ['curl','phpize','make'] for n,_,_ in e))
        self.assertTrue(any(n=='php' for n,_,_ in e))
        self.assertTrue(any(n=='docker-php-ext-enable' and set(a)=={'sqlsrv','pdo_sqlsrv'} for n,a,_ in e))

    def test_primary_failure_and_timeout_build_both(self):
        for code in ['1','124']:
            with self.subTest(code=code):
                if self.trace.exists():self.trace.unlink()
                r,e=self.run_installer(PRIMARY_CODE=code);self.assertEqual(r.returncode,0,r.stdout+r.stderr)
                names=[n for n,_,_ in e];self.assertLess(names.index('pecl'),names.index('curl'))
                self.assertEqual({pathlib.Path(c).name for n,a,c in e if n=='make' and 'install' in a},{'sqlsrv','pdo_sqlsrv'})
                enable=names.index('docker-php-ext-enable');self.assertGreater(enable,max(i for i,(n,a,_) in enumerate(e) if n=='make' and 'install' in a))
                self.assertIn('php',names[enable+1:])

    def test_direct_skips_pecl(self):
        r,e=self.run_installer(['--mode','direct']);self.assertEqual(r.returncode,0,r.stderr)
        self.assertFalse(any(n=='pecl' for n,_,_ in e));self.assertTrue(any(n=='curl' for n,_,_ in e))

    def test_corrupt_download_rejected_before_build(self):
        self.archive.write_bytes(b'wrong archive bytes')
        r,e=self.run_installer();self.assertNotEqual(r.returncode,0)
        self.assertTrue(any(n=='curl' for n,_,_ in e));self.assertFalse(any(n in ['phpize','make','docker-php-ext-enable'] for n,_,_ in e))

    def test_download_and_build_failures_do_not_enable(self):
        for flag in ['DOWNLOAD_FAIL','BUILD_FAIL']:
            with self.subTest(flag=flag):
                if self.trace.exists():self.trace.unlink()
                r,e=self.run_installer(**{flag:'1'});self.assertNotEqual(r.returncode,0)
                self.assertFalse(any(n=='docker-php-ext-enable' for n,_,_ in e))

    def test_loaded_version_failure_propagates_for_both_paths(self):
        for primary in ['0','1']:
            with self.subTest(primary=primary):
                r,e=self.run_installer(PRIMARY_CODE=primary,VERIFY_FAIL='1');self.assertNotEqual(r.returncode,0)
                self.assertTrue(any(n=='php' for n,_,_ in e))

    def test_invalid_duplicate_unknown_inputs_have_no_effect(self):
        for extra in [['--mode','invalid'],['--source-revision','bad'],['--version','5.13.1'],['--bogus'],['--mode','direct','--mode','auto']]:
            with self.subTest(extra=extra):
                if self.trace.exists():self.trace.unlink()
                r,e=self.run_installer(extra);self.assertNotEqual(r.returncode,0);self.assertEqual(e,[])

    def test_malformed_pin_values_have_no_effect(self):
        for values in [{'_version':'bad'},{'_revision':'../bad'},{'_digest':'not-a-sha256'}]:
            with self.subTest(values=values):
                if self.trace.exists():self.trace.unlink()
                r,e=self.run_installer(**values);self.assertNotEqual(r.returncode,0);self.assertEqual(e,[])

    def test_runtime_uses_pinned_public_installer(self):
        template=(ROOT/'tools/delivery/Dockerfile.runtime.in').read_text()
        self.assertIn('install-sqlsrv',template,'PECL_DIRECT_FALLBACK_REQUIRED')
        self.assertIn('--source-revision',template);self.assertIn('--source-sha256',template)
        self.assertNotIn('&& pecl install',template)
        pins=dict(l.split('=',1) for l in (ROOT/'tools/delivery/dependencies.env').read_text().splitlines() if l and not l.startswith('#'))
        self.assertEqual(pins['PDO_SQLSRV_VERSION'],'5.13.0')
        self.assertEqual(pins['SQLSRV_SOURCE_REVISION'],REV)
        self.assertEqual(pins['SQLSRV_SOURCE_SHA256'],'7ea3eeef0d615c884628285a647f9cb29db4e206be2cd5b418c10360085cf04a')
        rendered=(ROOT/'deploy/runtime/Dockerfile').read_text()
        for flag,key in [('version','PDO_SQLSRV_VERSION'),('source-revision','SQLSRV_SOURCE_REVISION'),('source-sha256','SQLSRV_SOURCE_SHA256')]:
            self.assertIn('--'+flag+' @'+key+'@',template)
            self.assertIn('--'+flag+' '+pins[key],rendered)
            self.assertNotIn('ARG '+key,template);self.assertNotIn('ENV '+key,template)
        # The real generated-source checker rejects a modified rendered argument.
        fixture=self.root/'renderer';fixture.mkdir()
        for source in ['tools/delivery/render-dependencies.py','tools/delivery/dependencies.env','composer.lock','composer.json']:
            target=fixture/source;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/source,target)
        for source in (ROOT/'tools/delivery').glob('*.in'):
            shutil.copyfile(source,fixture/'tools/delivery'/source.name)
        for target in ['deploy/runtime','tools/verification']:(fixture/target).mkdir(parents=True,exist_ok=True)
        r=subprocess.run(['python3','tools/delivery/render-dependencies.py'],cwd=fixture,capture_output=True,text=True);self.assertEqual(r.returncode,0,r.stderr)
        generated=fixture/'deploy/runtime/Dockerfile';generated.write_text(generated.read_text().replace('--source-revision '+REV,'--source-revision '+'0'*40))
        r=subprocess.run(['python3','tools/delivery/render-dependencies.py','--check'],cwd=fixture,capture_output=True,text=True);self.assertNotEqual(r.returncode,0)

        r=subprocess.run(['python3','tools/delivery/render-dependencies.py','--check'],cwd=ROOT,capture_output=True,text=True)
        self.assertEqual(r.returncode,0,r.stderr)


if __name__=='__main__':unittest.main()
