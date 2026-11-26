<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\Otiz\{ReclamationDeadline,PremiumCalculationV2};
assertSameValue(true,class_exists(ReclamationDeadline::class),'INTENDED_RED reclamation deadline resolver exists');
$source=['label'=>'Fixture','locator'=>'fixture/reclamations','contentSha256'=>str_repeat('a',64)];
$items=[];foreach([3,4]as$i=>$days)$items[]=['id'=>$i+1,'installationCaseId'=>6101,'number'=>'Р-'.($i+1),'delayDays'=>$days,'actorId'=>18,'recordedAt'=>'2026-11-26T00:00:00Z'];
$claims=['items'=>$items,'totalDays'=>7,'source'=>$source];
$r=ReclamationDeadline::resolve('2026-09-29',null,$claims);
assertSameValue(['2026-09-29','normative',7,'2026-10-06'],[$r['baseDeadline'],$r['baseBasis'],$r['reclamationDays'],$r['effectiveDeadline']],'two claims extend normative deadline');
$certificate=['newDeadline'=>'2026-10-08','certificateDate'=>'2026-09-25','source'=>$source];
$r=ReclamationDeadline::resolve('2026-09-29',$certificate,$claims);
assertSameValue(['2026-10-08','certificate','2026-10-15'],[$r['baseDeadline'],$r['baseBasis'],$r['effectiveDeadline']],'owner rule certificate plus all claim days');
assertSameValue($r,ReclamationDeadline::resolve('2026-09-29',$certificate,$claims),'deterministic evidence');
$one=$claims;$one['items']=[$items[0]];$one['items'][0]['delayDays']=1;$one['totalDays']=1;
assertSameValue('2028-02-29',ReclamationDeadline::resolve('2028-02-28',null,$one)['effectiveDeadline'],'calendar leap day');
$empty=['items'=>[],'totalDays'=>0,'source'=>$source];assertSameValue('2026-09-29',ReclamationDeadline::resolve('2026-09-29',null,$empty)['effectiveDeadline'],'empty real register preserves deadline');
foreach(['2026-10-14'=>[0,10000],'2026-10-15'=>[0,10000],'2026-10-20'=>[5,9500],'2027-03-01'=>[137,0]]as$day=>$expected){$fact=static fn($v)=>['value'=>$v,'effectiveDate'=>'2026-09-01','source'=>$source];$o=['reportDate'=>$fact($day),'premiumCents'=>$fact(100000),'shaftBp'=>$fact(10000),'progressBp'=>$fact(10000),'deadlineDate'=>$fact($r['effectiveDeadline']),'completionDate'=>$fact(null)];$c=PremiumCalculationV2::calculate($o,['closures'=>[],'actualPayouts'=>[]]);assertSameValue($expected,[$c['daysLate'],$c['kssBp']],'excused delay/zero/cap '.$day);if($expected[0]===0)assertSameValue(0,$c['amounts']['deadlinePenaltyCents'],'no delay deduction within extended date');}
$bad=$claims;$bad['totalDays']=6;$refused=false;try{ReclamationDeadline::resolve('2026-09-29',null,$bad);}catch(InvalidArgumentException|DomainException){$refused=true;}assertSameValue(true,$refused,'inconsistent evidence fails closed');
$overflow=false;try{ReclamationDeadline::resolve('9999-12-31',null,$one);}catch(InvalidArgumentException|DomainException){$overflow=true;}assertSameValue(true,$overflow,'date overflow rejected');
echo "PASS RECLAMATION-DELAY-001 pure deadline and penalty\n";
