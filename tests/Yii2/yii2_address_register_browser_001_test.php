<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/ObjectQueueFixture.php';
$f=null;$process=null;
try{
 $f=new ObjectQueueFixture(dirname(__DIR__,2));$h=$f->http;
 for($i=1;$i<=53;$i++)$f->object(451201+$i,6101+$i,'needs_assignment_order','2026-09-12','Тестовый проспект, дом '.($i+1));
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
