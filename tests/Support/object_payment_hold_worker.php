<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/autoload.php';
$c=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
$db=new yii\db\Connection(['dsn'=>$c['dsn'],'username'=>getenv('FMONITOR_TEST_DB_ADMIN_USER')?:'root','password'=>getenv('FMONITOR_TEST_DB_ADMIN_PASSWORD')?:'fmonitor2_test_root_local','charset'=>'utf8mb4']);
$owner=new FMonitor2\Otiz\OtizSettlementV2($db,$c['prefix'],static fn()=>'2026-11-24T19:00:00+03:00',static fn()=>'allow');
file_put_contents($c['ready'],'ready');$end=microtime(true)+15;while(!is_file($c['go'])){if(microtime(true)>$end)exit(3);usleep(10000);}
try{echo json_encode($owner->markPaid($c['actor']??9101,$c['id'],$c['revision'],'2026-11-24',$c['operation']),JSON_THROW_ON_ERROR);}catch(DomainException$e){echo json_encode(['error'=>$e->getMessage()]);}finally{$db->close();}
