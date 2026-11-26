<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/PreopeningFixture.php';
use FMonitor2\EquipmentReclamation\EquipmentReclamations;
assertSameValue(true,class_exists(EquipmentReclamations::class),'INTENDED_RED reclamation application owner exists');
$f=null;
try{
 $f=new PreopeningFixture(dirname(__DIR__,2));$db=$f->db;$p=$f->p;
 FMonitor2\Tests\Support\DeadlineCertificateFixture::grants($db,$p);
 $service=new EquipmentReclamations($db,$p,static fn()=>'2026-11-26T12:00:00Z');
 $first=['requestId'=>'51515151-0000-4000-8000-000000000001','installationCaseId'=>6101,'actorId'=>18,'number'=>'РЕК-<001>','delayDays'=>3];
 assertSameValue(0,$service->read(18,6101)['totalDays'],'real empty register');
 $added=$service->add($first);assertSameValue('added',$added['status'],'add first claim');
 $rows=$f->rows('fm2_equipment_reclamations');assertSameValue(1,count($rows),'one durable claim');
 assertSameValue('replayed',$service->add($first)['status'],'same request replay');assertSameValue($rows,$f->rows('fm2_equipment_reclamations'),'replay never duplicates');
 foreach([['delayDays'=>0],['delayDays'=>-1],['delayDays'=>1.5],['delayDays'=>'2'],['number'=>' '],['number'=>str_repeat('Я',256)],['number'=>[]],['delayDays'=>65536]]as$bad){$denied=false;try{$service->add(array_replace($first,['requestId'=>'51515151-0000-4000-8000-000000000099'],$bad));}catch(DomainException|InvalidArgumentException){$denied=true;}assertSameValue(true,$denied,'invalid claim refused');}
 foreach([array_replace($first,['delayDays'=>4]),array_replace($first,['requestId'=>'51515151-0000-4000-8000-000000000002','number'=>'рек-<001>'])]as$bad){$denied=false;try{$service->add($bad);}catch(DomainException){$denied=true;}assertSameValue(true,$denied,'changed request or duplicate number refused');}
 $denied=false;try{$service->add(array_replace($first,['actorId'=>96,'requestId'=>'51515151-0000-4000-8000-000000000003','number'=>'DENIED']));}catch(DomainException$e){$denied=$e->getMessage()==='FORBIDDEN';}assertSameValue(true,$denied,'OTIZ read role cannot add claims');assertSameValue($rows,$f->rows('fm2_equipment_reclamations'),'all refusals conserve claims');
 assertSameValue(3,$service->read(96,6101)['totalDays'],'OTIZ reads grounds');
 $f->start();$cookies=[];assertSameValue(303,$f->login($cookies)['status'],'FKR login');$path='/pilot/objects/4512/reclamations';
 $form=['_csrf'=>$f->token($cookies),'requestId'=>'51515151-0000-4000-8000-000000000004','number'=>'РЕК-002','delayDays'=>'4'];
 assertSameValue(303,$f->form($path,$form,$cookies)['status'],'HTTP add second reclamation');assertSameValue(7,$service->read(18,6101)['totalDays'],'multiple reclamations summed');
 assertSameValue(303,$f->form($path,$form,$cookies)['status'],'HTTP safe replay');assertSameValue(2,count($f->rows('fm2_equipment_reclamations')),'HTTP replay no duplicate');
 $before=$f->rows('fm2_equipment_reclamations');
 foreach(['0','-1','1.5','1e2']as$bad){$invalid=$form;$invalid['requestId']='51515151-0000-4000-8000-000000000005';$invalid['number']='BAD';$invalid['delayDays']=$bad;$invalidResult=$f->form($path,$invalid,$cookies);assertSameValue(422,$invalidResult['status'],'invalid HTTP integer refused');assertSameValue(true,str_contains($invalidResult['body'],'value="BAD"'),'validation preserves entered number');assertSameValue(true,str_contains(mb_strtolower($invalidResult['body']),'целое'),'validation explains whole days');}
 $duplicate=$form;$duplicate['requestId']='51515151-0000-4000-8000-000000000006';$duplicate['number']='рек-<001>';$duplicateResult=$f->form($path,$duplicate,$cookies);assertSameValue(409,$duplicateResult['status'],'duplicate number HTTP conflict');assertSameValue(true,str_contains(mb_strtolower($duplicateResult['body']),'уже'),'duplicate explains existing record');
 $noCsrf=$form;$noCsrf['_csrf']='bad';assertSameValue(400,$f->form($path,$noCsrf,$cookies)['status'],'CSRF required');
 $reader=[];$f->login($reader,96);$forbidden=$form;$forbidden['_csrf']=$f->token($reader);assertSameValue(403,$f->form($path,$forbidden,$reader)['status'],'HTTP write denied to OTIZ');assertSameValue($before,$f->rows('fm2_equipment_reclamations'),'HTTP refusals no new rows');
 $page=$f->request('GET','/pilot/objects/4512',[],$cookies);assertSameValue(200,$page['status'],'card supports claims');foreach(['Добавить рекламацию','РЕК-&lt;001&gt;','РЕК-002']as$text)assertSameValue(true,str_contains($page['body'],$text),'visible safe claim UI '.$text);
 $evidence=$service->currentEvidence(6101);assertSameValue($evidence,$service->currentEvidence(6101),'stable evidence hash/read');assertSameValue([3,4],array_column($evidence['items'],'delayDays'),'ordered source days');
 assertSameValue(false,FMonitor2\InstallationProcess\EquipmentReclamationSchemaMigration::apply($db,$p)['applied'],'migration repeat no-op');
 echo "PASS RECLAMATION-DELAY-001 ledger and HTTP\n";
}finally{if($f)$f->close();}
