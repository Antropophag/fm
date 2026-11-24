<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\Tests\Support\PaymentHoldFixture;
$f=null;
try{
 $f=new PaymentHoldFixture();$s=$f->owner;$p=$f->p;
 assertSameValue(true,method_exists($s,'redistributeReserveToRemainingCrew'),'INTENDED_RED: authoritative remaining-crew redistribution');
 foreach([7001=>'dismissed',7002=>'employed',7003=>'employed']as$id=>$status)$f->db->createCommand()->insert($p.'fm2_workforce_catalog',['installer_tab_id'=>$id,'fio'=>'Монтажник '.$id,'position'=>'Монтажник','employment_status'=>$status,'workforce_source'=>'fixture','workforce_source_updated_at'=>'2026-11-24T09:00:00Z','reconciliation_state'=>'delivered'])->execute();
 $source=static function(string$key,bool$holdPeer=false)use($f,$s):array {
  $people=[];foreach([7001=>20,7002=>60,7003=>20]as$id=>$weight)$people[]=['employeeId'=>(string)$id,'tabNumber'=>(string)$id,'name'=>'Монтажник '.$id,'weight'=>$weight,'employment'=>$id===7001?'dismissed':'employed'];
  $d=$s->createDraft(9101,['reportDate'=>'2026-11-24','recipientPolicyVersion'=>'reserved-shares-v1','objects'=>[['objectId'=>4512,'regnumber'=>'TEST-4512','fundCents'=>1000000,'kssBp'=>10000,'entitlements'=>[['sourceKind'=>'fixture','sourceId'=>'reserve-'.$key,'sourceRevision'=>'1','kind'=>'progress','recognitionDate'=>'2026-11-20','grossCents'=>1000000]],'recipients'=>$people]]],$f->op());if($holdPeer)$d=$s->saveObjectDecision(9101,$d['calculationId'],$d['revision'],4512,'7003','do_not_pay','Личная проверка',$f->op());return$s->accept(9101,$d['calculationId'],$d['revision'],$f->op());
 };
 // Failure while persisting the default policy must roll back draft, event and receipt together.
 $atomic=$source('atomic');$atomicOp=$f->op();$counts=static fn()=>[$f->count('fm2_otiz_calculation_revisions'),$f->count('fm2_otiz_v2_events'),$f->count('fm2_otiz_v2_operations')];$before=$counts();
 foreach(['INSERT','UPDATE']as$event)$f->db->createCommand("CREATE TRIGGER {$p}fail_policy_{$event} BEFORE {$event} ON {$p}fm2_otiz_calculation_revisions FOR EACH ROW BEGIN IF JSON_EXTRACT(NEW.input_json,'$.reserveRelease.remainingCrewPolicy') = true THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='controlled failure policy'; END IF; END")->execute();
 $failed=false;try{$s->redistributeReserveToRemainingCrew(9101,$atomic['calculationId'],$atomic['revision'],4512,'7001','Атомарное решение',$atomicOp);}catch(Throwable$failure){$failed=str_contains($failure->getMessage(),'controlled failure policy');}
 assertSameValue(true,$failed,'injected persistence failure reached');assertSameValue($before,$counts(),'policy failure leaves no committed draft event or receipt');
 foreach(['INSERT','UPDATE']as$event)$f->db->createCommand("DROP TRIGGER {$p}fail_policy_{$event}")->execute();
 $atomicResult=$s->redistributeReserveToRemainingCrew(9101,$atomic['calculationId'],$atomic['revision'],4512,'7001','Атомарное решение',$atomicOp);
 $f->db->createCommand("UPDATE {$p}fm2_workforce_catalog SET employment_status='dismissed' WHERE installer_tab_id IN(7002,7003)")->execute();$before=$counts();
 assertSameValue($atomicResult,$s->redistributeReserveToRemainingCrew(9101,$atomic['calculationId'],$atomic['revision'],4512,'7001','Атомарное решение',$atomicOp),'replay returns original receipt despite changed recipient eligibility');assertSameValue($before,$counts(),'replay writes no facts');
 $f->db->createCommand("UPDATE {$p}fm2_workforce_catalog SET employment_status='employed' WHERE installer_tab_id IN(7002,7003)")->execute();
 $a=$source('weighted');$id=$a['calculationId'];$op=$f->op();$r=$s->redistributeReserveToRemainingCrew(9101,$id,$a['revision'],4512,'7001','Решение ОТиЗ',$op);
 assertSameValue(['7002'=>150000,'7003'=>50000],array_column($r['recipients'],'amountCents','employeeId'),'saved60:20 contribution, not equal allocation');
 assertSameValue($r,$s->redistributeReserveToRemainingCrew(9101,$id,$a['revision'],4512,'7001','Решение ОТиЗ',$op),'default release replay');
 assertSameValue(200000,$s->read($id)['remainingReservedCents'],'draft preserves reserve');
 $deny=static function(string$code,callable$call)use($f,$s,$id):void{$before=[$f->count('fm2_otiz_calculation_revisions'),$f->count('fm2_otiz_v2_events'),$f->count('fm2_otiz_v2_operations'),$s->read($id)];$denied=false;try{$call();}catch(DomainException$e){$denied=$e->getMessage()===$code;}assertSameValue(true,$denied,'reserve denial '.$code);assertSameValue($before,[$f->count('fm2_otiz_calculation_revisions'),$f->count('fm2_otiz_v2_events'),$f->count('fm2_otiz_v2_operations'),$s->read($id)],'reserve rejection no facts');};
 $deny('FORBIDDEN',fn()=>$s->redistributeReserveToRemainingCrew(0,$id,$a['revision'],4512,'7001','Решение',$f->op()));
 $deny('STALE_REVISION',fn()=>$s->redistributeReserveToRemainingCrew(9101,$id,$a['revision']-1,4512,'7001','Решение',$f->op()));
 $deny('OPERATION_CONFLICT',fn()=>$s->redistributeReserveToRemainingCrew(9101,$id,$a['revision'],4512,'7001','Другая причина',$op));
 $competing=$s->redistributeReserveToRemainingCrew(9102,$id,$a['revision'],4512,'7001','Второй черновик',$f->op());

 $s->accept(9101,$r['calculationId'],$r['revision'],$f->op());assertSameValue(0,$s->read($id)['remainingReservedCents'],'accept consumes once');
 $deny('RESERVE_EXHAUSTED',fn()=>$s->accept(9102,$competing['calculationId'],$competing['revision'],$f->op()));
 $heldPeer=$source('personal-reserve',true);$heldRelease=$s->redistributeReserveToRemainingCrew(9101,$heldPeer['calculationId'],$heldPeer['revision'],4512,'7001','Остальным допустимым',$f->op());
 assertSameValue(['7002'=>200000],array_column($heldRelease['recipients'],'amountCents','employeeId'),'employed peer with own unresolved reserve is excluded');
 assertSameValue(200000,array_column($s->read($heldPeer['calculationId'])['recipients'],'reservedCents','employeeId')['7003'],'peer own reserve remains separate');

 $f->db->createCommand("UPDATE {$p}fm2_workforce_catalog SET employment_status='dismissed' WHERE installer_tab_id=7003")->execute();
 $b=$source('current-status');$r=$s->redistributeReserveToRemainingCrew(9101,$b['calculationId'],$b['revision'],4512,'7001','После проверки',$f->op());
 assertSameValue(['7002'=>200000],array_column($r['recipients'],'amountCents','employeeId'),'dismissed since source calculation not silently paid as peer');
 $f->db->createCommand("UPDATE {$p}fm2_workforce_catalog SET employment_status='dismissed' WHERE installer_tab_id=7002")->execute();
 $obligationsBefore=$f->count('fm2_otiz_recipient_obligations');$denied=false;
 try{$s->accept(9101,$r['calculationId'],$r['revision'],$f->op());}catch(DomainException$e){$denied=$e->getMessage()==='RESERVE_RECIPIENT_UNAVAILABLE';}
 assertSameValue(true,$denied,'default redistribution rechecks current employment at acceptance');assertSameValue($obligationsBefore,$f->count('fm2_otiz_recipient_obligations'),'stale recipient creates no obligation');
 $before=$f->count('fm2_otiz_calculation_revisions');$denied=false;
 try{$s->redistributeReserveToRemainingCrew(9101,$b['calculationId'],$b['revision'],4512,'7001','Нет получателей',$f->op());}catch(DomainException$e){$denied=$e->getMessage()==='NO_ELIGIBLE_RESERVE_RECIPIENT';}
 assertSameValue(true,$denied,'no eligible peers is explicit');assertSameValue($before,$f->count('fm2_otiz_calculation_revisions'),'no empty release draft');assertSameValue(200000,$s->read($b['calculationId'])['remainingReservedCents'],'no eligible peers preserves reserve');
 $explicit=$s->createReserveReleaseDraft(9101,$b['calculationId'],$b['revision'],4512,'7001',[['employeeId'=>'7001','weight'=>1]],'Явное решение выплатить уволенному',$f->op());
 assertSameValue(['7001'=>200000],array_column($explicit['recipients'],'amountCents','employeeId'),'explicit original dismissed recipient payment remains possible');
 $explicit=$s->accept(9101,$explicit['calculationId'],$explicit['revision'],$f->op());$s->holdObjectPayments(9101,4512,0,'ФКР: проверка объекта',$f->op());$held=false;
 try{$s->markPaid(9101,$explicit['calculationId'],$explicit['revision'],'2026-11-24',$f->op());}catch(DomainException$e){$held=$e->getMessage()==='PAYMENT_HELD';}assertSameValue(true,$held,'object hold also blocks reserve release payment');
 $s->resumeObjectPayments(9101,4512,1,'ФКР: снято',$f->op());assertSameValue(200000,$s->markPaid(9101,$explicit['calculationId'],$explicit['revision'],'2026-11-24',$f->op())['paidCents'],'explicit dismissed source receives only released reserve');
 echo "PASS MIKRYUKOVA-FEEDBACK-001 original crew reserve policy\n";
}finally{if($f!==null)$f->close();}
