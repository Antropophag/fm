<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PreopeningFixture.php';
$f=null;
try {
    $f=new PreopeningFixture(dirname(__DIR__,2));$f->start();$actor=[];$f->login($actor,18);$csrf=$f->token($actor);$p=$f->p;
    $f->db->query("UPDATE {$p}fm_maintable SET workdatestart='2026-09-01',plan_finish_date='2026-09-20',workdateendadjusted='2099-01-01' WHERE id=4512");
    $payload=json_decode($f->db->query("SELECT payload_json FROM {$p}fm2_pilot_object_details WHERE object_id=4512")->fetch_column(),true,flags:JSON_THROW_ON_ERROR);
    foreach(['stops'=>9,'floors'=>9,'weight'=>630,'lift_type'=>'пассажирский','pitmaterial'=>'41']as$key=>$value)$payload['fields'][$key]=['raw'=>$value,'display'=>(string)$value];
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$f->db->prepare("UPDATE {$p}fm2_pilot_object_details SET payload_json=?,content_sha256=? WHERE object_id=4512")->execute([$json,hash('sha256',$json)]);
    $effective=new \FMonitor2\InstallationProcess\MariaDbEffectiveObjectDetails($f->db,$p,$p);
    assertSameValue(9,$effective->read(4512)['stops']['raw']??null,'imported stops present in effective details');
    $post=['_csrf'=>$csrf,'requestId'=>'24242424-0000-4000-8000-000000000101','expectedRevision'=>'0','stops'=>'7','floors'=>'12'];
    $saved=$f->form('/pilot/objects/4512/details',$post,$actor);
    assertSameValue(303,$saved['status'],'INTENDED_RED: authorized stops edit through real HTTP');
    $values=json_decode($f->db->query("SELECT values_json FROM {$p}fm2_object_detail_edits WHERE object_id=4512")->fetch_column(),true,flags:JSON_THROW_ON_ERROR);
    assertSameValue([7,12],[$values['stops'],$values['floors']],'distinct values persisted');
    $events=(int)$f->db->query("SELECT COUNT(*) FROM {$p}fm2_object_detail_edit_events")->fetch_column();
    assertSameValue(1,$events,'one audit event');$f->form('/pilot/objects/4512/details',$post,$actor);
    assertSameValue($events,(int)$f->db->query("SELECT COUNT(*) FROM {$p}fm2_object_detail_edit_events")->fetch_column(),'replay preserves audit count');
    $card=$f->request('GET','/pilot/objects/4512',[],$actor);
    foreach(['Количество остановок','Данные объекта изменены']as$text)assertSameValue(true,str_contains($card['body'],$text),'card exposes '.$text);
    $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8"'.$card['body']);$xp=new DOMXPath($dom);$periodText=$xp->evaluate('string(//*[@data-contractual-period])');
    assertSameValue('7',$xp->evaluate('string(//input[@name="stops"]/@value)'),'editable stops control retains manual value');
    assertSameValue(7,$effective->read(4512)['stops']['raw'],'manual stops present in effective details');
    $env=$f->environment();$db=new yii\db\Connection(['dsn'=>'mysql:host='.$env['FMONITOR_DB_HOST'].';port='.$env['FMONITOR_DB_PORT'].';dbname='.$f->database,'username'=>$env['FMONITOR_DB_USER'],'password'=>$env['FMONITOR_DB_PASSWORD'],'charset'=>'utf8mb4']);$db->open();
    $cardOwner=new \FMonitor2\InstallationProcess\MariaDbYiiObjectCard($db,$p,$p,new \FMonitor2\InstallationProcess\MariaDbYiiObjectCardProjection($db,$p,new \FMonitor2\InstallationProcess\MariaDbYiiObjectQueueProjection($db,$p)));
    assertSameValue(7,$cardOwner->read(18,4512)['objectDetails']['fields']['stops']['raw'],'card display uses edited stops');
    $economy=(new \FMonitor2\Otiz\OtizPortfolioEconomy($db,$p,$p))->read(96,[]);$rows=array_column($economy['rows'],null,'objectId');
    assertSameValue(58500000,$rows[4512]['fundCents'],'portfolio row reprices seven edited stops');
    assertSameValue(58500000,$economy['summary']['fundCents'],'portfolio summary reprices seven edited stops');$db->close();
    foreach(['Нормативный период','20 дней']as$text)assertSameValue(true,str_contains($periodText,$text),'scoped card period '.$text);foreach(['01.09.2026','20.09.2026','Начало','Завершение']as$text)assertSameValue(true,str_contains($card['body'],$text),'card plan fact '.$text);foreach(['20 календарных дней','Договорный норматив','Начало работ не подтверждено']as$text)assertSameValue(false,str_contains($card['body'],$text),'ordinary card hides obsolete diagnostic copy '.$text);
    assertSameValue(false,str_contains($periodText,'2099'),'adjusted operational date never masquerades as contractual period');
    foreach([['stops'=>'0'],['stops'=>'1.5'],['stops'=>'']]as$i=>$patch){$before=$f->facts();$r=$f->form('/pilot/objects/4512/details',array_replace($post,['requestId'=>sprintf('24242424-0000-4000-8000-%012d',200+$i),'expectedRevision'=>'1'],$patch),$actor);assertSameValue(422,$r['status'],'invalid stops denied');assertSameValue($before,$f->facts(),'validation no facts');}
    $before=$f->facts();$r=$f->form('/pilot/objects/4512/details',array_replace($post,['requestId'=>'24242424-0000-4000-8000-000000000299','stops'=>'8']),$actor);assertSameValue(409,$r['status'],'stale revision denied');assertSameValue($before,$f->facts(),'stale request no facts');
    $reader=[];$f->login($reader,95);$before=$f->facts();$r=$f->form('/pilot/objects/4512/details',array_replace($post,['_csrf'=>$f->token($reader),'expectedRevision'=>'1','requestId'=>'24242424-0000-4000-8000-000000000300']),$reader);assertSameValue(403,$r['status'],'read-only role cannot edit stops');assertSameValue($before,$f->facts(),'authorization denial no facts');
    echo "PASS MIKRYUKOVA-FEEDBACK-001 card HTTP\n";
}finally{if($f!==null)$f->close();}
