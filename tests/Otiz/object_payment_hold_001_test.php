<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\Tests\Support\PaymentHoldFixture;
function holdDenied(string $code,callable $f):void {try{$f();throw new TestFailure('Accepted '.$code);}catch(DomainException $e){assertSameValue($code,$e->getMessage(),'exact denial');}}
$f=null;
try {
    $f=new PaymentHoldFixture();$s=$f->owner;
    assertSameValue(true,method_exists($s,'holdObjectPayments'),'INTENDED_RED: object hold public owner');
    foreach([1,2]as$run){$m=\FMonitor2\InstallationProcess\CanonicalMigrationApplication::run($f->base->db,$f->p,\FMonitor2\InstallationProcess\ProductionPilotMigrationCatalogue::migrations());assertSameValue([0,43],[$m['exitCode'],$m['result']['schemaVersion']??null],'canonical payment-control schema/repeat');}
    foreach(['fm2_otiz_object_payment_hold_events','fm2_otiz_payment_allocations']as$table){$ddl=$f->db->createCommand('SHOW CREATE TABLE '.$f->p.$table)->queryOne();assertSameValue(true,str_contains(implode(' ',$ddl),'CHECK'),'schema protects monetary/hold domain values');}
    $a=$f->accepted();$id=$a['calculationId'];$rev=$a['revision'];
    $saved=$f->db->createCommand('SELECT projection_json FROM '.$f->p.'fm2_otiz_calculation_revisions WHERE id=:id',[':id'=>$id])->queryScalar();
    assertSameValue([0,false],array_values(array_intersect_key($s->objectPaymentHold(9101,4513),array_flip(['revision','held']))),'initial hold state');
    holdDenied('FORBIDDEN',fn()=>$s->holdObjectPayments(0,4513,0,'ФКР',$f->op()));
    holdDenied('REASON_REQUIRED',fn()=>$s->holdObjectPayments(9101,4513,0,' ',$f->op()));
    holdDenied('OBJECT_NOT_FOUND',fn()=>$s->holdObjectPayments(9101,999999,0,'ФКР',$f->op()));
    $op=$f->op();$held=$s->holdObjectPayments(9101,4513,0,'ФКР: проверка документов',$op);
    assertSameValue($held,$s->holdObjectPayments(9101,4513,0,'ФКР: проверка документов',$op),'hold replay');
    assertSameValue([1,true],[$held['revision'],$held['held']],'hold revision');
    holdDenied('OPERATION_CONFLICT',fn()=>$s->holdObjectPayments(9101,4513,0,'Другое основание',$op));
    $audit=$f->db->createCommand("SELECT * FROM {$f->p}fm2_otiz_object_payment_hold_events ORDER BY id")->queryAll();
    assertSameValue([4513,1,1,9101,'ФКР: проверка документов'],[(int)$audit[0]['object_id'],(int)$audit[0]['revision'],(int)$audit[0]['held'],(int)$audit[0]['actor_user_id'],$audit[0]['reason']],'hold audit identity author and reason');
    assertSameValue(true,str_starts_with($audit[0]['created_at'],'2026-11-24'),'hold timestamp');
    holdDenied('STALE_OBJECT_PAYMENT_HOLD',fn()=>$s->resumeObjectPayments(9101,4513,0,'ФКР: снято',$f->op()));
    $plan=$s->paymentPlan(9101,$id);
    assertSameValue([600000,400000,0,1000000],[$plan['eligibleCents'],$plan['heldCents'],$plan['paidCents'],$plan['remainingCents']],'eligible and held partition');
    $payOp=$f->op();$first=$s->markPaid(9101,$id,$rev,'2026-11-24',$payOp);
    assertSameValue(600000,$first['paidCents'],'pays only nonheld object');
    assertSameValue($first,$s->markPaid(9101,$id,$rev,'2026-11-24',$payOp),'payment replay exact');
    $plan=$s->paymentPlan(9101,$id);assertSameValue([0,400000,600000,400000],[$plan['eligibleCents'],$plan['heldCents'],$plan['paidCents'],$plan['remainingCents']],'partial balances');
    $monthly=(new \FMonitor2\Otiz\MariaDbOtizMonthlyTotals($f->db,$f->p))->read(9101,'2026-11',[4512,4513]);$monthlyObjects=array_column($monthly['objects'],null,'objectId');
    assertSameValue([false,600000,0],[$monthly['incomplete'],$monthlyObjects[4512]['paidCents']??0,$monthlyObjects[4513]['paidCents']??0],'monthly actual partial allocation');
    $economy=(new \FMonitor2\Otiz\OtizPortfolioEconomy($f->db,$f->p,$f->p))->read(9101,[]);$economyRows=array_column($economy['rows'],null,'objectId');
    assertSameValue([600000,0,0,400000],[$economyRows[4512]['paidCents'],$economyRows[4512]['payableCents'],$economyRows[4513]['paidCents'],$economyRows[4513]['payableCents']],'portfolio paid and owing per object');
    assertSameValue([600000,400000],[$economy['summary']['paidCents'],$economy['summary']['payableCents']],'portfolio summary uses actual partial allocations');
    $waiting=(new \FMonitor2\Otiz\MariaDbOtizCalculationRegister($f->db,$f->p))->read('waiting','all',1);
    assertSameValue([1,600000,1000000],[$waiting['total'],(int)$waiting['rows'][0]['paid_cents'],(int)$waiting['rows'][0]['obligation_cents']],'partial stays waiting with exact paid amount');
    $detail=$f->db->createCommand("SELECT o.object_id,a.amount_cents FROM {$f->p}fm2_otiz_payment_allocations a JOIN {$f->p}fm2_otiz_recipient_obligations o ON o.id=a.obligation_id WHERE a.payment_id=:id ORDER BY o.object_id",[':id'=>$first['paymentId']])->queryAll();
    assertSameValue([[4512,600000]],array_map(fn($r)=>[(int)$r['object_id'],(int)$r['amount_cents']],$detail),'first payment detail links only paid object');
    $before=$f->count('fm2_otiz_payment_facts');holdDenied('PAYMENT_HELD',fn()=>$s->markPaid(9101,$id,$rev,'2026-11-24',$f->op()));assertSameValue($before,$f->count('fm2_otiz_payment_facts'),'all held makes no zero payment');
    $secondCalculation=$f->accepted('second');$plan2=$s->paymentPlan(9101,$secondCalculation['calculationId']);assertSameValue(400000,$plan2['heldCents'],'hold persists across future calculations');
    $s->resumeObjectPayments(9101,4513,1,'ФКР: документы проверены',$f->op());
    assertSameValue($audit[0],$f->db->createCommand("SELECT * FROM {$f->p}fm2_otiz_object_payment_hold_events ORDER BY id LIMIT 1")->queryOne(),'resume never rewrites first event');
    $resumeAudit=$f->db->createCommand("SELECT * FROM {$f->p}fm2_otiz_object_payment_hold_events ORDER BY id DESC LIMIT 1")->queryOne();
    assertSameValue([4513,2,0,9101,'ФКР: документы проверены','2026-11-24T19:00:00+03:00'],[(int)$resumeAudit['object_id'],(int)$resumeAudit['revision'],(int)$resumeAudit['held'],(int)$resumeAudit['actor_user_id'],$resumeAudit['reason'],$resumeAudit['created_at']],'resume exact audit provenance');
    assertSameValue('2026-11-24T19:00:00+03:00',$audit[0]['created_at'],'hold exact clock instant');
    $resumed=$s->objectPaymentHold(9101,4513);assertSameValue([2,false,'ФКР: документы проверены'],[$resumed['revision'],$resumed['held'],$resumed['reason']],'current resumed state and provenance');

    $second=$s->markPaid(9101,$id,$rev,'2026-11-24',$f->op());assertSameValue(400000,$second['paidCents'],'resume pays only remainder');
    $plan=$s->paymentPlan(9101,$id);assertSameValue([1000000,0],[$plan['paidCents'],$plan['remainingCents']],'exact fully paid total');
    $monthly=(new \FMonitor2\Otiz\MariaDbOtizMonthlyTotals($f->db,$f->p))->read(9101,'2026-11',[4512,4513]);assertSameValue([false,1000000],[$monthly['incomplete'],array_sum(array_column($monthly['objects'],'paidCents'))],'monthly multiple batches remain complete');
    $detail=$f->db->createCommand("SELECT o.object_id,a.amount_cents FROM {$f->p}fm2_otiz_payment_allocations a JOIN {$f->p}fm2_otiz_recipient_obligations o ON o.id=a.obligation_id WHERE a.payment_id=:id",[':id'=>$second['paymentId']])->queryAll();assertSameValue([[4513,400000]],array_map(fn($r)=>[(int)$r['object_id'],(int)$r['amount_cents']],$detail),'second payment detail only remainder');
    assertSameValue('no_change',$s->markPaid(9101,$id,$rev,'2026-11-24',$f->op())['status'],'no duplicate after fully paid');
    $s->reversePayment(9101,$first['paymentId'],'Ошибочная отметка первого платежа',$f->op(),'erroneous_mark');
    $plan=$s->paymentPlan(9101,$id);$objects=array_column($plan['objects'],null,'objectId');
    assertSameValue([600000,0,400000],[$objects[4512]['remainingCents'],$objects[4513]['remainingCents'],$plan['paidCents']],'subset reversal leaves other object paid');
    $monthly=(new \FMonitor2\Otiz\MariaDbOtizMonthlyTotals($f->db,$f->p))->read(9101,'2026-11',[4512,4513]);assertSameValue(400000,array_sum(array_column($monthly['objects'],'paidCents')),'monthly subtracts only reversed subset');
    assertSameValue(600000,$s->markPaid(9101,$id,$rev,'2026-11-24',$f->op())['paidCents'],'repay reversed subset exactly');
    assertSameValue($saved,$f->db->createCommand('SELECT projection_json FROM '.$f->p.'fm2_otiz_calculation_revisions WHERE id=:id',[':id'=>$id])->queryScalar(),'hold/pay/reverse never rewrite accepted calculation');
    $rows=$f->db->createCommand("SELECT p.id,p.amount_cents,COALESCE(SUM(a.amount_cents),0) allocated FROM {$f->p}fm2_otiz_payment_facts p LEFT JOIN {$f->p}fm2_otiz_payment_allocations a ON a.payment_id=p.id GROUP BY p.id,p.amount_cents")->queryAll();
    foreach($rows as$row)assertSameValue((int)$row['amount_cents'],(int)$row['allocated'],'payment header equals allocation sum');
    assertSameValue(2,$f->count('fm2_otiz_object_payment_hold_events'),'append-only hold/resume history, rejected operations neutral');
    echo "PASS MIKRYUKOVA-FEEDBACK-001 object hold/partial/reversal\n";
} finally {if($f!==null)$f->close();}
