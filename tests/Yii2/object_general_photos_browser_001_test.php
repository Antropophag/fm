<?php
/** OBJECT-GENERAL-PHOTOS-001 A4-A5: real UI/local queue/automatic replay. */
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/InspectionFixture.php';
$f=null;$process=null;
try{
 $f=new InspectionFixture(dirname(__DIR__,2));$f->open();$h=$f->http;$config=$h->artifacts.'/general-browser.json';$result=$h->artifacts.'/general-result.json';$log=$h->artifacts.'/general-browser.log';
 file_put_contents($config,json_encode(['origin'=>'http://127.0.0.1:'.$h->server['port'],'email'=>$h->emails[73],'readerEmail'=>$h->emails[95],'password'=>$h->password,'artifacts'=>$h->artifacts,'result'=>$result,'png'=>base64_encode(InspectionFixture::png(172)),'playwright'=>getenv('FMONITOR_TEST_PLAYWRIGHT_MODULE')?:dirname($h->root).'/shlz-ui/node_modules/playwright'],JSON_THROW_ON_ERROR));chmod($config,0600);
 $process=proc_open([getenv('FMONITOR_TEST_NODE_BINARY')?:'node',dirname(__DIR__).'/Support/object_general_photos_browser.cjs',$config],[0=>['file','/dev/null','r'],1=>['file',$log,'w'],2=>['file',$log,'a']],$pipes,$h->root);
 if(!is_resource($process))throw new TestFailure('SETUP_FAILURE browser');$deadline=microtime(true)+150;
 do{$state=proc_get_status($process);if(!$state['running'])break;usleep(20000);}while(microtime(true)<$deadline);
 if($state['running'])throw new TestFailure('bounded general browser timeout '.$h->artifacts);$exit=$state['exitcode'];proc_close($process);$process=null;
 assertSameValue(0,$exit,'general photo browser '.file_get_contents($log));
 $observed=json_decode(file_get_contents($result),true,flags:JSON_THROW_ON_ERROR);$photos=$h->rows('fm2_checklist_photos');$ops=$h->rows('fm2_checklist_operations');
 assertSameValue(1,count($photos),'one server photo after automatic replay');assertSameValue('0',(string)$photos[0]['section_id'],'server general bucket');assertSameValue(2,count($ops),'only upload and explicit revoke, no fake section completion');assertSameValue(true,$photos[0]['revoked_at']!==null,'general revoke preserves upload history');assertSameValue(['photo_uploaded','photo_revoked'],array_column($ops,'operation_type'),'ordered public history');assertSameValue($observed['clientOperationId'],$ops[0]['client_operation_id'],'same durable operation identity');
 echo 'PASS: OBJECT-GENERAL-PHOTOS-001 browser '.$h->artifacts."\n";
}finally{if(is_resource($process)){proc_terminate($process,9);proc_close($process);}if(isset($config)&&is_file($config))unlink($config);if($f)$f->close();}
