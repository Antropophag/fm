<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__,2).'/app/autoload.php';
use FMonitor2\Jobs\{SmtpConfiguration,JobsRuntimeConfiguration,JobHandlerRuntime};
// OPTIONAL-SMTP-001 S01,S04: public configuration and existing fail-closed handler.
$keys=array_map(static fn($s)=>'FMONITOR_SMTP_'.$s,['HOST','USERNAME','PASSWORD','FROM_ADDRESS']);
$full=['FMONITOR_RUNTIME_ENV'=>'production','FMONITOR_SMTP_HOST'=>'smtp.example.test','FMONITOR_SMTP_PORT'=>'587','FMONITOR_SMTP_ENCRYPTION'=>'tls','FMONITOR_SMTP_USERNAME'=>'mailer@example.test','FMONITOR_SMTP_PASSWORD'=>'synthetic-mail-value','FMONITOR_SMTP_FROM_ADDRESS'=>'mailer@example.test','FMONITOR_SMTP_FROM_NAME'=>'FMonitor','FMONITOR_SMTP_TIMEOUT_SECONDS'=>'10','FMONITOR_SMTP_VERIFY_PEER'=>'true','FMONITOR_PUBLIC_BASE_URL'=>'https://fmonitor.example.test'];
assertSameValue(true,is_callable([SmtpConfiguration::class,'isConfigured']),'INTENDED_RED optional SMTP presence seam');
$metadata=array_diff_key($full,array_flip($keys));
foreach([[], $metadata, array_replace($metadata,array_fill_keys($keys,'')),array_replace($metadata,array_fill_keys($keys," \t\r\n"))] as $empty){
    assertSameValue(false,SmtpConfiguration::isConfigured($empty),'S01 absent/blank/metadata does not enable SMTP');
    try{SmtpConfiguration::fromEnvironment($empty);throw new TestFailure('empty configuration allowed a sender');}
    catch(RuntimeException $e){assertSameValue('SMTP_CONFIGURATION_INVALID',$e->getMessage(),'sender still rejects disabled SMTP');}
}
for($mask=1;$mask<16;$mask++){
    $candidate=$metadata;foreach($keys as $i=>$key)$candidate[$key]=($mask&(1<<$i))?$full[$key]:'';
    assertSameValue(true,SmtpConfiguration::isConfigured($candidate),'S01 any credential requires validation');
    if($mask===15){assertSameValue('smtp.example.test',SmtpConfiguration::fromEnvironment($candidate)->host,'full config unchanged');continue;}
    try{SmtpConfiguration::fromEnvironment($candidate);throw new TestFailure('partial SMTP accepted');}
    catch(RuntimeException $e){assertSameValue('SMTP_CONFIGURATION_INVALID',$e->getMessage(),'partial config remains invalid');}
}
foreach($keys as $key)foreach([false,0,[],null] as $value){
    try{SmtpConfiguration::isConfigured([$key=>$value]);throw new TestFailure('nonstring SMTP accepted');}
    catch(RuntimeException $e){assertSameValue('SMTP_CONFIGURATION_INVALID',$e->getMessage(),'invalid type fails closed');}
}
$previous=getenv();
try{
    foreach(array_keys($previous) as $key)if(str_starts_with($key,'FMONITOR_SMTP_'))putenv($key);
    foreach(['HOST'=>'127.0.0.1','PORT'=>'1','NAME'=>'not_opened','USER'=>'test','PASSWORD'=>'synthetic']as$key=>$value)putenv('FMONITOR_DB_'.$key.'='.$value);
    putenv('FMONITOR_PROCESS_TABLE_PREFIX=test_');
    $config=JobsRuntimeConfiguration::fromEnvironment();
    assertSameValue(['status'=>'permanent','failureCode'=>'OUTBOX_TRANSPORT_UNCONFIGURED'],JobHandlerRuntime::handle(['jobType'=>'outbox.dispatch','payloadVersion'=>1,'payload'=>['intentId'=>1]],$config),'S04 queued email refuses before unreachable DB or network');
    $calls=0;
    $result=JobHandlerRuntime::handle(['jobType'=>'bitrix.order-document-links.sync'],$config,static function()use(&$calls){$calls++;return['status'=>'published','count'=>3];});
    assertSameValue(['status'=>'completed','result'=>['published'=>3]],$result,'Bitrix dispatch independent of SMTP');
    $result=JobHandlerRuntime::handle(['jobType'=>'erp.equipment-facts.sync'],$config,static function()use(&$calls){$calls++;return['status'=>'completed','runId'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','matched'=>2];});
    assertSameValue(['status'=>'completed','result'=>['runId'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','matched'=>2]],$result,'ERP dispatch independent of SMTP');
    assertSameValue(2,$calls,'both integration callbacks execute');
}finally{foreach(array_keys(getenv())as$key)if(!array_key_exists($key,$previous))putenv($key);foreach($previous as$key=>$value)putenv($key.'='.$value);}
echo "PASS OPTIONAL-SMTP-001 presence and unchanged fail-closed delivery\n";
