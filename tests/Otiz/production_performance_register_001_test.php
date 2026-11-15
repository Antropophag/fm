<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\Otiz\OtizRegisterAdmission;
assertSameValue(true,class_exists(OtizRegisterAdmission::class),'INTENDED_RED A01 request-local register projection is absent');
$rows=[];foreach([[1,2],[1,2],[2,3]]as$i=>$ids)$rows[]=['id'=>$i+10,'projection_json'=>json_encode(['objects'=>array_map(static fn(int$id):array=>['objectId'=>$id],$ids)]),'obligation_cents'=>1234];
$calls=[];$read=static function(int$id)use(&$calls):array{$calls[]=$id;return['decision'=>'allow'];};
$actual=OtizRegisterAdmission::decorate($rows,$read);
assertSameValue([1,2,3],$calls,'A01 repeated objects read once per render');
assertSameValue(array_column($rows,'id'),array_column($actual,'id'),'row identity/order retained');
assertSameValue([1234,1234,1234],array_column($actual,'obligation_cents'),'read optimization never changes money');
$calls=[];$blocked=OtizRegisterAdmission::decorate($rows,static function(int$id)use(&$calls):array{$calls[]=$id;return['decision'=>$id===2?'blocked':'allow','reasonCode'=>$id===2?'COMPOSITION_MISMATCH':null];});
assertSameValue([1,2],$calls,'first blocking object short circuit and per-request memo');
assertSameValue(array_fill(0,3,'Несоответствие состава'),array_column($blocked,'blocking_reason'),'same first blocking reason in every affected row');
$calls=[];OtizRegisterAdmission::decorate($rows,$read);assertSameValue([1,2,3],$calls,'next request rechecks changed admission rather than retaining blocked cache');
$unknown=OtizRegisterAdmission::decorate([$rows[0]],static fn(int$id):array=>['decision'=>'unknown','reasonCode'=>'NATIVE_EVIDENCE_UNPROVEN']);
assertSameValue('Основание выплаты не подтверждено',$unknown[0]['blocking_reason'],'unknown is not payment approval');
assertSameValue([],OtizRegisterAdmission::decorate([],static fn()=>throw new TestFailure('empty register queried')),'empty register no reads');
foreach(['not-json','{"objects":{"a":{"objectId":1}}}','{}','null','{"objects":null}','{"objects":[]}','{"objects":"bad"}','{"objects":[{}]}','{"objects":[{"objectId":0}]}','{"objects":[{"objectId":"bad"}]}']as$invalid){$result=OtizRegisterAdmission::decorate([['id'=>71,'projection_json'=>$invalid,'obligation_cents'=>1234]],static fn(int$id):array=>['decision'=>'allow']);assertSameValue('Основание выплаты не подтверждено',$result[0]['blocking_reason']??null,'corrupt projection never displayed as payable '.$invalid);assertSameValue(1234,$result[0]['obligation_cents'],'corrupt read never changes stored amount');}
echo "PASS PRODUCTION-PERFORMANCE-001 A01\n";
