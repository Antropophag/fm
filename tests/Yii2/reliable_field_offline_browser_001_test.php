<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/ReliableFieldOfflineFixture.php';
$f=null;$process=null;
try{
    $f=new ReliableFieldOfflineFixture(dirname(__DIR__,2));$h=$f->inspection->http;$h->db->query("UPDATE {$h->p}fm_maintable SET ordadr_address='ул. Берёзовая, д.12, корп.2',entrance='3' WHERE id IN(5051,5052)");$config=$h->artifacts.'/field-browser.json';$result=$h->artifacts.'/field-result.json';$log=$h->artifacts.'/field-browser.log';
    file_put_contents($config,json_encode(['origin'=>'http://127.0.0.1:'.$h->server['port'],'email'=>$h->emails[73],'otherEmail'=>$h->emails[95],'password'=>$h->password,'artifacts'=>$h->artifacts,'result'=>$result,'png'=>base64_encode(InspectionFixture::png(87)),'playwright'=>getenv('FMONITOR_TEST_PLAYWRIGHT_MODULE')?:dirname($h->root).'/shlz-ui/node_modules/playwright'],JSON_THROW_ON_ERROR));chmod($config,0600);
    $process=proc_open([getenv('FMONITOR_TEST_NODE_BINARY')?:'node',__DIR__.'/reliable_field_offline_browser.cjs',$config],[0=>['file','/dev/null','r'],1=>['file',$log,'w'],2=>['file',$log,'a']],$pipes,$h->root);
    if(!is_resource($process))throw new TestFailure('SETUP_FAILURE browser');$deadline=microtime(true)+360;
    do{$state=proc_get_status($process);if(!$state['running'])break;usleep(20000);}while(microtime(true)<$deadline);
    if($state['running'])throw new TestFailure('bounded browser timeout '.$h->artifacts);$exit=$state['exitcode'];proc_close($process);$process=null;
    assertSameValue(0,$exit,'INTENDED_RED A1-A9 field browser '.file_get_contents($log));$observed=json_decode(file_get_contents($result),true,flags:JSON_THROW_ON_ERROR);assertSameValue(25,count($observed),'all required field acceptance scenarios executed');assertSameValue([],array_values(array_filter($observed,fn($r)=>!$r['pass'])),'all field acceptance cases');
    $ops=$h->rows('fm2_checklist_operations');$ids=array_column($ops,'client_operation_id');assertSameValue(count($ids),count(array_unique($ids)),'A4 server independent no replay duplicates');assertSameValue(array_fill(0,count($ops),'73'),array_column($ops,'actor_user_id'),'A5 exact authorized engineer attribution');
    $completed=array_values(array_filter($ops,fn($r)=>$r['operation_type']==='item_completed'&&(int)$r['installation_case_id']===6101));$completedItems=array_map('intval',array_column($completed,'item_id'));sort($completedItems);assertSameValue(array_merge(range(28,38),[40]),$completedItems,'A4 independent server facts: each intended completed item once; restart-only39 stays local');$seenRevisions=array_map('intval',array_column(array_values(array_filter($ops,fn($r)=>(int)$r['installation_case_id']===6101)),'accepted_revision'));assertSameValue(range(1,count($seenRevisions)),$seenRevisions,'A4 server exact contiguous history revisions');
    $h->noLegacy();echo 'PASS: RELIABLE-FIELD-OFFLINE-001 browser '.$h->artifacts."\n";
}finally{if(is_resource($process)){proc_terminate($process,9);proc_close($process);}if(isset($config)&&is_file($config))unlink($config);if($f)$f->close();}
