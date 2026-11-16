<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__,2).'/app/autoload.php';
use FMonitor2\InstallationProcess\JobsSchemaMigration;
use FMonitor2\Jobs\{MariaDbJobQueue,MariaDbOutbox,JobsSchedulerProcess,MariaDbWorkforceScheduler,MariaDbOrderDocumentLinksScheduler,MariaDbWorkerHeartbeat,MariaDbEquipmentFactsScheduler,MariaDbInstallerUtilizationScheduler};
// OPTIONAL-SMTP-001 S03: real CLI + owned DB; scheduler never executes transports.
function osmtpCli(array $args,array $env,?callable $ready=null):array{
    $pipes=[];$proc=proc_open([PHP_BINARY,dirname(__DIR__,2).'/bin/fmonitor2-jobs.php',...$args],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),$env);
    if(!is_resource($proc))throw new TestFailure('SETUP_FAILURE cli spawn');
    foreach($pipes as $pipe)stream_set_blocking($pipe,false);
    $out='';$err='';$end=microtime(true)+12;$signaled=false;
    try{while(true){
        $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);$status=proc_get_status($proc);
        if(!$status['running']){$exit=$status['exitcode'];break;}
        if($ready!==null&&!$signaled&&$ready()){proc_terminate($proc,SIGTERM);$signaled=true;}
        if(microtime(true)>$end)throw new TestFailure('INTENDED_RED scheduler did not reach expected state');
        usleep(10000);
    }}finally{
        if(proc_get_status($proc)['running'])proc_terminate($proc,SIGKILL);
        $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);proc_close($proc);
    }
    return[$exit,json_decode(trim($out),true,512,JSON_THROW_ON_ERROR),$err];
}
$host=getenv('FMONITOR_TEST_DB_HOST')?:'127.0.0.1';$port=(int)(getenv('FMONITOR_TEST_DB_PORT')?:23306);
$user=getenv('FMONITOR_TEST_DB_ADMIN_USER')?:'root';$password=getenv('FMONITOR_TEST_DB_ADMIN_PASSWORD')?:'fmonitor2_test_root_local';
$name='t_optional_smtp_'.bin2hex(random_bytes(5));$prefix='smtp_';
$admin=new mysqli($host,$user,$password,'',$port);$admin->query("CREATE DATABASE `$name`");$db=new mysqli($host,$user,$password,$name,$port);
try{
    JobsSchemaMigration::apply($db,$prefix);
    $env=array_filter(getenv(),static fn($k)=>!str_starts_with($k,'FMONITOR_'),ARRAY_FILTER_USE_KEY);
    $env=array_replace($env,['FMONITOR_DB_HOST'=>$host,'FMONITOR_DB_PORT'=>(string)$port,'FMONITOR_DB_NAME'=>$name,'FMONITOR_DB_USER'=>$user,'FMONITOR_DB_PASSWORD'=>$password,'FMONITOR_PROCESS_TABLE_PREFIX'=>$prefix,'FMONITOR_SESSION_INSTANCE'=>'optional-smtp','FMONITOR_RUNTIME_ENV'=>'production','FMONITOR_PUBLIC_BASE_URL'=>'http://127.0.0.1:8093','FMONITOR_ERP_HOST'=>'erp.example.invalid','FMONITOR_ERP_DATABASE'=>'erp','FMONITOR_ERP_USER'=>'reader','FMONITOR_ERP_PASSWORD'=>'synthetic-erp-value','FMONITOR_ERP_EQUIPMENT_FACTS_HMAC_KEY'=>str_repeat('h',40),'FMONITOR_ERP_EQUIPMENT_FACTS_TIMEOUT_SECONDS'=>'7','FMONITOR_ERP_EQUIPMENT_FACTS_MAX_ROWS'=>'500','FMONITOR_ERP_EQUIPMENT_FACTS_CHUNK_SIZE'=>'100','FMONITOR_SMTP_PORT'=>'587','FMONITOR_SMTP_ENCRYPTION'=>'tls','FMONITOR_SMTP_FROM_NAME'=>'FMonitor','FMONITOR_SMTP_TIMEOUT_SECONDS'=>'10','FMONITOR_SMTP_VERIFY_PEER'=>'true']);
    foreach(['HOST','USERNAME','PASSWORD','FROM_ADDRESS']as$suffix)$env['FMONITOR_SMTP_'.$suffix]='';
    $now=$db->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(6),'%Y-%m-%dT%H:%i:%s.%fZ')")->fetch_column();
    $heartbeat=new MariaDbWorkerHeartbeat($db,$prefix);$heartbeat->observe('worker:optional-smtp',$now);$heartbeat->observe('scheduler:optional-smtp',$now);
    [$exit,$body,$err]=osmtpCli(['health'],$env);
    assertSameValue([0,true,''],[$exit,$body['ok']??null,$err],'INTENDED_RED production health accepts blank SMTP and local HTTP');
    $full=array_replace($env,['FMONITOR_PUBLIC_BASE_URL'=>'https://fmonitor.example.test','FMONITOR_SMTP_HOST'=>'smtp.example.invalid','FMONITOR_SMTP_USERNAME'=>'mailer@example.test','FMONITOR_SMTP_PASSWORD'=>'synthetic-mail-value','FMONITOR_SMTP_FROM_ADDRESS'=>'mailer@example.test']);
    [$exit,$body,$err]=osmtpCli(['health'],$full);assertSameValue([0,true,''],[$exit,$body['ok']??null,$err],'configured SMTP health preserved without connecting');
    foreach([array_replace($env,['FMONITOR_SMTP_HOST'=>'smtp.example.invalid']),array_replace($full,['FMONITOR_SMTP_VERIFY_PEER'=>'false'])]as$bad){
        foreach(['health','scheduler']as$mode){
            [$exit,$body,$err]=osmtpCli([$mode],$bad);
            assertSameValue([70,['ok'=>false,'error'=>'JOBS_UNAVAILABLE'],''],[$exit,$body,$err],'partial/invalid SMTP fails closed before scheduling');
            assertSameValue(0,(int)$db->query("SELECT COUNT(*) FROM {$prefix}fm2_jobs")->fetch_column(),'invalid SMTP creates no jobs');
        }
    }
    $queue=new MariaDbJobQueue($db,$prefix,null,null,['outbox.dispatch'=>[1],'weekly-fkr-report.generate'=>[1]]);
    foreach(['outbox.dispatch','weekly-fkr-report.generate'] as $i=>$type){
        $queue->enqueue(['jobType'=>$type,'payloadVersion'=>1,'payload'=>['fixture'=>'historical'],'availableAtUtc'=>$now,'idempotencyKey'=>($i===0?'aaaaaaaa':'bbbbbbbb').'-aaaa-4aaa-8aaa-aaaaaaaaaaaa','actor'=>['type'=>'system','id'=>'fixture']]);
        $lease=$queue->claim(['workerId'=>'fixture','batch'=>1])[0];
        if($i===0)$queue->fail(['jobId'=>$lease['jobId'],'leaseToken'=>$lease['leaseToken'],'failureCode'=>'FIXTURE','retryable'=>false]);
        else $queue->complete(['jobId'=>$lease['jobId'],'leaseToken'=>$lease['leaseToken'],'result'=>['fixture'=>true]]);
    }
    $history=static fn()=>[$db->query("SELECT * FROM {$prefix}fm2_jobs WHERE job_id IN (1,2) ORDER BY job_id")->fetch_all(MYSQLI_ASSOC),$db->query("SELECT * FROM {$prefix}fm2_job_events WHERE job_id IN (1,2) ORDER BY event_id")->fetch_all(MYSQLI_ASSOC)];
    $historyBefore=$history();
    $outbox=new MariaDbOutbox($db,$prefix);$db->begin_transaction();$created=$outbox->append(['eventId'=>'optional-smtp-event','channel'=>'email','template'=>'weekly-fkr-report','version'=>1,'data'=>['recipientIdentity'=>'fixture']]);$db->commit();
    $intentBefore=$outbox->read($created['intentId']);
    $db->query("DELETE FROM {$prefix}fm2_worker_heartbeats WHERE worker_id='scheduler:optional-smtp'");
    $ready=static fn()=>(int)$db->query("SELECT COUNT(*) FROM {$prefix}fm2_worker_heartbeats WHERE worker_id='scheduler:optional-smtp'")->fetch_column()===1;
    [$exit,$body,$err]=osmtpCli(['scheduler'],$env,$ready);
    assertSameValue([0,['status'=>'stopped','exitCode'=>0],''],[$exit,$body,$err],'blank SMTP scheduler runs and stops normally');
    $types=array_column($db->query("SELECT DISTINCT job_type FROM {$prefix}fm2_jobs WHERE job_id NOT IN (1,2) ORDER BY job_type")->fetch_all(MYSQLI_ASSOC),'job_type');
    assertSameValue(['bitrix.order-document-links.sync','erp.equipment-facts.sync','workforce.sync'],$types,'integration scheduling active without any email jobs');
    assertSameValue($intentBefore,$outbox->read($created['intentId']),'disabled scheduling preserves pending intent exactly');
    assertSameValue($historyBefore,$history(),'blank SMTP preserves historical mail jobs/events exactly');
    assertSameValue(0,(int)$db->query("SELECT COUNT(*) FROM {$prefix}fm2_outbox_attempt_events")->fetch_column(),'no delivery attempts');
    // Deterministic Monday at the public scheduler seam: no email collaborators,
    // but the other durable schedules and heartbeat must tick. SIGTERM stops after one tick.
    $fixed='2026-11-23T07:00:00.000000Z';
    $process=new JobsSchedulerProcess(new MariaDbWorkforceScheduler($db,$prefix),null,new MariaDbOrderDocumentLinksScheduler($db,$prefix),$heartbeat,static function()use($fixed){posix_kill(getmypid(),SIGTERM);return $fixed;},'scheduler:fixed',null,new MariaDbEquipmentFactsScheduler($db,$prefix),new MariaDbInstallerUtilizationScheduler($db,$prefix));
    assertSameValue(['status'=>'stopped','exitCode'=>0],$process->run(),'Monday disabled mail process stops normally');
    assertSameValue(0,(int)$db->query("SELECT COUNT(*) FROM {$prefix}fm2_jobs WHERE job_id NOT IN (1,2) AND job_type IN ('outbox.dispatch','weekly-fkr-report.generate')")->fetch_column(),'Monday disabled email does not enqueue');
    assertSameValue($historyBefore,$history(),'Monday tick preserves historical mail jobs/events exactly');
    $db->query("DELETE FROM {$prefix}fm2_worker_heartbeats WHERE worker_id='scheduler:optional-smtp'");
    [$exit,$body,$err]=osmtpCli(['scheduler'],$full,$ready);
    assertSameValue([0,['status'=>'stopped','exitCode'=>0],''],[$exit,$body,$err],'complete SMTP scheduler preserved');
    assertSameValue(1,(int)$db->query("SELECT COUNT(*) FROM {$prefix}fm2_jobs WHERE job_id NOT IN (1,2) AND job_type='outbox.dispatch'")->fetch_column(),'re-enable schedules pending intent exactly once');
    assertSameValue($intentBefore,$outbox->read($created['intentId']),'scheduling is not delivery');
    assertSameValue($historyBefore,$history(),'re-enable preserves historical mail jobs/events exactly');
    assertSameValue(0,(int)$db->query("SELECT COUNT(*) FROM {$prefix}fm2_outbox_attempt_events")->fetch_column(),'scheduler never attempts mail');
    echo "PASS OPTIONAL-SMTP-001 real health/scheduler and pending history\n";
}finally{$db->close();$admin->query("DROP DATABASE IF EXISTS `$name`");$admin->close();}
