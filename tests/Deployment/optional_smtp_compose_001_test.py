#!/usr/bin/env python3
"""OPTIONAL-SMTP-001 S03: actual Compose interpolation, no daemon/resources."""
import json
import os
import pathlib
import subprocess
ROOT=pathlib.Path(__file__).resolve().parents[2]
BASE={'FMONITOR_DB_NAME':'fixture','FMONITOR_DB_USER':'fixture','FMONITOR_DB_PASSWORD':'synthetic-db',
      'FMONITOR_PROCESS_TABLE_PREFIX':'p_','FMONITOR_LEGACY_TABLE_PREFIX':'l_','FMONITOR_SESSION_INSTANCE':'fixture',
      'FMONITOR_YII_COOKIE_VALIDATION_KEY':'k'*64,'FMONITOR_YII_IDENTITY_KEY':'i'*64,
      'FMONITOR_TRUSTED_REQUEST_HOST':'fmonitor.example.test','FMONITOR_TRUSTED_REQUEST_SCHEME':'https',
      'FMONITOR_PUBLIC_BASE_URL':'https://fmonitor.example.test','FMONITOR_RUNTIME_IMAGE':'fmonitor2-runtime:fixture',
      'FMONITOR_DATABASE_IMAGE':'mariadb:11.4','FMONITOR_MIGRATION_DB_USER':'root','FMONITOR_MIGRATION_DB_PASSWORD':'synthetic-root',
      'FMONITOR_HTTP_PORT':'18093','FMONITOR_ERP_HOST':'erp.example.test','FMONITOR_ERP_DATABASE':'erp',
      'FMONITOR_ERP_USER':'reader','FMONITOR_ERP_PASSWORD':'synthetic-erp','FMONITOR_ERP_EQUIPMENT_FACTS_HMAC_KEY':'h'*40,
      'FMONITOR_ERP_EQUIPMENT_FACTS_TIMEOUT_SECONDS':'5','FMONITOR_ERP_EQUIPMENT_FACTS_MAX_ROWS':'100',
      'FMONITOR_ERP_EQUIPMENT_FACTS_CHUNK_SIZE':'50'}
MAIL={'FMONITOR_SMTP_'+k:v for k,v in {'HOST':'smtp.example.test','USERNAME':'mailer@example.test','PASSWORD':'synthetic-mail',
    'FROM_ADDRESS':'mailer@example.test','PORT':'587','ENCRYPTION':'tls','FROM_NAME':'FMonitor','TIMEOUT_SECONDS':'10','VERIFY_PEER':'true'}.items()}
for path in ('deploy/production/compose.yaml','deploy/runtime/compose.yaml'):
    for enabled in (False,True):
        mail=dict(MAIL)
        if not enabled:
            for suffix in ('HOST','USERNAME','PASSWORD','FROM_ADDRESS'):mail['FMONITOR_SMTP_'+suffix]=''
        env={k:v for k,v in os.environ.items() if not k.startswith(('FMONITOR_','COMPOSE_'))}
        env.update(BASE);env.update(mail)
        result=subprocess.run(['docker','compose','--env-file','/dev/null','-p','fm2-optional-config','-f',str(ROOT/path),'--profile','jobs','config','--format','json'],cwd=ROOT,env=env,text=True,capture_output=True,timeout=20)
        assert result.returncode==0, 'SETUP_FAILURE compose rendering '+result.stderr
        services=json.loads(result.stdout)['services']
        for service in ('jobs-worker','jobs-scheduler'):
            config=services[service]['environment']
            for key,value in dict(mail,FMONITOR_PUBLIC_BASE_URL=BASE['FMONITOR_PUBLIC_BASE_URL'],FMONITOR_RUNTIME_ENV='production').items():
                assert config.get(key)==value, 'INTENDED_RED '+path+' '+service+' does not receive '+key
print('PASS OPTIONAL-SMTP-001 both Compose schedulers receive blank/full SMTP')
