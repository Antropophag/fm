<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PreopeningFixture.php';
function holdAppendix(string $bytes):string {
 $file=tempnam(sys_get_temp_dir(),'hold-xlsx-');file_put_contents($file,$bytes);$z=new ZipArchive();
 try{assertSameValue(true,$z->open($file)===true,'valid payment workbook');$w=simplexml_load_string($z->getFromName('xl/workbook.xml'));$w->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$id='';foreach($w->xpath('//m:sheet')as$s)if((string)$s['name']==='Приложение к приказу')$id=(string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];$r=simplexml_load_string($z->getFromName('xl/_rels/workbook.xml.rels'));$r->registerXPathNamespace('p','http://schemas.openxmlformats.org/package/2006/relationships');foreach($r->xpath('//p:Relationship')as$link)if((string)$link['Id']===$id)return(string)$z->getFromName('xl/'.(string)$link['Target']);throw new TestFailure('payment appendix absent');}finally{$z->close();unlink($file);}
}
$f=null;$db=null;
try {
 $f=new PreopeningFixture(dirname(__DIR__,2));$f->start();$p=$f->p;$e=$f->environment();
 $db=new yii\db\Connection(['dsn'=>'mysql:host='.$e['FMONITOR_DB_HOST'].';port='.$e['FMONITOR_DB_PORT'].';dbname='.$f->database,'username'=>$e['FMONITOR_DB_USER'],'password'=>$e['FMONITOR_DB_PASSWORD'],'charset'=>'utf8mb4']);$db->open();
 $f->insert($p.'fm_maintable',['id'=>4513,'regnumber'=>'TEST-4513','ordadr_address'=>'Тест 4513']);
 $f->insert($p.'fm2_installation_cases',['id'=>6102,'legacy_installation_object_id'=>4513,'process_state'=>'working','actual_start_date'=>'2026-09-01','created_at'=>'2026-09-01T09:00:00Z','updated_at'=>'2026-09-01T09:00:00Z','lock_version'=>1]);
 $source=['decision'=>'allow','sourceRevision'=>'hold-http-allow','incidentId'=>null,'generation'=>0,'observedAt'=>'2026-09-01T09:00:00Z'];
 foreach([4512,4513]as$id)$f->insert($p.'fm2_otiz_admission_inputs',['object_id'=>$id,'source_revision'=>'hold-http-allow','decision'=>'allow','observed_at'=>'2026-09-01T09:00:00Z']);
 $owner=new FMonitor2\Otiz\OtizSettlementV2($db,$p,static fn()=>'2026-11-24T19:00:00+03:00',static fn()=>$source);$objects=[];
 foreach([4512=>600000,4513=>400000]as$id=>$sum)$objects[]=['objectId'=>$id,'regnumber'=>'TEST-'.$id,'fundCents'=>$sum,'kssBp'=>10000,'entitlements'=>[['sourceKind'=>'fixture','sourceId'=>'hold-http-'.$id,'sourceRevision'=>'1','kind'=>'progress','recognitionDate'=>'2026-11-20','grossCents'=>$sum]],'recipients'=>[['employeeId'=>'A'.$id,'tabNumber'=>(string)$id,'name'=>'Получатель '.$id,'weight'=>1,'employment'=>'employed']]];
 $draft=$owner->createDraft(96,['reportDate'=>'2026-11-24','recipientPolicyVersion'=>'reserved-shares-v1','objects'=>$objects],'24242424-0000-4000-8000-000000001001');$calc=$owner->accept(96,$draft['calculationId'],$draft['revision'],'24242424-0000-4000-8000-000000001002');$id=$calc['calculationId'];
 $actor=[];$f->login($actor,96);$csrf=$f->token($actor);$url='/pilot/otiz/calculations/'.$id;$page=$f->request('GET',$url,[],$actor);
 assertSameValue(200,$page['status'],'accepted calculation reachable');assertSameValue(true,str_contains($page['body'],'Приостановить выплату'),'INTENDED_RED: object hold action is discoverable');
 foreach(['За сроки','Общее удержание','Личное удержание']as$label)assertSameValue(true,str_contains($page['body'],$label),'distinct deduction meaning '.$label);
 $moneyObject=$objects[0];$moneyObject['kssBp']=9000;$moneyObject['entitlements'][0]['sourceId']='deduction-levels';$moneyObject['entitlements'][0]['grossCents']=1000000;$moneyObject['recipients']=[['employeeId'=>'7001','tabNumber'=>'7001','name'=>'Первый','weight'=>60,'employment'=>'employed'],['employeeId'=>'7002','tabNumber'=>'7002','name'=>'Второй','weight'=>40,'employment'=>'employed']];
 $money=$owner->createDraft(96,['reportDate'=>'2026-11-24','recipientPolicyVersion'=>'reserved-shares-v1','objects'=>[$moneyObject]],'24242424-0000-4000-8000-000000001100');
 $money=$owner->saveDeduction(96,$money['calculationId'],$money['revision'],4512,null,100000,'Общее','ФКР-1','24242424-0000-4000-8000-000000001101');
 $money=$owner->saveDeduction(96,$money['calculationId'],$money['revision'],4512,'7001',50000,'Личное','ФКР-2','24242424-0000-4000-8000-000000001102');
 assertSameValue([100000,100000,750000],[$money['objects'][0]['deadlineCents'],$money['objects'][0]['disciplineCents'],$money['totalCents']],'deadline then common then personal exact money');
 $moneyPage=$f->request('GET','/pilot/otiz/calculations/'.$money['calculationId'],[],$actor);$md=new DOMDocument();@$md->loadHTML('<?xml encoding="UTF-8"'.$moneyPage['body']);$mp=new DOMXPath($md);
 foreach(['7001'=>430000,'7002'=>320000]as$employee=>$cents){$row='//*[@data-otiz-allocation-row and @data-object-id="4512" and @data-employee-id="'.$employee.'"]';assertSameValue(true,$mp->query($row.'//*[@data-v2-decision-open]')->length>0,'decision reachable for every employed recipient');assertSameValue((string)$cents,$mp->evaluate('string(('.$row.'//*[@data-allocation-amount-cents])[1]/@data-allocation-amount-cents)'),'personal deduction applies only to exact recipient');}
 foreach(['deadlineCents','kssBp','deadlinePenalty']as$name)assertSameValue(0,$mp->query('//input[@name="'.$name.'"]')->length,'automatic deadline is not a manual deduction input');

 $holdPath='/pilot/otiz/objects/4513/payment-hold';$post=['_csrf'=>$csrf,'operationId'=>'24242424-0000-4000-8000-000000001003','expectedRevision'=>'0','action'=>'hold','reason'=>'ФКР: проверка','calculationId'=>(string)$id];
 $fk=[];$f->login($fk,18);$denied=$f->form($holdPath,array_replace($post,['_csrf'=>$f->token($fk)]),$fk);assertSameValue(403,$denied['status'],'FKR cannot perform OTIZ payment action');
 $noCsrf=$post;unset($noCsrf['_csrf']);assertSameValue(400,$f->form($holdPath,$noCsrf,$actor)['status'],'CSRF required');
 assertSameValue(303,$f->form($holdPath,$post,$actor)['status'],'hold real HTTP');
 $heldPage=$f->request('GET',$url,[],$actor);foreach(['К выплате сейчас','Приостановлено','Уже выплачено','ФКР: проверка']as$label)assertSameValue(true,str_contains($heldPage['body'],$label),'payment consequences visible '.$label);
 $moneyDom=new DOMDocument();@$moneyDom->loadHTML('<?xml encoding="UTF-8"'.$heldPage['body']);$mx=new DOMXPath($moneyDom);
 foreach(['eligible'=>600000,'held'=>400000,'paid'=>0]as$key=>$cents)assertSameValue((string)$cents,$mx->evaluate('string(//*[@data-payment-plan="'.$key.'"]/@data-amount-cents)'),'scoped current payment '.$key);

 $state=$owner->objectPaymentHold(96,4513);assertSameValue([true,1],[$state['held'],$state['revision']],'HTTP persists one hold');
 assertSameValue(303,$f->form($holdPath,$post,$actor)['status'],'hold replay HTTP');assertSameValue(1,$owner->objectPaymentHold(96,4513)['revision'],'replay unchanged');
 $bad=array_replace($post,['operationId'=>'24242424-0000-4000-8000-000000001004','action'=>'resume']);assertSameValue(409,$f->form($holdPath,$bad,$actor)['status'],'stale hold form rejected');
 $beforeExport=$f->facts();$holdBefore=$db->createCommand("SELECT * FROM {$p}fm2_otiz_object_payment_hold_events ORDER BY id")->queryAll(); $book=$f->request('GET',$url.'/export.xlsx?mode=payment',[],$actor);assertSameValue(200,$book['status'],'partial payment export allowed');$appendix=holdAppendix($book['body']);assertSameValue([true,false],[str_contains($appendix,'Получатель 4512'),str_contains($appendix,'Получатель 4513')],'payment appendix excludes held recipient');assertSameValue($beforeExport,$f->facts(),'payment export no business writes');assertSameValue($holdBefore,$db->createCommand("SELECT * FROM {$p}fm2_otiz_object_payment_hold_events ORDER BY id")->queryAll(),'export preserves hold history');
 $pay=['_csrf'=>$csrf,'operationId'=>'24242424-0000-4000-8000-000000001005','expectedRevision'=>(string)$calc['revision'],'paymentDate'=>'2026-11-24'];
 assertSameValue(303,$f->form($url.'/payments',$pay,$actor)['status'],'partial payment HTTP');assertSameValue(600000,$owner->paymentPlan(96,$id)['paidCents'],'only eligible payment marked');
 assertSameValue(409,$f->request('GET',$url.'/export.xlsx?mode=payment',[],$actor)['status'],'all-held remainder cannot produce payable export');
 $resume=array_replace($post,['operationId'=>'24242424-0000-4000-8000-000000001006','expectedRevision'=>'1','action'=>'resume','reason'=>'ФКР: проверка завершена']);assertSameValue(303,$f->form($holdPath,$resume,$actor)['status'],'resume HTTP');
 $resumedPage=$f->request('GET',$url,[],$actor);$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8"'.$resumedPage['body']);$xp=new DOMXPath($dom);assertSameValue(1,$xp->query('//form[@action="'.$url.'/payments"]')->length,'remaining-payment action stays reachable after earlier batch');
 assertSameValue(1,$xp->query('//a[@href="'.$url.'/export.xlsx?mode=payment"]')->length,'remaining-payment Excel link stays reachable after resume');
 $book=$f->request('GET',$url.'/export.xlsx?mode=payment',[],$actor);$appendix=holdAppendix($book['body']);assertSameValue([false,true],[str_contains($appendix,'Получатель 4512'),str_contains($appendix,'Получатель 4513')],'resumed payment appendix excludes previously paid recipient');
 $pay['operationId']='24242424-0000-4000-8000-000000001007';assertSameValue(303,$f->form($url.'/payments',$pay,$actor)['status'],'remaining payment HTTP');assertSameValue(1000000,$owner->paymentPlan(96,$id)['paidCents'],'exact total after both payments');
 $reserveObject=$moneyObject;$reserveObject['entitlements'][0]['sourceId']='reserve-http';$reserveObject['kssBp']=10000;$reserveObject['recipients'][1]['employment']='dismissed';
 $reserve=$owner->createDraft(96,['reportDate'=>'2026-11-24','recipientPolicyVersion'=>'reserved-shares-v1','objects'=>[$reserveObject]],'24242424-0000-4000-8000-000000001200');$reserve=$owner->accept(96,$reserve['calculationId'],$reserve['revision'],'24242424-0000-4000-8000-000000001201');
 $releasePath='/pilot/otiz/calculations/'.$reserve['calculationId'].'/reserve-releases';$releasePost=['_csrf'=>$csrf,'operationId'=>'24242424-0000-4000-8000-000000001202','objectId'=>'4512','sourceEmployeeId'=>'7002','releaseMode'=>'original_crew','expectedRevision'=>(string)$reserve['revision'],'reason'=>'ОТиЗ: распределить'];
 $beforeRelease=$f->facts();assertSameValue(403,$f->form($releasePath,array_replace($releasePost,['_csrf'=>$f->token($fk)]),$fk)['status'],'FKR cannot redistribute reserve');assertSameValue($beforeRelease,$f->facts(),'unauthorized reserve no writes');
 assertSameValue(409,$f->form($releasePath,array_replace($releasePost,['expectedRevision'=>'0']),$actor)['status'],'stale reserve HTTP');assertSameValue($beforeRelease,$f->facts(),'stale reserve no writes');
 assertSameValue(303,$f->form($releasePath,$releasePost,$actor)['status'],'authoritative default redistribution HTTP');assertSameValue(400000,$owner->read($reserve['calculationId'])['remainingReservedCents'],'HTTP draft has not consumed reserve');

 echo "PASS MIKRYUKOVA-FEEDBACK-001 payment HTTP/export\n";
}finally{if($db!==null)$db->close();if($f!==null)$f->close();}
