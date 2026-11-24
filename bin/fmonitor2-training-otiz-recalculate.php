<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use FMonitor2\Otiz\TrainingOtizRecalculation;

$exit=0;$db=null;
try{
 $required=static function(string$n):string{$v=getenv($n);if(!is_string($v)||$v==='')throw new InvalidArgumentException();return$v;};
 $mode=$argv[1]??'';$actor=filter_var($argv[2]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!in_array($mode,['preview','apply'],true)||$actor===false)throw new InvalidArgumentException();
 $port=$required('FMONITOR_DB_PORT');$prefix=$required('FMONITOR_PROCESS_TABLE_PREFIX');$legacy=(string)(getenv('FMONITOR_LEGACY_TABLE_PREFIX')?:$prefix);if(filter_var($port,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]])===false)throw new InvalidArgumentException();
 $db=new yii\db\Connection(['dsn'=>'mysql:host='.$required('FMONITOR_DB_HOST').';port='.$port.';dbname='.$required('FMONITOR_DB_NAME'),'username'=>$required('FMONITOR_DB_USER'),'password'=>$required('FMONITOR_DB_PASSWORD'),'charset'=>'utf8mb4']);$db->open();$owner=new TrainingOtizRecalculation($db,$prefix,$legacy,$required('FMONITOR_PUBLIC_BASE_URL'),static fn()=>(new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM));
 if($mode==='preview'){if($argc!==3)throw new InvalidArgumentException();$out=$owner->preview((int)$actor);}else{if($argc!==5)throw new InvalidArgumentException();$out=$owner->apply((int)$actor,(string)$argv[3],(string)$argv[4]);}
}catch(InvalidArgumentException){$exit=64;$out=['ok'=>false,'reason'=>'CONFIGURATION_INVALID'];}
catch(DomainException$e){$exit=65;$out=['ok'=>false,'reason'=>$e->getMessage()];}
catch(Throwable){$exit=70;$out=['ok'=>false,'reason'=>'TRAINING_RECALCULATION_UNAVAILABLE'];}
finally{if($db instanceof yii\db\Connection)$db->close();}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";exit($exit);
