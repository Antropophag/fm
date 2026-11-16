<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/ObjectQueueFixture.php';
// ADDRESS-REGISTER-001: real HTTP, actor isolation and independent persisted-facts observations.
$f=null;$original=null;
try{
 $f=new ObjectQueueFixture(dirname(__DIR__,2));$h=$f->http;$p=$f->p;
 $state=$h->artifacts.'/private-state';mkdir($state,0700);$h->start(extraEnvironment:['FMONITOR_SESSION_STATE_ROOT'=>$state]);
 $guest=[];$r=$h->request('GET','/pilot/address-register',[],$guest);assertSameValue([303,'/pilot/login'],[$r['status'],$r['headers']['location'][0]??null],'INTENDED_RED address register guest route');
 $return=$h->login($guest);assertSameValue('/pilot/address-register',$return['headers']['location'][0]??null,'guest login returns to register');
 $a=[];assertSameValue(303,$h->login($a)['status'],'login');$before=$f->facts();
 $r=$h->request('GET','/pilot/address-register',[],$a);assertSameValue(200,$r['status'],'real register');
 foreach(['Адресный перечень','REG-451201','Синтетический адрес','data-register-table','data-register-open','/pilot/objects/451201']as$m)assertSameValue(true,str_contains($r['body'],$m),'register content '.$m);
 assertSameValue('no-store',$r['headers']['cache-control'][0]??null,'private response');assertSameValue($before,$f->facts(),'GET no business/schema mutations');
 // Every emitted mutable resource must obey the merged performance cache contract.
 preg_match_all('~(?:src|href)="(/pilot/assets/[^"?]+\.(?:js|css)(?:\?[^" ]*)?)"~',$r['body'],$assetMatches);
 assertSameValue(true,count($assetMatches[1])>=3,'actual stylesheet/module inventory');
 foreach(array_unique($assetMatches[1])as$asset){$url=html_entity_decode($asset,ENT_QUOTES|ENT_HTML5,'UTF-8');
  assertSameValue(1,preg_match('/\?v=[a-f0-9]{12}$/D',$url),'INTENDED_RED register mutable asset version '.$url);
  $assetResponse=$h->request('GET',$url,[],$a);assertSameValue(200,$assetResponse['status'],'versioned resource served');
  assertSameValue(true,str_contains($assetResponse['headers']['cache-control'][0]??'','immutable'),'content-addressed cache '.$url);
 }
 assertSameValue(false,str_contains($r['body'],'/preloader.js'),'unused preloader not emitted');
 $head=$h->request('HEAD','/pilot/address-register',[],$a);assertSameValue([200,''],[$head['status'],$head['body']],'HEAD');
 $denied=[];$h->login($denied,'ordinary.person@shlz.ru');assertSameValue(403,$h->request('GET','/pilot/address-register',[],$denied)['status'],'objects.read required');
 $csrf=$h->csrf($r['body']);$defaults=FMonitor2\AddressRegister\Columns::defaults();
 $form=['_csrf'=>$csrf,'expectedRevision'=>'0','visible'=>json_encode(['registrationNumber','address','comment'])];$path='/pilot/address-register/preferences';
 assertSameValue(405,$h->request('GET',$path,[],$a)['status'],'write POST only');assertSameValue(405,$h->request('HEAD',$path,[],$a)['status'],'write rejects HEAD');assertSameValue(405,$h->request('POST','/pilot/address-register',['_csrf'=>$csrf],$a)['status'],'index rejects POST');assertSameValue(400,$h->request('POST',$path,array_replace($form,['_csrf'=>'wrong']),$a)['status'],'native CSRF');
 $r=$h->request('POST',$path,$form,$a);assertSameValue(200,$r['status'],'save JSON');$saved=json_decode($r['body'],true,flags:JSON_THROW_ON_ERROR);assertSameValue(['revision'=>1,'visible'=>['registrationNumber','address','comment'],'layoutVersion'=>2],$saved,'exact saved layout');
 $store=new FMonitor2\AddressRegister\ColumnPreferences($state.'/address-register-preferences',static fn(int$id)=>true);assertSameValue($saved,$store->read(9101),'authenticated identity owns persisted state');
 assertSameValue(409,$h->request('POST',$path,$form,$a)['status'],'stale CAS conflict');
 $form['expectedRevision']='1';
 foreach([['userId'=>'9401'],['visible'=>'["registrationNumber","secret"]'],['visible'=>'["registrationNumber","registrationNumber"]'],['visible'=>'[]'],['visible'=>'invalid json'],['widths'=>'{"address":9000}'],['expectedRevision'=>'-1'],['expectedRevision'=>['1']]]as$delta)assertSameValue(422,$h->request('POST',$path,array_replace($form,$delta),$a)['status'],'invalid input no persist '.json_encode($delta));
 assertSameValue($saved,$store->read(9101),'rejections retain exact state');assertSameValue($defaults,$store->read(9401),'target spoof no writes');assertSameValue($before,$f->facts(),'settings never mutate business/schema');
 $second=[];$h->login($second);assertSameValue(200,$h->request('GET','/pilot/address-register',[],$second)['status'],'new session reads persisted layout');
 $f->db->query("INSERT INTO {$p}fm2_pilot_role_permissions(role_id,permission)VALUES(9210,'objects.read')");
 $b=[];$h->login($b,'ordinary.person@shlz.ru');$bpage=$h->request('GET','/pilot/address-register',[],$b);assertSameValue(200,$bpage['status'],'second account reads');
 $f->db->query("UPDATE {$p}fm2_pilot_users SET status=0 WHERE user_id=9401");assertSameValue(303,$h->request('GET','/pilot/address-register',[],$b)['status'],'inactive session cannot read');$f->db->query("UPDATE {$p}fm2_pilot_users SET status=1 WHERE user_id=9401");$b=[];$h->login($b,'ordinary.person@shlz.ru');$bpage=$h->request('GET','/pilot/address-register',[],$b);
 $bform=$form;$bform['_csrf']=$h->csrf($bpage['body']);$bform['expectedRevision']='0';$bform['visible']='["registrationNumber","factoryNumber"]';assertSameValue(200,$h->request('POST',$path,$bform,$b)['status'],'second account saves independently');assertSameValue($saved,$store->read(9101),'first account not overwritten');
 $f->db->query("DELETE FROM {$p}fm2_pilot_role_permissions WHERE role_id=9210 AND permission='objects.read'");assertSameValue(403,$h->request('POST',$path,$bform,$b)['status'],'revocation blocks save');
 foreach(['?q[]=x','?page=0','?page=99999999999999999999999','?status=bad','?q=a&q=b','?sort=secret','?page[]=1','?status[]=installation','?q=%ZZ','?q='.str_repeat('a',121)]as$q)assertSameValue(400,$h->request('GET','/pilot/address-register'.$q,[],$a)['status'],'query validation '.$q);
 // Effective edits override stale mirror values; technical snapshot display remains available.
 $f->insert($p.'fm2_object_detail_edits',['object_id'=>451201,'revision'=>1,'values_json'=>json_encode(['address'=>'Новый адрес <script>alert(1)</script>','regnumber'=>'NEW-451201','comment'=>'Проверить монтаж','floors'=>17]),'updated_at_utc'=>'2026-11-13 12:00:00','updated_by_user_id'=>9101]);
 $owner=FMonitor2\YiiRuntime\InstallationProcessFactory::addressRegister($f->connection(),$p,'');$model=$owner->read(9101,'NEW-451201');assertSameValue(1,$model['filters']['total'],'search effective registration');$row=$model['objects'][0];assertSameValue(['NEW-451201','Новый адрес <script>alert(1)</script>','2026-09-01'],[$row['registrationNumber'],$row['address'],$row['actualStartDate']],'canonical values');assertSameValue('17',(string)$row['floors'],'technical effective override');
 $r=$h->request('GET','/pilot/address-register?q=NEW-451201',[],$a);assertSameValue(false,str_contains($r['body'],'<script>alert(1)</script>'),'escape source HTML');assertSameValue(true,str_contains($r['body'],'&lt;script&gt;'),'literal still visible');
 // Exact full-row oracle: latest edits and corrected completion facts override old values.
 $f->db->query("UPDATE fm_maintable SET workdateendadjusted='2026-11-25' WHERE id=451201");
 $details=['weight'=>['raw'=>630,'display'=>'630 кг'],'speed'=>['raw'=>1.6,'display'=>'1,6 м/с'],'lift_type'=>['raw'=>1,'display'=>'Пассажирский'],'pittype'=>['raw'=>2,'display'=>'Каркасная'],'pitmaterial'=>['raw'=>3,'display'=>'Металл'],'paired'=>['raw'=>1,'display'=>'Да']];
 $payload=json_encode(['schemaVersion'=>'technical-object-detail-v1','objectId'=>451201,'fields'=>$details],JSON_THROW_ON_ERROR);
 $stmt=$f->db->prepare("UPDATE {$p}fm2_pilot_object_details SET payload_json=?,content_sha256=? WHERE object_id=451201");$stmt->execute([$payload,hash('sha256',$payload)]);
 foreach(['pto_act'=>'2026-09-13','declaration'=>'2026-09-14']as$type=>$date){
  $f->completion(6101,$type);$fact=(int)$f->db->insert_id;$previous=null;
  foreach([1=>'2026-09-11',2=>$date]as$version=>$corrected){
   $f->insert($p.'fm2_pilot_completion_fact_corrections',['root_fact_id'=>$fact,'version_no'=>$version,'previous_correction_id'=>$previous,'previous_version_no'=>$version===1?null:1,'fact_date'=>$corrected,'details'=>null,'reason'=>'Уточнение теста','recorded_at'=>'2026-09-15 09:00:00','recorded_by_user_id'=>9101]);$previous=(int)$f->db->insert_id;
  }
 }
 $expectedRow=['id'=>451201,'caseId'=>6101,'address'=>'Новый адрес <script>alert(1)</script>','entrance'=>'1','registrationNumber'=>'NEW-451201','factoryNumber'=>'ZAV-451201','status'=>'Работы завершены','completionProgress'=>100,'plannedStartDate'=>'2026-09-12','plannedFinishDate'=>'2026-11-25','actualStartDate'=>'2026-09-01','actualFinishDate'=>'2026-09-13','controlEngineer'=>null,'installers'=>null,'floors'=>'17','weight'=>'630 кг','speed'=>'1,6 м/с','lift_type'=>'Пассажирский','pittype'=>'Каркасная','pitmaterial'=>'Металл','paired'=>'Да','ptoDate'=>'2026-09-13','declarationDate'=>'2026-09-14','comment'=>'Проверить монтаж'];
 $exact=$owner->read(9101,'NEW-451201')['objects'][0];assertSameValue($expectedRow,$exact,'all 22 columns, latest completion correction, effective and snapshot sources');
 $f->db->query("UPDATE fm_maintable SET workdateendadjusted='0000-00-00' WHERE id=451201");assertSameValue('2026-12-01',$owner->read(9101,'NEW-451201')['objects'][0]['plannedFinishDate'],'invalid adjusted finish falls back to plan');
 // Mirror-only object stays visible without invented process state or broken card link.
 $f->insert('fm_maintable',['id'=>999001,'ordadr_address'=>'Без дела % _ \\','entrance'=>'2','regnumber'=>'UNSTARTED']);
 foreach(['UNSTARTED','%','_','\\']as$q){$m=$owner->read(9101,$q);assertSameValue([999001],array_map('intval',array_column($m['objects'],'id')),'literal search '.$q);}
 assertSameValue([],$owner->read(9101,'999001')['objects'],'INTENDED_RED internal object id is not a user search field');
 $r=$h->request('GET','/pilot/address-register?q=UNSTARTED',[],$a);assertSameValue(true,str_contains($r['body'],'Нет монтажного дела'),'honest mirror-only status');assertSameValue(false,str_contains($r['body'],'href="/pilot/objects/999001"'),'no broken object link');
 for($i=1;$i<=51;$i++)$f->object(451201+$i,6101+$i,'needs_assignment_order');$m=$owner->read(9101,'Синтетический');assertSameValue([50,51,2],[count($m['objects']),$m['filters']['total'],$m['filters']['pages']],'bounded pagination');$next=$owner->read(9101,'Синтетический','',2);assertSameValue(1,count($next['objects']),'last page');assertSameValue([],array_intersect(array_column($m['objects'],'id'),array_column($next['objects'],'id')),'pages do not overlap');
 assertSameValue(range(451202,451251),array_map('intval',array_column($m['objects'],'id')),'equal addresses and entrances sort by id');
 assertSameValue([451252],array_map('intval',array_column($next['objects'],'id')),'exact second page boundary');
 $f->db->query("UPDATE fm_maintable SET entrance='0' WHERE id=451252");
 $f->db->query("UPDATE fm_maintable SET ordadr_address='Синтетический А' WHERE id=451251");
 $ordered=$owner->read(9101,'Синтетический');assertSameValue([451251,451252,...range(451202,451249)],array_map('intval',array_column($ordered['objects'],'id')),'address then entrance then id across boundary');
 assertSameValue([451250],array_map('intval',array_column($owner->read(9101,'Синтетический','',2)['objects'],'id')),'reordered second page exact');
 $pager=$h->request('GET','/pilot/address-register?q='.rawurlencode('Синтетический').'&status=needs_assignment_order',[],$a);
 $dom=new DOMDocument();@$dom->loadHTML($pager['body']);$xp=new DOMXPath($dom);$found=false;
 foreach($xp->query('//nav[@aria-label="Страницы адресного перечня"]//a[@href]')as$link){parse_str((string)parse_url($link->getAttribute('href'),PHP_URL_QUERY),$query);if(($query['page']??'')==='2'){assertSameValue(['q'=>'Синтетический','status'=>'needs_assignment_order','page'=>'2'],$query,'pager preserves filters');$found=true;}}
 assertSameValue(true,$found,'rendered second-page link');
 // A corrupt dependency fails safely, preserving the previously accepted preference bytes.
 $file=$state.'/address-register-preferences/user-9101/revision-0000000001.json';$bytes=file_get_contents($file);file_put_contents($file,'BROKEN-SECRET-STATE');
 $unavailable=$h->request('GET','/pilot/address-register',[],$a);assertSameValue([503,"Service unavailable.\n"],[$unavailable['status'],$unavailable['body']],'safe unavailable page');
 $unavailable=$h->request('POST','/pilot/address-register/preferences',$form,$a);assertSameValue([503,['error'=>'preferences_unavailable']],[$unavailable['status'],json_decode($unavailable['body'],true)],'safe unavailable save');assertSameValue('60',$unavailable['headers']['retry-after'][0]??null,'retry hint');assertSameValue('BROKEN-SECRET-STATE',file_get_contents($file),'failed save preserves corrupt evidence');file_put_contents($file,$bytes);
 $r=$h->request('GET','/pilot/address-register?q=no-such-object',[],$a);assertSameValue(200,$r['status'],'empty readable');assertSameValue(true,str_contains($r['body'],'data-register-open'),'empty can configure');
 foreach(['/pilot/assets/address-register.js','/pilot/assets/address-register.css','/pilot/objects']as$path)assertSameValue(200,$h->request('GET',$path,[],$a)['status'],'assets/adjacent queue '.$path);

 // Exact stage buckets are independently seeded; filter predicate must match rendered stage.
 foreach([[452002,6202,'needs_assignment_order'],[452003,6203,'assignment_order_prepared'],[452004,6204,'needs_assignment_order'],[452005,6205,'working'],[452006,6206,'working'],[452007,6207,'working']]as[$id,$case,$st])$f->object($id,$case,$st,'2026-09-12','Статусный тест');
 foreach([[6223,6203,'prepared'],[6224,6204,'registered'],[6225,6205,'registered'],[6226,6206,'registered'],[6227,6207,'registered']]as[$id,$case,$st])$f->order($id,$case,1,7301,$st);
 foreach([6206,6207]as$case)for($item=1;$item<=41;$item++)$f->checklist($case,$item,$item);
 $f->completion(6207,'pto_act');$f->completion(6207,'declaration');
 foreach(['needs_assignment_order'=>[452002,452003],'ready_to_open'=>[452004],'installation'=>[452005],'document_closeout'=>[452006],'completed'=>[452007]]as$st=>$ids)assertSameValue($ids,array_map('intval',array_column($owner->read(9101,'Статусный тест',$st)['objects'],'id')),'exact status '.$st);
 foreach(['installation'=>['Монтажные работы',0,null],'document_closeout'=>['Документарное закрытие',85,null],'completed'=>['Работы завершены',100,'2026-09-10']]as$st=>$values){$row=$owner->read(9101,'Статусный тест',$st)['objects'][0];assertSameValue($values,[$row['status'],$row['completionProgress'],$row['actualFinishDate']],'stage projection '.$st);}
 assertSameValue([],$owner->read(9101,'unmatchable','completed')['objects'],'completed still applies search');
 $payload=json_encode(['schemaVersion'=>'technical-object-detail-v1','objectId'=>999999,'fields'=>['weight'=>['raw'=>12345,'display'=>'WRONG-OBJECT-WEIGHT']]],JSON_THROW_ON_ERROR);$stmt=$f->db->prepare("UPDATE {$p}fm2_pilot_object_details SET payload_json=?,content_sha256=? WHERE object_id=451201");$stmt->execute([$payload,hash('sha256',$payload)]);assertSameValue(null,$owner->read(9101,'NEW-451201')['objects'][0]['weight'],'mismatched detail identity never displayed');
 $f->close();$f=null;
 // Apply a real native original through the public owner; no fabricated application fields.
 require_once dirname(__DIR__,2).'/app/AssignmentOrderOriginal/AssignmentOrderOriginalRuntime.php';
 $original=new FMonitor2\Tests\Support\SelectedOriginalFixture();$db=$original->selection->db;
 $migration=FMonitor2\InstallationProcess\CanonicalMigrationApplication::run($db,'',FMonitor2\InstallationProcess\ProductionPilotMigrationCatalogue::migrations());assertSameValue(0,$migration['exitCode'],'current fixture schema');
 $db->query("UPDATE fm_maintable SET entrance='1',workdatestart='2026-10-01',plan_finish_date='2026-10-31' WHERE id=4512");$db->query("INSERT INTO fm2_pilot_role_permissions(role_id,permission) VALUES(1,'objects.read')");
 assertSameValue('selected',$original->selection->app()->selectAssignmentOrderComposition(FMonitor2\Tests\Support\SelectionNativeFixture::command())->status()->value,'native selection');
 $connection=new yii\db\Connection(['dsn'=>'mysql:host='.(getenv('FMONITOR_TEST_DB_HOST')?:'127.0.0.1').';port='.(getenv('FMONITOR_TEST_DB_PORT')?:23306).';dbname='.$original->selection->schema->source->name,'username'=>getenv('FMONITOR_TEST_DB_ADMIN_USER')?:'root','password'=>getenv('FMONITOR_TEST_DB_ADMIN_PASSWORD')?:'fmonitor2_test_root_local','charset'=>'utf8mb4']);
 $native=FMonitor2\YiiRuntime\InstallationProcessFactory::addressRegister($connection,'','');assertSameValue(null,$native->read(18,'4512')['objects'][0]['installers'],'pending selection not current team');
 $accepted=$original->app()->submitAssignmentOrderOriginal(FMonitor2\Tests\Support\SelectedOriginalFixture::command(new FMonitor2\Tests\Support\SelectedOriginalInput(FMonitor2\Tests\Support\SelectedOriginalFixture::pdf())));assertSameValue('accepted',$accepted->status()->value,'accepted original');
 $db->query("INSERT IGNORE INTO fm2_pilot_role_permissions(role_id,permission) VALUES(1,'assignment_order.composition.apply')");
 $applied=FMonitor2\AssignmentOrderComposition\ProductionAssignmentOrderApplicationFactory::create($db)->applyAssignmentOrderOriginal(new FMonitor2\AssignmentOrderComposition\ApplyAssignmentOrderOriginalCommand('66666666-6666-4666-8666-666666666666',4512,81,(string)$accepted->currentRevisionId(),0,18));assertSameValue('applied',$applied->status,'real current application');
 $row=$native->read(18,'4512')['objects'][0];assertSameValue([['Монтажник 7001'],'Инженер теста'],[$row['installers'],$row['controlEngineer']],'current native names');
 $command=FMonitor2\Tests\Support\SelectionNativeFixture::command(2,1,7002);$next=new FMonitor2\AssignmentOrderComposition\SelectAssignmentOrderCompositionCommand($command->requestId,FMonitor2\AssignmentOrderComposition\AssignmentOrderCompositionMode::NEW_ORDER,$command->installationObjectId,$command->actorUserId,$command->installerTabIds,$command->controlEngineerUserId,$command->expectedSelectionRevision);assertSameValue('selected',$original->selection->app()->selectAssignmentOrderComposition($next)->status()->value,'pending replacement');assertSameValue(['Монтажник 7001'],$native->read(18,'4512')['objects'][0]['installers'],'pending replacement does not change current team');
 $db->query('DELETE FROM fm2_control_engineer_assignments');assertSameValue(null,$native->read(18,'4512')['objects'][0]['controlEngineer'],'historical order engineer not current fallback');
 echo "PASS: ADDRESS-REGISTER-001 HTTP/read projection and native lineage\n";

}finally{if($f instanceof ObjectQueueFixture)$f->close();if($original instanceof FMonitor2\Tests\Support\SelectedOriginalFixture)$original->close();}
