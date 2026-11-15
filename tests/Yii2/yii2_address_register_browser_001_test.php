<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/ObjectQueueFixture.php';
$f=null;$process=null;
try{
 $f=new ObjectQueueFixture(dirname(__DIR__,2));$h=$f->http;
 for($i=1;$i<=53;$i++)$f->object(451201+$i,6101+$i,$i<=2?'working':'needs_assignment_order','2026-09-12',$i<=2?'Адаптивный объект '.($i+1):'Тестовый проспект, дом '.($i+1));

 // Synthetic persisted current compositions: exact 1/2/5-name UI examples, no production data.
 $f->db->query("UPDATE fm_maintable SET ordadr_address='Адаптивный объект 1' WHERE id=451201");
 $names=['Иванов Иван Иванович','Петров Пётр Петрович','Сидоров-Петров Алексей Александрович','Смирнов Андрей Владимирович','Кузнецов Николай Константинович'];
 foreach([1,2,5]as$index=>$count){$case=6101+$index;$object=451201+$index;$order=9001+$index;$event=9901+$index;$ids=range(7001,7000+$count);$identity='composition-'.$order.'-v1';$composition=['caseId'=>$case,'compositionIdentity'=>$identity,'engineerUserId'=>7301,'installers'=>$ids,'orderId'=>$order];$people=[];foreach($ids as$i=>$id)$people[]=['tabId'=>$id,'fullName'=>$names[$i],'position'=>'Монтажник'];
 $f->insert($f->p.'fm2_assignment_order_identities',['assignment_order_id'=>$order,'installation_case_id'=>$case,'order_version'=>1,'source_kind'=>'legacy_order','allocated_at_utc'=>'2026-09-01 06:00:00']);
 $f->insert($f->p.'fm2_process_events',['id'=>$event,'installation_case_id'=>$case,'event_type'=>'assignment_order_applied','occurred_at'=>'2026-09-01T06:00:00Z','actor_user_id'=>9101,'payload_json'=>'{}']);
 $f->insert($f->p.'fm2_assignment_order_applications',['installation_case_id'=>$case,'object_id'=>$object,'application_sequence'=>1,'assignment_order_id'=>$order,'order_version'=>1,'original_revision_id'=>'fixture-'.$index,'original_revision_number'=>1,'document_date'=>'2026-09-01','composition_identity'=>$identity,'composition_sha256'=>hash('sha256',json_encode($composition,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'control_engineer_user_id'=>7301,'previous_application_id'=>null,'kind'=>'initial','applied_at_utc'=>'2026-09-01 06:00:00','applied_by_user_id'=>9101,'request_id'=>sprintf('12345678-1234-4234-8234-%012d',$case),'request_fingerprint'=>hash('sha256','crew-'.$index),'expected_application_sequence'=>0,'process_event_id'=>$event,'selected_snapshot_json'=>json_encode(['selectedInstallers'=>$people,'selectedEngineer'=>['userId'=>7301,'fullName'=>'Инженер теста','position'=>'Инженер строительного контроля']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'eligibility_snapshot_json'=>'{}']);
 }
 $longRegistration='R'.str_repeat('7',119);$statement=$f->db->prepare('UPDATE fm_maintable SET regnumber=? WHERE id=451204');$statement->execute([$longRegistration]);
 $state=$h->artifacts.'/private-state';mkdir($state,0700);
 $h->start(extraEnvironment:['FMONITOR_SESSION_STATE_ROOT'=>$state]);
 $qa=$f->root.'/.local/address-register-qa';if(!is_dir($qa))mkdir($qa,0700,true);
 $config=$h->artifacts.'/browser.json';file_put_contents($config,json_encode(['origin'=>'http://127.0.0.1:'.$h->server['port'],'email'=>$h->auth->email,'password'=>$h->auth->password,'artifacts'=>$qa,'result'=>$h->artifacts.'/result.json','playwright'=>getenv('FMONITOR_TEST_PLAYWRIGHT_MODULE')?:dirname($f->root).'/shlz-ui/node_modules/playwright'],JSON_THROW_ON_ERROR));chmod($config,0600);
 $before=$f->facts();$process=proc_open([getenv('FMONITOR_TEST_NODE_BINARY')?:'node',__DIR__.'/address_register_browser.mjs',$config],[0=>['file','/dev/null','r'],1=>['file',$h->artifacts.'/browser.log','a'],2=>['file',$h->artifacts.'/browser.log','a']],$pipes,$f->root);
 if(!is_resource($process))throw new TestFailure('browser spawn');$deadline=microtime(true)+110;do{$s=proc_get_status($process);if(!$s['running'])break;usleep(20000);}while(microtime(true)<$deadline);if($s['running'])throw new TestFailure('browser timeout '.$h->artifacts);
 $exit=$s['exitcode'];proc_close($process);$process=null;assertSameValue(0,$exit,'browser flow '.file_get_contents($h->artifacts.'/browser.log'));
 $result=json_decode(file_get_contents($h->artifacts.'/result.json'),true,flags:JSON_THROW_ON_ERROR);assertSameValue(true,$result['complete'],'complete interaction');assertSameValue([],$result['assetFailures'],'assets work');assertSameValue($before,$f->facts(),'UI no business/schema writes');
 echo 'PASS: ADDRESS-REGISTER-001 browser; screenshots '.$qa."\n";
}finally{if(is_resource($process)){proc_terminate($process,9);proc_close($process);}if($f instanceof ObjectQueueFixture)$f->close();}
