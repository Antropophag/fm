<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Yii2/InspectionFixture.php';
use FMonitor2\Otiz\{MariaDbNativePremiumInputs,PremiumCalculationV2};

$fixture=null;
try {
    $fixture=new InspectionFixture(dirname(__DIR__,2));$fixture->open();
    $h=$fixture->http;$db=$h->db;$p=$h->p;
    $payload=json_decode($db->query("SELECT payload_json FROM {$p}fm2_pilot_object_details WHERE object_id=4512")->fetch_column(),true,flags:JSON_THROW_ON_ERROR);
    foreach (['floors'=>12,'stops'=>7,'weight'=>630,'pitmaterial'=>'41','lift_type'=>'пассажирский'] as $key=>$value) $payload['fields'][$key]=['raw'=>$value,'display'=>(string)$value];
    $save=static function(array $payload)use($db,$p):void {$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$db->prepare("UPDATE {$p}fm2_pilot_object_details SET payload_json=?,content_sha256=? WHERE object_id=4512")->execute([$json,hash('sha256',$json)]);};
    $save($payload);
    // Original planning dates are contractual by the owner's explicit clarification.
    $db->query("UPDATE {$p}fm_maintable SET workdatestart='2026-09-01',plan_finish_date='2026-09-20',workdateendadjusted='2099-01-01' WHERE id=4512");
    $db->query("UPDATE {$p}fm2_installation_cases SET actual_start_date='2026-09-10' WHERE id=6101");
    $documentSource=(new \FMonitor2\InstallationProcess\MariaDbLegacyInstallationObject($db,$p))->getInstallationObjectSnapshot(4512);
    assertSameValue(['2026-09-01','2026-09-20','2026-09-10','2099-01-01'],[$documentSource['contractStartDate']??null,$documentSource['contractFinishDate']??null,$documentSource['actualStartDate']??null,$documentSource['plannedFinishDate']],'INTENDED_RED: document source separates contract actual and adjusted operational dates');
    $page=$fixture->page();$operation=InspectionFixture::operation();$operation['deviceTime']='2026-09-14T10:00:00+03:00';
    InspectionFixture::result($fixture->send($operation,InspectionFixture::csrf($page)),200,'accepted');
    $db->query("UPDATE {$p}fm2_checklist_operations SET server_received_at='2026-09-14T11:00:00+03:00'");
    $reader=new MariaDbNativePremiumInputs($db,$p,null,static fn()=>'2026-10-01T12:00:00Z');
    $before=$h->facts();$row=$reader->forDate('2026-10-01')[0];
    assertSameValue([58500000,'2026-09-29',[]],[$row['premium'],$row['deadline'],$row['issues']],'INTENDED_RED: seven stops and full twenty-day allowance');
    $period=$row['sourceEvidence']['contractualPeriod']??[];
    assertSameValue(['2026-09-01','2026-09-20',20,'2026-09-10','2026-09-29'],[$period['contractStartDate']??null,$period['contractFinishDate']??null,$period['normDays']??null,$period['actualStartDate']??null,$period['allowanceFinishDate']??null],'period explanation exposes original/actual/derived dates');
    $calc=PremiumCalculationV2::calculate($row['operands'],['closures'=>[],'actualPayouts'=>[]]);
    assertSameValue([2,9800],[$calc['daysLate'],$calc['kssBp']],'late through report without PTO');
    assertSameValue($before,$h->facts(),'input read creates no business facts');
    // A completed PTO freezes the comparison date despite later report dates.
    $h->insert($p.'fm2_pilot_completion_facts',['installation_case_id'=>6101,'fact_type'=>'pto_act','fact_date'=>'2026-09-29','details'=>'ПТО','recorded_at'=>'2026-09-29 09:00:00','recorded_by_user_id'=>18]);
    $row=$reader->forDate('2026-10-01')[0];
    $calc=PremiumCalculationV2::calculate($row['operands'],['closures'=>[],'actualPayouts'=>[]]);
    assertSameValue([0,10000],[$calc['daysLate'],$calc['kssBp']],'PTO within allowance prevents late penalty');
    $db->query("UPDATE {$p}fm2_pilot_completion_facts SET fact_date='2026-10-01' WHERE installation_case_id=6101 AND fact_type='pto_act'");
    $row=$reader->forDate('2026-10-03')[0];$calc=PremiumCalculationV2::calculate($row['operands'],['closures'=>[],'actualPayouts'=>[]]);
    assertSameValue([2,9800],[$calc['daysLate'],$calc['kssBp']],'PTO freezes exactly two late days');
    \FMonitor2\Tests\Support\DeadlineCertificateFixture::grants($db,$p);
    $certificate=new \FMonitor2\DeadlineTransferCertificate\DeadlineTransferCertificates($db,$p,static fn()=>'2026-10-03T12:00:00Z');
    $bytes=\FMonitor2\Tests\Support\AssignmentOrderOriginalPdfCorpus::passiveClassic();$done=false;
    $stream=static function()use($bytes,&$done):?string{if($done)return null;$done=true;return$bytes;};
    $certificate->submit(['requestId'=>'24242424-0000-4000-8000-000000000999','installationCaseId'=>6101,'actorId'=>18,'expectedVersion'=>0,'certificateDate'=>'2026-09-28','newDeadline'=>'2026-10-02','correctionReason'=>null,'sourceLabel'=>'ФКР','sourceLocator'=>'fixture://mikryukova/certificate','mediaType'=>'application/pdf'],$stream);
    $row=$reader->forDate('2026-10-03')[0];assertSameValue('2026-10-02',$row['deadline'],'certificate replaces allowance without adding norm twice');
    assertSameValue(0,PremiumCalculationV2::calculate($row['operands'],['closures'=>[],'actualPayouts'=>[]])['daysLate'],'certificate excuses delay');
    $missing=$payload;unset($missing['fields']['stops']);$save($missing);$row=$reader->forDate('2026-10-03')[0];
    assertSameValue(null,$row['operands'],'missing stops never falls back to floors');
    $save($payload);$db->query("UPDATE {$p}fm_maintable SET workdatestart='2026-09-21' WHERE id=4512");
    assertSameValue(null,$reader->forDate('2026-10-03')[0]['operands'],'negative contract interval blocks financial inputs');
    foreach([['','2026-09-20'],['2026-09-01',''],['2026-02-30','2026-09-20']]as[$start,$finish]){
        $db->prepare("UPDATE {$p}fm_maintable SET workdatestart=?,plan_finish_date=? WHERE id=4512")->execute([$start,$finish]);
        assertSameValue(null,$reader->forDate('2026-10-03')[0]['operands'],'invalid/missing original dates cannot manufacture norm even with certificate');
    }
    $db->query("UPDATE {$p}fm2_installation_cases SET actual_start_date=NULL WHERE id=6101");
    assertSameValue([],$reader->forDate('2026-10-03'),'unopened object has no financial calculation');
    echo "PASS MIKRYUKOVA-FEEDBACK-001 native inputs\n";
} finally {if($fixture!==null)$fixture->close();}
