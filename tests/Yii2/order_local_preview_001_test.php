<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/PreopeningFixture.php';
// ORDER-LOCAL-PREVIEW-001: independent root-authored acceptance through real Yii HTTP.
$f=null;
try {
 $f=new PreopeningFixture(dirname(__DIR__,2));$f->start();$cookies=[];
 assertSameValue(303,$f->login($cookies)['status'],'native authorized login');
 $card=$f->request('GET','/pilot/objects/4512',[],$cookies);
 assertSameValue(200,$card['status'],'object card');
 assertSameValue(false,str_contains($card['body'],'Основание срока: Договорный норматив'),'normative period is a compact fact');
 assertSameValue(303,$f->selection($cookies)['status'],'save composition');
 $selection=$f->request('GET','/pilot/objects/4512/assignment-order/selection',[],$cookies);
 $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$selection['body']);$xp=new DOMXPath($dom);
 assertSameValue(0,$xp->query('//aside[contains(@class,"fm2-order-helper")]//button | //aside[contains(@class,"fm2-order-helper")]//form')->length,'helper has no action forms/buttons');
 assertSameValue(1,$xp->query('//button[contains(@class,"shlz-button--primary") and contains(.,"Сформировать шаблон")]')->length,'one blue generate action');
 assertSameValue(1,$xp->query('//input[@name="appendixStartDate" and @type="date" and @required and (@form="assignment-order-template-form" or ancestor::form[@id="assignment-order-template-form"]) and @value="2026-10-01"]')->length,'INTENDED_RED work-start date chosen before template, initially from plan');
 $chosenStart='2026-11-30';
 assertSameValue(1,$xp->query('//section[.//h2[normalize-space(.)="Состав распоряжения"]]//button[@type="submit" and contains(.,"Сохранить изменения")]')->length,'INTENDED_RED save composition belongs with crew controls');
 assertSameValue(1,$xp->query('//section[.//h2[normalize-space(.)="Состав распоряжения"]]//*[@data-main-selection]')->length,'crew and save share the section');
 assertSameValue(1,$xp->query('//section[.//h2[normalize-space(.)="Шаблон распоряжения"]]//input[@name="appendixStartDate"]')->length,'start date belongs to template section');
 assertSameValue(1,$xp->query('//section[.//h2[normalize-space(.)="Шаблон распоряжения"]]//button[contains(.,"Сформировать шаблон")]')->length,'generate belongs to template section');
 assertSameValue(1,$xp->query('//section[.//h2[normalize-space(.)="Подписанный оригинал"]]//a[contains(.,"Загрузить оригинал")]')->length,'upload belongs to original section');
 assertSameValue(0,$xp->query('//section[.//h2[normalize-space(.)="Состав распоряжения"]]//a[contains(.,"Загрузить оригинал")]')->length,'upload is not mixed with crew');
 $path='/pilot/objects/4512/assignment-orders/81/template';
 foreach([[],['appendixStartDate'=>''],['appendixStartDate'=>'2026-02-30'],['appendixStartDate'=>['2026-11-30']]]as$bad){$fields=['_csrf'=>$f->token($cookies)]+$bad;$before=$f->facts();$badResult=$f->form($path,$fields,$cookies);assertSameValue(true,in_array($badResult['status'],[400,422],true),'invalid/missing template date rejected');assertSameValue($before,$f->facts(),'invalid template date changes no facts');}

 $csrf=$f->token($cookies);$before=$f->facts();$duplicate=$f->request('POST',$path,[],$cookies,['Content-Type: application/x-www-form-urlencoded'],'_csrf='.rawurlencode($csrf).'&appendixStartDate=2026-11-30&appendixStartDate=2026-11-29');assertSameValue(400,$duplicate['status'],'duplicate date is ambiguous and rejected');assertSameValue($before,$f->facts(),'duplicate date adds no facts');
 $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
 $generated=$f->form($path,['_csrf'=>$f->token($cookies),'appendixStartDate'=>$chosenStart],$cookies);
 assertSameValue(200,$generated['status'],'generate template');
 assertSameValue(true,str_starts_with($generated['body'],'%PDF-'),'actual PDF');
 FMonitor2\Tests\Support\TemplateGenerationFixture::pdfMarker($generated['body'],'ФН81');
 FMonitor2\Tests\Support\TemplateGenerationFixture::pdfMarker($generated['body'],'30.11.2026');
 $objectPlan=$f->db->query('SELECT workdatestart,plan_finish_date FROM '.$f->p.'fm_maintable WHERE id=4512')->fetch_row();assertSameValue(['2026-10-01','2026-12-01'],$objectPlan,'template choice does not rewrite object plan');
 $selectionAgain=$f->request('GET','/pilot/objects/4512/assignment-order/selection',[],$cookies);$dateDom=new DOMDocument();@$dateDom->loadHTML('<?xml encoding="UTF-8">'.$selectionAgain['body']);assertSameValue(1,(new DOMXPath($dateDom))->query('//input[@name="appendixStartDate" and @value="2026-11-30"]')->length,'saved template date survives page reload');

 $facts=$f->facts();
 foreach([1,2] as $repeat){$download=$f->request('GET',$path,[],$cookies);assertSameValue(200,$download['status'],'download saved template');assertSameValue($generated['body'],$download['body'],'identical saved bytes');}
 assertSameValue($facts,$f->facts(),'downloads create no new facts');
 // Independent injected clock proves that upload uses the saved formation day, not today's clock.
 $clock=new class implements FMonitor2\AssignmentOrderComposition\SelectionClock {
  public function now():FMonitor2\AssignmentOrderComposition\SelectionInstantLookup{return FMonitor2\AssignmentOrderComposition\SelectionInstantLookup::found(new FMonitor2\AssignmentOrderComposition\SelectionInstant('2026-09-04T20:59:00Z'));}
 };
 $renderer=new FMonitor2\InstallationProcess\ProductionPdfAssignmentOrderRenderer();
 $application=FMonitor2\AssignmentOrderComposition\AssignmentOrderTemplateVerificationFactory::create($f->db,$f->p,$clock,$renderer->renderAssignmentOrder(...));
 assertSameValue('generated',$application->generateAssignmentOrderTemplate(6101,81,18,$chosenStart)['status'],'known earlier template generation');
 $today='2026-09-04';
 assertSameValue('rejected',$application->downloadLastAssignmentOrderTemplate(9999,81,18)['status'],'template tied to its case');
 $form=$f->request('GET','/pilot/objects/4512/assignment-orders/81/originals/submit',[],$cookies);
 assertSameValue(200,$form['status'],'upload form');
 assertSameValue(1,substr_count($form['body'],'Монтажник 7001'),'INTENDED_RED original upload shows each installer once');
 $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$form['body']);$xp=new DOMXPath($dom);
 foreach(['documentDate','compositionConfirmed'] as $name)assertSameValue(0,$xp->query('//*[@name="'.$name.'"]')->length,'removed manual input '.$name);
 assertSameValue(1,$xp->query('//input[@name="externalDocumentNumber" and @required and @maxlength="255"]')->length,'required generous 1C number');
 assertSameValue(1,$xp->query('//input[@name="appendixStartDate" and @value="2026-11-30"]')->length,'upload defaults to selected printed date rather than object plan');
 $metadata=['csrfToken'=>$f->token($cookies),'requestId'=>'22222222-2222-4222-8222-000000000001','mode'=>'initial','appendixStartDate'=>'2026-09-02','externalDocumentNumber'=>'0007-Р/ДО № 15','rootOriginalId'=>null,'targetRevisionId'=>null,'expectedCurrentRevisionId'=>null,'correctionReason'=>null,'originalFilename'=>'signed.pdf'];
 foreach(['',str_repeat('Я',256)] as $badNumber){$bad=$metadata;$bad['externalDocumentNumber']=$badNumber;$before=$f->facts();$badResult=$f->upload($cookies,$bad);assertSameValue(true,in_array($badResult['status'],[400,422],true),'invalid number rejected');assertSameValue($before,$f->facts(),'invalid number writes no facts');}
 $authorName='Автор <оригинала>'; $authorStatement=$f->db->prepare('UPDATE '.$f->p.'fm2_pilot_users SET full_name=? WHERE user_id=18');$authorStatement->execute([$authorName]);
 $result=$f->upload($cookies,$metadata);assertSameValue(201,$result['status'],'accept original with external number');
 $receipt=json_decode($result['body'],true,flags:JSON_THROW_ON_ERROR);
 assertSameValue($today,$receipt['documentDate'],'server uses generated date');
 $history=$f->request('GET','/pilot/objects/4512/assignment-orders/81/originals/history',[],$cookies);
 assertSameValue(200,$history['status'],'history available');assertSameValue(true,str_contains($history['body'],'0007-Р/ДО № 15'),'external number roundtrip');
 assertSameValue(true,str_contains($history['body'],'Загрузил: Автор &lt;оригинала&gt;'),'INTENDED_RED history resolves and escapes revision author');
 assertSameValue(false,str_contains($history['body'],'пользователь ID'),'history hides technical author ID');
 $readerCookies=[];assertSameValue(303,$f->login($readerCookies,73)['status'],'history reader login');$otherView=$f->request('GET','/pilot/objects/4512/assignment-orders/81/originals/history',[],$readerCookies);
 assertSameValue(200,$otherView['status'],'other reader history');assertSameValue(true,str_contains($otherView['body'],'Загрузил: Автор &lt;оригинала&gt;'),'author is uploader, not viewer');
 $authorStatement->execute([' ']);$fallbackHistory=$f->request('GET','/pilot/objects/4512/assignment-orders/81/originals/history',[],$cookies);
 assertSameValue(true,str_contains($fallbackHistory['body'],'Загрузил: '.$f->emails[18]),'blank author name falls back to email');$authorStatement->execute([$authorName]);

 assertSameValue(200,$f->form($path,['_csrf'=>$f->token($cookies),'appendixStartDate'=>$chosenStart],$cookies)['status'],'later explicit template generation');
 $facts=$f->facts();$replayed=$f->upload($cookies,$metadata);assertSameValue(200,$replayed['status'],'exact upload replay after new template date');assertSameValue('2026-09-04',json_decode($replayed['body'],true)['documentDate'],'replay retains original formation day');assertSameValue($facts,$f->facts(),'replay preserves facts');
 $changed=$metadata;$changed['externalDocumentNumber']='changed';$conflict=$f->upload($cookies,$changed);assertSameValue(true,in_array($conflict['status'],[409,422],true),'changed request number conflicts');assertSameValue($facts,$f->facts(),'conflict leaves history unchanged');
 $priorRevisions=$f->rows('fm2_assignment_order_original_revisions');$priorCase=$f->rows('fm2_installation_cases')[0];
 $correction=$metadata;$correction['requestId']='33333333-3333-4333-8333-000000000001';$correction['mode']='correction';$correction['externalDocumentNumber']=$metadata['externalDocumentNumber'];$correction['rootOriginalId']=$receipt['rootOriginalId'];$correction['targetRevisionId']=$receipt['currentRevisionId'];$correction['expectedCurrentRevisionId']=$receipt['currentRevisionId'];$correction['correctionReason']='Уточнён подписанный документ';
 $correctedPdf=FMonitor2\Tests\Support\AssignmentOrderOriginalPdfCorpus::classic(['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 144 144] >>']);
 foreach(['externalDocumentNumber'=>'0008-Р/ДО','appendixStartDate'=>'2026-09-03'] as $key=>$value){$tampered=$correction;$tampered[$key]=$value;$beforeReplacement=$f->facts();assertSameValue(422,$f->upload($cookies,$tampered,$correctedPdf)['status'],'INTENDED_RED scan replacement cannot change '.$key);assertSameValue($beforeReplacement,$f->facts(),'rejected scan metadata writes no facts');}
 assertSameValue(201,$f->upload($cookies,$correction,$correctedPdf)['status'],'replacement scan with same document requisites');
 $revisions=$f->rows('fm2_assignment_order_original_revisions');assertSameValue(2,count($revisions),'correction adds one revision');assertSameValue($priorRevisions[0],$revisions[0],'original revision immutable');assertSameValue($revisions[0]['document_date'],$revisions[1]['document_date'],'scan replacement preserves document date despite later template');
 $afterCase=$f->rows('fm2_installation_cases')[0];foreach(['actual_start_date','opened_at','opened_by_user_id']as$key)assertSameValue($priorCase[$key],$afterCase[$key],'correction preserves opening '.$key);
 $history=$f->request('GET','/pilot/objects/4512/assignment-orders/81/originals/history',[],$cookies);foreach(['0007-Р/ДО № 15']as$number)assertSameValue(true,str_contains($history['body'],$number),'both revision numbers retained');
 $correctionForm=$f->request('GET','/pilot/objects/4512/assignment-orders/81/originals/submit',[],$cookies);assertSameValue(true,str_contains($correctionForm['body'],'value="0007-Р/ДО № 15"'),'current number prefilled');assertSameValue(1,substr_count($correctionForm['body'],'Монтажник 7001'),'original correction shows each installer once');
 $scanDom=new DOMDocument();@$scanDom->loadHTML('<?xml encoding="UTF-8">'.$correctionForm['body']);$scanXp=new DOMXPath($scanDom);
 foreach(['externalDocumentNumber','appendixStartDate'] as $name){assertSameValue(1,$scanXp->query('//input[@name="'.$name.'" and @type="hidden"]')->length,'scan replacement retains hidden immutable '.$name);assertSameValue(0,$scanXp->query('//input[@name="'.$name.'" and not(@type="hidden")]')->length,'no editable document requisite '.$name);}
 assertSameValue(true,str_contains($correctionForm['body'],'Заменить скан'),'replacement action names file operation');
 assertSameValue(false,str_contains($history['body'],'К форме оригинала'),'no vague history navigation');
 assertSameValue(true,str_contains($history['body'],'Заменить скан'),'history exposes scan replacement');
 assertSameValue(true,str_contains($history['body'],'Новое распоряжение'),'history distinguishes new order');
 assertSameValue(200,$f->upload($cookies,$correction,$correctedPdf)['status'],'scan replacement replay stays idempotent');
 $deniedCookies=[];assertSameValue(303,$f->login($deniedCookies,95)['status'],'reader login');$before=$f->facts();assertSameValue(403,$f->request('GET',$path,[],$deniedCookies)['status'],'template download requires permission');assertSameValue($before,$f->facts(),'denied download writes no facts');
 $acceptedSelection=$f->request('GET','/pilot/objects/4512/assignment-order/selection',[],$cookies);assertSameValue(true,str_contains($acceptedSelection['body'],'Создать новое распоряжение'),'accepted selection offers explicit new order');
 $oldOriginals=$f->rows('fm2_assignment_order_original_revisions');
 assertSameValue(303,$f->selection($cookies,'44444444-4444-4444-8444-000000000001',[7001],'new_order',1)['status'],'new order can retain same crew');
 $orders=$f->rows('fm2_assignment_order_selections');assertSameValue(2,count($orders),'new order gets separate identity');assertSameValue(false,$orders[0]['assignment_order_id']===$orders[1]['assignment_order_id'],'new order has different FN identity');assertSameValue($oldOriginals,$f->rows('fm2_assignment_order_original_revisions'),'new order preserves prior signed scans');
 echo "PASS ORDER-LOCAL-PREVIEW-001 real card/template/download/upload/history/replay\n";
} finally {if($f instanceof PreopeningFixture)$f->close();}
