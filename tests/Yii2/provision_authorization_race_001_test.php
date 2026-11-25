<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PasswordAccessFixture.php';
$f=new PasswordAccessFixture();$w=$f->web;$process=null;$path=null;
try {
    $f->start();$admin=[];$w->login($admin);assertSameValue(true,str_contains($w->page($admin)['body'],'/pilot/admin/users/create'),'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 provision authority race seam');
    $e=$w->environment();$dir=$w->artifacts.'/revoke-race';mkdir($dir,0700);$path=$dir.'/config.json';
    $config=['connection'=>['dsn'=>'mysql:host='.$e['FMONITOR_DB_HOST'].';port='.$e['FMONITOR_DB_PORT'].';dbname='.$e['FMONITOR_DB_NAME'],'username'=>$e['FMONITOR_DB_USER'],'password'=>$e['FMONITOR_DB_PASSWORD'],'charset'=>'utf8mb4'],'prefix'=>$w->p,'directory'=>$dir,'operations'=>[['method'=>'provision','arguments'=>[9101,'revoked.race@shlz.ru','Revoked Race']]],'clock'=>$f->now->format(DATE_ATOM),'recovery'=>$f->config()];file_put_contents($path,json_encode($config,JSON_THROW_ON_ERROR));chmod($path,0600);
    // SQL is a deterministic authorization-input fault/barrier, not the tested provision command.
    $w->db->begin_transaction();$w->db->query("SELECT role_id FROM {$w->p}fm2_pilot_roles WHERE code='user' FOR UPDATE");
    $w->db->query("DELETE FROM {$w->p}fm2_pilot_role_permissions WHERE role_id=9201 AND permission='access.administer'");
    $process=proc_open([PHP_BINARY,dirname(__DIR__).'/Support/password_access_worker.php',$path,'0'],[0=>['file','/dev/null','r'],1=>['file',$dir.'/worker.log','a'],2=>['file',$dir.'/worker.log','a']],$pipes,$w->root);if(!is_resource($process))throw new TestFailure('worker setup');
    $deadline=microtime(true)+10;while(!is_file($dir.'/ready-0')&&microtime(true)<$deadline)usleep(10000);assertSameValue(true,is_file($dir.'/ready-0'),'worker ready');file_put_contents($dir.'/go','go');
    $waiting=false;$deadline=microtime(true)+10;
    do{$rows=$w->db->query("SELECT t.trx_mysql_thread_id FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE b.trx_mysql_thread_id=CONNECTION_ID()")->fetch_all(MYSQLI_ASSOC);$waiting=$rows!==[];if($waiting)break;usleep(200000); // Allow MariaDB's InnoDB status snapshot to refresh between observations.
}while(microtime(true)<$deadline);
    assertSameValue(true,$waiting,'provision is actually blocked behind uncommitted authority change');$w->db->commit();
    $deadline=microtime(true)+10;do{$state=proc_get_status($process);if(!$state['running'])break;usleep(20000);}while(microtime(true)<$deadline);assertSameValue(false,$state['running'],'worker completes after revoke');assertSameValue(0,$state['exitcode'],'worker exit '.file_get_contents($dir.'/worker.log'));proc_close($process);$process=null;
    $result=json_decode(file_get_contents($dir.'/result-0.json'),true,flags:JSON_THROW_ON_ERROR);assertSameValue('access_denied',$result['status'],'authority rechecked after revoke commit');
    assertSameValue(0,(int)$w->db->query("SELECT COUNT(*) FROM {$w->p}fm2_pilot_users WHERE email='revoked.race@shlz.ru'")->fetch_column(),'no identity');assertSameValue(0,(int)$w->db->query("SELECT COUNT(*) FROM {$w->p}fm2_pilot_auth_credentials WHERE email_normalized='revoked.race@shlz.ru'")->fetch_column(),'no credential');assertSameValue([],$f->rows('fm2_password_events'),'no provision event');assertSameValue([],$f->rows('fm2_pilot_invitations'),'no invitation');assertSameValue([],$f->rows('fm2_outbox_intents'),'no mail');
    echo "PASS: provision rechecks authority after overlapping revoke commit\n";
}finally{if($w->db->thread_id)$w->db->rollback();if(is_resource($process)){proc_terminate($process,9);proc_close($process);}if($path!==null&&is_file($path))unlink($path);$f->close();}
