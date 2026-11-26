<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Yii2/InspectionFixture.php';
use FMonitor2\EquipmentReclamation\EquipmentReclamations;
use FMonitor2\Otiz\{MariaDbNativePremiumInputs,PremiumCalculationV2,SnapshotPublication,MariaDbSnapshotStore,OtizSettlementV2DraftBuilder};
assertSameValue(true,class_exists(EquipmentReclamations::class),'INTENDED_RED reclamation ledger available to finance');
$f=null;$yii=null;
try{
 $f=new InspectionFixture(dirname(__DIR__,2));$h=$f->http;$db=$h->db;$p=$h->p;
 FMonitor2\Tests\Support\DeadlineCertificateFixture::grants($db,$p);
 FMonitor2\Tests\Support\OtizContractFixture::seed($h);
 $h->start();$fk=[];$h->login($fk);assertSameValue(303,$h->selection($fk,ids:[7001,7002])['status'],'selected crew');
 $metadata=['csrfToken'=>$h->token($fk),'requestId'=>'62626262-0000-4000-8000-000000000001','mode'=>'initial','appendixStartDate'=>'2026-09-10','externalDocumentNumber'=>'DO-RECL-001','rootOriginalId'=>null,'targetRevisionId'=>null,'expectedCurrentRevisionId'=>null,'correctionReason'=>null,'originalFilename'=>'signed.pdf'];
 assertSameValue(201,$h->upload($fk,$metadata)['status'],'original opens case');$h->login($f->cookies,73);
 $db->query("UPDATE {$p}fm_maintable SET workdatestart='2026-09-01',plan_finish_date='2026-09-20' WHERE id=4512");
 $payload=json_decode($db->query("SELECT payload_json FROM {$p}fm2_pilot_object_details WHERE object_id=4512")->fetch_column(),true,flags:JSON_THROW_ON_ERROR);
 foreach(['stops'=>7,'weight'=>630,'pitmaterial'=>'41','lift_type'=>'пассажирский']as$key=>$v)$payload['fields'][$key]=['raw'=>$v,'display'=>(string)$v];$j=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$db->prepare("UPDATE {$p}fm2_pilot_object_details SET payload_json=?,content_sha256=? WHERE object_id=4512")->execute([$j,hash('sha256',$j)]);
 $page=$f->page();$op=InspectionFixture::operation();$op['deviceTime']='2026-09-14T10:00:00+03:00';InspectionFixture::result($f->send($op,InspectionFixture::csrf($page)),200,'accepted');$db->query("UPDATE {$p}fm2_checklist_operations SET server_received_at='2026-09-14T11:00:00+03:00'");
 $clock=static fn()=>'2026-10-20T12:00:00Z';$reader=new MariaDbNativePremiumInputs($db,$p,null,$clock);$ledger=new EquipmentReclamations($db,$p,$clock);
 $calculate=static fn($r)=>PremiumCalculationV2::calculate($r['operands'],['closures'=>[],'actualPayouts'=>[]]);
 $row=$reader->forDate('2026-10-10')[0];assertSameValue([], $row['issues'],'complete financial inputs');assertSameValue('2026-09-29',$row['deadline'],'base normative deadline');assertSameValue(11,$calculate($row)['daysLate'],'baseline lateness');
 $pub=new SnapshotPublication(new MariaDbSnapshotStore($db,$p),fn(string$d)=>$reader->forDate($d),$clock);
 $savedId=$pub->buildAndPublish(96,'2026-10-10','63636363-0000-4000-8000-000000000001');$saved=$pub->read(96,$savedId);
 $claim=['requestId'=>'64646464-0000-4000-8000-000000000001','installationCaseId'=>6101,'actorId'=>18,'number'=>'РЕК-A','delayDays'=>3];$ledger->add($claim);
 $row=$reader->forDate('2026-10-10')[0];assertSameValue(['2026-10-02',8],[$row['deadline'],$calculate($row)['daysLate']],'first claim reduces late days');
 $env=$h->environment();$yii=new yii\db\Connection(['dsn'=>'mysql:host='.$env['FMONITOR_DB_HOST'].';port='.$env['FMONITOR_DB_PORT'].';dbname='.$h->database,'username'=>$env['FMONITOR_DB_USER'],'password'=>$env['FMONITOR_DB_PASSWORD'],'charset'=>'utf8mb4']);$yii->open();
 $builder=new OtizSettlementV2DraftBuilder($yii,$p,$p,$clock);$draft=$builder->build(96,'2026-10-01');assertSameValue(10000,$draft['objects'][0]['kssBp'],'draft before both deadlines no lateness');
 $ledger->add(array_replace($claim,['requestId'=>'64646464-0000-4000-8000-000000000002','number'=>'РЕК-B','delayDays'=>4]));
 $current=$builder->build(96,'2026-10-01');assertSameValue(10000,$current['objects'][0]['kssBp'],'new evidence leaves Kss unchanged');$stale=false;try{$builder->assertCurrent($draft,'STALE_RECLAMATION',false);}catch(DomainException$e){$stale=$e->getMessage()==='STALE_RECLAMATION';}assertSameValue(true,$stale,'deadline evidence invalidates stale draft even equal Kss');
 $row=$reader->forDate('2026-10-10')[0];assertSameValue(['2026-10-06',4,7],[$row['deadline'],$calculate($row)['daysLate'],$row['sourceEvidence']['reclamations']['totalDays']],'two claims sum in real inputs');
 $nextId=$pub->buildAndPublish(96,'2026-10-10','63636363-0000-4000-8000-000000000002');$next=$pub->read(96,$nextId);assertSameValue($saved,$pub->read(96,$savedId),'published snapshot remains byte-for-byte materialized history');assertSameValue(false,$saved['snapshot']['content_hash']===$next['snapshot']['content_hash'],'new grounds change new snapshot identity');
 $cert=new FMonitor2\DeadlineTransferCertificate\DeadlineTransferCertificates($db,$p,$clock);$bytes=FMonitor2\Tests\Support\AssignmentOrderOriginalPdfCorpus::passiveClassic();$done=false;$stream=static function()use($bytes,&$done){if($done)return null;$done=true;return$bytes;};
 $cert->submit(['requestId'=>'65656565-0000-4000-8000-000000000001','installationCaseId'=>6101,'actorId'=>18,'expectedVersion'=>0,'certificateDate'=>'2026-09-25','newDeadline'=>'2026-10-08','correctionReason'=>null,'sourceLabel'=>'Справка ФКР','sourceLocator'=>'fixture/reclamation/certificate','mediaType'=>'application/pdf'],$stream);
 $row=$reader->forDate('2026-10-10')[0];$math=$calculate($row);assertSameValue(['2026-10-15',0,0],[$row['deadline'],$math['daysLate'],$math['amounts']['deadlinePenaltyCents']],'certificate plus seven claim days excuses all late days');
 assertSameValue(['2026-10-08','certificate',7],[$row['sourceEvidence']['deadlineAllowance']['baseDeadline'],$row['sourceEvidence']['deadlineAllowance']['baseBasis'],$row['sourceEvidence']['deadlineAllowance']['reclamationDays']],'saved explanation retains base and addition');
 $h->insert($p.'fm2_pilot_completion_facts',['installation_case_id'=>6101,'fact_type'=>'pto_act','fact_date'=>'2026-10-20','details'=>'ПТО','recorded_at'=>'2026-10-20 09:00:00','recorded_by_user_id'=>18]);$row=$reader->forDate('2026-11-01')[0];assertSameValue(5,$calculate($row)['daysLate'],'only installer delay beyond extended date penalized');
 assertSameValue($saved,$pub->read(96,$savedId),'later certificate and PTO do not rewrite old snapshot');
 $table=$p.'fm2_equipment_reclamations';$db->query("RENAME TABLE `$table` TO `{$table}_missing`");try{$blocked=false;try{$broken=$reader->forDate('2026-10-10')[0];$blocked=$broken['operands']===null;}catch(Throwable){$blocked=true;}assertSameValue(true,$blocked,'missing register never silently means zero excused days');}finally{$db->query("RENAME TABLE `{$table}_missing` TO `$table`");}
 echo "PASS RECLAMATION-DELAY-001 native penalties, snapshots and freshness\n";
}finally{if($yii)$yii->close();if($f)$f->close();}
