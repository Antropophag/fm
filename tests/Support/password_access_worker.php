<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__,2).'/vendor/autoload.php';
require dirname(__DIR__,2).'/vendor/yiisoft/yii2/Yii.php';
$c=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);$i=(int)$argv[2];
$owner=new FMonitor2\IdentityAccess\YiiUserAccess(['db'=>new yii\db\Connection($c['connection']),'tablePrefix'=>$c['prefix'],'passwordClock'=>static fn()=>new DateTimeImmutable($c['clock']),'passwordRecoveryConfig'=>$c['recovery']]);
file_put_contents($c['directory'].'/ready-'.$i,'ready');$deadline=microtime(true)+15;
while(!is_file($c['directory'].'/go')){if(microtime(true)>$deadline)throw new RuntimeException('barrier timeout');usleep(10000);}
$op=$c['operations'][$i];$result=$owner->{$op['method']}(...$op['arguments']);
file_put_contents($c['directory'].'/result-'.$i.'.json',json_encode($result,JSON_THROW_ON_ERROR));
