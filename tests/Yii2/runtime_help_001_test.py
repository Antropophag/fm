#!/usr/bin/env python3
"""Existing guide via real Yii HTTP routes, without database or external services."""
import hashlib
import http.client
from html.parser import HTMLParser
import os
import pathlib
import shutil
import socket
import subprocess
import tempfile
import time
import unittest

ROOT=pathlib.Path(__file__).resolve().parents[2]

class Links(HTMLParser):
    def __init__(self):super().__init__();self.paths=set()
    def handle_starttag(self,tag,attrs):
        for key,value in attrs:
            if key in ['src','href'] and value and value.startswith('/help/'):
                self.paths.add(value)

class Help(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        assert (ROOT/'vendor/autoload.php').is_file(),'SETUP_FAILURE: install locked Composer dependencies'
        cls.tmp=tempfile.TemporaryDirectory(prefix='fm2-help-http-')
        cls.private=pathlib.Path(cls.tmp.name).resolve()
        cls.site=cls.private/'site';cls.site.mkdir()
        # Exact application/config/public bytes in a private fixture permit replacing
        # an ALLOWED screenshot with a symlink without mutating repository files.
        for folder in ['app','config','public']:shutil.copytree(ROOT/folder,cls.site/folder)
        (cls.site/'vendor').symlink_to(ROOT/'vendor',target_is_directory=True)
        with socket.socket() as sock:sock.bind(('127.0.0.1',0));cls.port=sock.getsockname()[1]
        cls.host='127.0.0.1:'+str(cls.port)
        env={k:v for k,v in os.environ.items() if not k.startswith('FMONITOR_')}
        env.update(FMONITOR_TRUSTED_REQUEST_HOST=cls.host,FMONITOR_TRUSTED_REQUEST_SCHEME='http',FMONITOR_YII_COOKIE_VALIDATION_KEY='synthetic-help-cookie-key-'*3,FMONITOR_YII_IDENTITY_KEY='synthetic-help-identity-key-'*3,FMONITOR_YII_RUNTIME_PATH=str(cls.private/'runtime'),FMONITOR_YII_SESSION_PATH=str(cls.private))
        cls.log=(cls.private/'http.log').open('wb')
        cls.server=subprocess.Popen(['php','-d','display_errors=0','-S',cls.host,'-t',str(cls.site/'public'),str(cls.site/'public/yii.php')],cwd=cls.site,env=env,stdout=cls.log,stderr=cls.log)
        for _ in range(80):
            if cls.server.poll() is not None:raise RuntimeError('SETUP_FAILURE: help HTTP fixture exited')
            try:
                with socket.create_connection(('127.0.0.1',cls.port),timeout=.1):break
            except OSError:time.sleep(.05)
        else:raise RuntimeError('SETUP_FAILURE: help HTTP fixture did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate();cls.server.wait(timeout=10);cls.log.close();cls.tmp.cleanup()

    def request(self,path,method='GET',host=None):
        conn=http.client.HTTPConnection('127.0.0.1',self.port,timeout=10)
        try:
            conn.request(method,path,headers={'Host':host or self.host})
            response=conn.getresponse();return response.status,{k.lower():v for k,v in response.getheaders()},response.read()
        finally:conn.close()

    def test_page_aliases_and_head(self):
        expected=(ROOT/'public/help/index.html').read_bytes()
        for path in ['/help/','/help','/help/index.html']:
            with self.subTest(path=path):
                status,headers,body=self.request(path)
                self.assertEqual(status,200,'RUNTIME_HELP_ROUTE_REQUIRED '+path)
                self.assertEqual(body,expected);self.assertTrue(headers['content-type'].startswith('text/html'))
                self.assertEqual(headers.get('x-content-type-options'),'nosniff')
                self.assertIn("script-src 'self'",headers.get('content-security-policy',''))
                self.assertNotIn('unsafe-inline',headers.get('content-security-policy',''))
                self.assertNotIn('set-cookie',headers)
                status,h,body=self.request(path,'HEAD');self.assertEqual((status,body),(200,b''))
                self.assertEqual(int(h['content-length']),len(expected))

    def test_every_linked_help_asset_and_shared_stylesheet(self):
        links=Links();links.feed((ROOT/'public/help/index.html').read_text())
        self.assertGreater(len(links.paths),10)
        for path in sorted(links.paths|{'/pilot/assets/shlz.css'}):
            with self.subTest(path=path):
                status,headers,body=self.request(path);self.assertEqual(status,200,'RUNTIME_HELP_ROUTE_REQUIRED '+path)
                expected=(ROOT/'public'/path.lstrip('/')).read_bytes() if path.startswith('/help/') else (ROOT/'app/YiiRuntime/Assets/shlz.css').read_bytes()
                self.assertEqual(hashlib.sha256(body).digest(),hashlib.sha256(expected).digest())
                mime={'.css':'text/css','.js':'text/javascript','.webp':'image/webp'}[pathlib.Path(path).suffix]
                self.assertTrue(headers.get('content-type','').startswith(mime),headers)
                self.assertEqual(headers.get('x-content-type-options'),'nosniff')
                status,h,empty=self.request(path,'HEAD');self.assertEqual((status,empty),(200,b''));self.assertEqual(int(h['content-length']),len(expected))

    def test_unknown_source_and_traversal_paths_are_closed(self):
        for path in ['/help/no-such-file.js','/help/user-guide.md','/help/runtime.php','/help/.env','/help/../.env','/help/%2e%2e/.env','/help/screenshots/../../config/yii/web.php','/help/screenshots/%252e%252e/secret.webp']:
            with self.subTest(path=path):self.assertEqual(self.request(path)[0],404)

    def test_symlink_cannot_expose_outside_file(self):
        target=self.private/'private-source';target.write_bytes(b'PRIVATE_HELP_SENTINEL')
        name='01-login.webp';link=self.site/'public/help/screenshots'/name
        original=link.read_bytes();link.unlink()
        try:
            link.symlink_to(target)
            status,_,body=self.request('/help/screenshots/'+name)
            self.assertEqual(status,404);self.assertNotIn(b'PRIVATE_HELP_SENTINEL',body)
        finally:
            if link.is_symlink():link.unlink()
            link.write_bytes(original)

    def test_host_and_unsupported_method_boundaries(self):
        self.assertEqual(self.request('/help/',host='unexpected.invalid')[0],400)
        for method in ['POST','PUT','DELETE','OPTIONS']:
            with self.subTest(method=method):self.assertIn(self.request('/help/',method)[0],[400,404,405])

if __name__=='__main__':unittest.main()
