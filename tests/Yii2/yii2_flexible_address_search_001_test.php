<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/ObjectQueueFixture.php';
// FLEXIBLE-ADDRESS-SEARCH-001: real list owners and HTTP, >page-size corpus, edits, no writes.
$f=null;
try{
$f=new ObjectQueueFixture(dirname(__DIR__,2));$p=$f->p;$db=$f->db;
foreach(range(1,60)as$i){$id=500000+$i;$case=6200+$i;$f->object($id,$case,'working',address:'ул. Берёзовая, д. 12, корп. 2');$f->order(8000+$i,$case,1,7301);$db->query("UPDATE fm_maintable SET entrance='3' WHERE id={$id}");}
foreach([[500101,6301,'Берёзовая д112 к2','3'],[500102,6302,'Берёзовая д12 к2','13'],[500103,6303,'Берёзовая д12 стр2','3']]as[$id,$case,$address,$entrance]){$f->object($id,$case,'working',address:$address);$f->order(8200+$case,$case,1,7301);$db->prepare('UPDATE fm_maintable SET entrance=? WHERE id=?')->execute([$entrance,$id]);}
$db->query("INSERT IGNORE INTO {$p}fm2_pilot_role_permissions(role_id,permission)VALUES(9201,'otiz.manage')");
// Independent norm oracle: 5 stops, 320 kg, reinforced concrete = 52,000,000 cents.
foreach([451201,500001]as$id){$json=json_encode(['schemaVersion'=>'technical-object-detail-v1','objectId'=>$id,'fields'=>['stops'=>['raw'=>5],'weight'=>['raw'=>320],'lift_type'=>['display'=>'Пассажирский'],'pitmaterial'=>['display'=>'Железобетон']]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$db->prepare("UPDATE {$p}fm2_pilot_object_details SET payload_json=?,content_sha256=? WHERE object_id=?")->execute([$json,hash('sha256',$json),$id]);}
$db->query("UPDATE fm_maintable SET regnumber='BASE-REG',zavnumber='BASE-FACTORY' WHERE id=451201");$f->completion(6101,'declaration');
$connection=$f->connection();$address=FMonitor2\YiiRuntime\InstallationProcessFactory::addressRegister($connection,$p,'');$queue=$f->queue();$completion=new FMonitor2\InstallationProcess\MariaDbYiiCompletionRegister($connection,$p,'');$economy=new FMonitor2\Otiz\MariaDbOtizPortfolioEconomy($connection,$p,'');
$before=$f->facts();$q='березовая 12 2 3';$expected=range(500001,500050);
$models=[
'address'=>$address->read(9101,$q),
'objects'=>$queue->read(9101,$q,'',1),
'completion'=>$completion->read(9101,['q'=>$q,'mode'=>'all']),
'economy'=>$economy->read(9101,['q'=>$q,'pageSize'=>50]),
'legacy-register'=>(new FMonitor2\Otiz\ObjectRegister($db,$p,''))->read(9101,['q'=>$q,'pageSize'=>50,'sort'=>'regnumber_asc'])];
foreach($models as$name=>$m){$rows=$m['objects']??$m['rows'];$ids=array_map('intval',array_column($rows,$name==='legacy-register'?'object_id':($name==='completion'||$name==='economy'?'objectId':'id')));assertSameValue($expected,$ids,'INTENDED_RED '.$name.' matches normalized address/entrance before paging');assertSameValue(60,$m['filters']['total']??$m['total'],$name.' total');}
assertSameValue(52000000,$models['economy']['summary']['fundCents'],'filtered economy excludes equal funded nonmatch');
foreach(['REG-500060','ZAV-500060']as$needle){assertSameValue([500060],array_map('intval',array_column($address->read(9101,$needle)['objects'],'id')),'address preserves '.$needle);assertSameValue([500060],array_map('intval',array_column($queue->read(9101,$needle,'',1)['objects'],'id')),'queue preserves '.$needle);}
assertSameValue([451201],array_map('intval',array_column($queue->read(9101,'451201','',1)['objects'],'id')),'queue preserves ID-only search');
assertSameValue([],$address->read(9101,'451201')['objects'],'address register does not gain internal ID search');
foreach(['REG-500060'=>500060,'Декларация теста'=>451201]as$needle=>$id)assertSameValue([$id],array_map('intval',array_column($completion->read(9101,['q'=>$needle,'mode'=>'all'])['rows'],'objectId')),'completion preserves '.$needle);
assertSameValue([500060],array_map('intval',array_column($economy->read(9101,['q'=>'REG-500060'])['rows'],'objectId')),'economy registration');
$denied=[fn()=>$address->read(9401,$q),fn()=>$queue->read(9401,$q,'',1),fn()=>$completion->read(9401,['q'=>$q,'mode'=>'all']),fn()=>$economy->read(9401,['q'=>$q])];foreach($denied as$read){try{$read();throw new TestFailure('unauthorized search unexpectedly allowed');}catch(DomainException){}}
$m=$address->read(9101,$q,'',2);assertSameValue(range(500051,500060),array_map('intval',array_column($m['objects'],'id')),'address page2 exact');
$edit=json_encode(['address'=>'ул. Лесная, д. 9','entrance'=>'7','regnumber'=>'CORRECTED-TAIL'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$f->insert($p.'fm2_object_detail_edits',['object_id'=>500060,'revision'=>1,'values_json'=>$edit,'updated_at_utc'=>'2026-11-27 12:00:00','updated_by_user_id'=>9101]);
assertSameValue([500060],array_map('intval',array_column($address->read(9101,'лесная д9 п7')['objects'],'id')),'effective edits searchable without rebuild');assertSameValue(59,$address->read(9101,$q)['filters']['total'],'old address removed immediately');
$oldRegister=new FMonitor2\Otiz\ObjectRegister($db,$p,'');
$reads=[
 'address'=>fn(string$n)=>$address->read(9101,$n),
 'objects'=>fn(string$n)=>$queue->read(9101,$n,'',1),
 'completion'=>fn(string$n)=>$completion->read(9101,['q'=>$n,'mode'=>'all']),
 'economy'=>fn(string$n)=>$economy->read(9101,['q'=>$n]),
 'legacy-register'=>fn(string$n)=>$oldRegister->read(9101,['q'=>$n])];
foreach($reads as$name=>$read){
 foreach(['лесная д9 п7','CORRECTED-TAIL']as$needle){$m=$read($needle);$rows=$m['objects']??$m['rows'];$key=$name==='legacy-register'?'object_id':($name==='completion'||$name==='economy'?'objectId':'id');assertSameValue([500060],array_map('intval',array_column($rows,$key)),'INTENDED_RED effective address/entrance/registration '.$name.' '.$needle);assertSameValue('ул. Лесная, д. 9',$rows[0]['address'],'effective display agrees '.$name);$regKey=in_array($name,['legacy-register','economy'],true)?'regnumber':'registrationNumber';assertSameValue('CORRECTED-TAIL',$rows[0][$regKey],'effective registration displayed '.$name);}
 $m=$read('REG-500060');assertSameValue(0,$m['filters']['total']??$m['total'],'stale registration removed '.$name);
}
assertSameValue([451201,500060],array_slice(array_map('intval',array_column($oldRegister->read(9101,['sort'=>'regnumber_asc','pageSize'=>25])['rows'],'object_id')),0,2),'effective registration sorts before SQL paging');
// Its explicitly global economics summary retains the pre-existing contract.
assertSameValue($oldRegister->read(9101,[])['summary'],$oldRegister->read(9101,['q'=>'CORRECTED-TAIL'])['summary'],'legacy global summary remains global');

$db->query("DELETE FROM {$p}fm2_object_detail_edits WHERE object_id=500060");assertSameValue($before,$f->facts(),'all search reads preserve schema and facts');
// One explicit legacy prefix gives every real HTTP controller the same fixture source.
$db->query('CREATE TABLE search_fm_maintable LIKE fm_maintable');$db->query('INSERT INTO search_fm_maintable SELECT * FROM fm_maintable');
$state=$f->http->artifacts.'/search-session-state';mkdir($state,0700);$f->http->start(extraEnvironment:['FMONITOR_SESSION_STATE_ROOT'=>$state,'FMONITOR_LEGACY_TABLE_PREFIX'=>'search_']);$cookies=[];assertSameValue(303,$f->http->login($cookies)['status'],'login');
foreach(['/pilot/objects','/pilot/address-register','/pilot/completion-register?mode=all','/pilot/otiz/objects','/pilot/otiz']as$route){$url=$route.(str_contains($route,'?')?'&':'?').'q='.rawurlencode('березовя д12 к2 п3');$r=$f->http->request('GET',$url,[],$cookies);assertSameValue(200,$r['status'],'real HTTP search '.$route);assertSameValue(true,str_contains($r['body'],'REG-500001'),'visible match '.$route);assertSameValue(false,str_contains($r['body'],'REG-500101'),'wrong house absent '.$route);}
echo "PASS: FLEXIBLE-ADDRESS-SEARCH-001 server lists\n";
}finally{if($f instanceof ObjectQueueFixture)$f->close();}
