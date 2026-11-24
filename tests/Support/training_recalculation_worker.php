<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/autoload.php';
$c=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);$db=new yii\db\Connection($c['db']);
$owner=new FMonitor2\Otiz\TrainingOtizRecalculation($db,$c['prefix'],$c['prefix'],'https://fmonitor.antropophag.ru',static fn()=>'2026-11-24T19:00:00+03:00');
file_put_contents($c['ready'],(string)$db->createCommand('SELECT CONNECTION_ID()')->queryScalar());$end=microtime(true)+15;while(!is_file($c['go'])){if(microtime(true)>$end)exit(3);usleep(10000);}
try{echo json_encode($owner->apply(96,$c['previewHash'],$c['operation']),JSON_THROW_ON_ERROR);}finally{$db->close();}
