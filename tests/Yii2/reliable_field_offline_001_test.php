<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/ReliableFieldOfflineFixture.php';
$f=null;
try{
    $f=new ReliableFieldOfflineFixture(dirname(__DIR__,2));$i=$f->inspection;$h=$i->http;$before=$h->facts();
    $a=$i->page('/pilot/construction-control/offline-manifest?page=1&ownership=all&query=NO-MATCH');
    assertSameValue(200,$a['status'],'INTENDED_RED A1 protected mine manifest exists');
    $a=json_decode($a['body'],true,flags:JSON_THROW_ON_ERROR);$b=$i->page('/pilot/construction-control/offline-manifest?page=2');
    assertSameValue(200,$b['status'],'A1 second page available');$b=json_decode($b['body'],true,flags:JSON_THROW_ON_ERROR);
    assertSameValue([73,1,2,53,50],[$a['userId'],$a['page'],$a['pages'],$a['total'],count($a['items'])],'A1 first manifest bounded/full count');
    assertSameValue([73,2,2,53,3],[$b['userId'],$b['page'],$b['pages'],$b['total'],count($b['items'])],'A1 tail is never hidden by UI filters');
    assertSameValue(true,is_string($a['setVersion']??null)&&preg_match('/^[a-f0-9]{64}$/D',$a['setVersion'])===1,'A1 complete set digest');assertSameValue($a['setVersion'],$b['setVersion'],'A1 pagination belongs to same set');
    $ids=array_column(array_merge($a['items'],$b['items']),'objectId');sort($ids);
    assertSameValue(array_merge([4512],range(5000,5051)),$ids,'A1 native mine only, exact53, no colleagues');
    $completed=array_values(array_filter(array_merge($a['items'],$b['items']),fn($r)=>$r['objectId']===5051))[0];assertSameValue(true,$completed['completed'],'A1 completed own read-only retained');assertSameValue(true,str_contains($i->page('/pilot/objects/5051/checklist')['body'],'data-enabled="false"'),'A1 completed checklist not editable');
    foreach(array_merge($a['items'],$b['items'])as$row){assertSameValue('/pilot/construction-control/objects/'.$row['objectId'].'/checklist',$row['checklistUrl'],'A1 canonical same-origin route');foreach(['address','registrationNumber','entrance','completed']as$key)assertSameValue(true,array_key_exists($key,$row),'manifest visible metadata '.$key);}
    foreach(['0','-1','01','x','%5B%5D=1']as$bad){$path=str_starts_with($bad,'%')?'/pilot/construction-control/offline-manifest?page'.$bad:'/pilot/construction-control/offline-manifest?page='.$bad;assertSameValue(404,$i->page($path)['status'],'A1 invalid pagination rejected '.$bad);}
    $head=$h->request('HEAD','/pilot/construction-control/offline-manifest?page=1',[],$i->cookies);assertSameValue([200,''],[$head['status'],$head['body']],'A1 HEAD no body');
    $guest=[];$denied=$h->request('GET','/pilot/construction-control/offline-manifest',[],$guest);assertSameValue(true,in_array($denied['status'],[303,401],true),'A1 guest no private manifest');
    $reader=[];$h->login($reader,95);assertSameValue(403,$h->request('GET','/pilot/construction-control/offline-manifest',[],$reader)['status'],'A1 read-only nonqueue role cannot enumerate');
    $ctx=$i->page('/pilot/construction-control/objects/4512/sync-context');$body=json_decode($ctx['body'],true,flags:JSON_THROW_ON_ERROR);
    assertSameValue([200,73,0],[$ctx['status'],$body['userId']??null,$body['revision']??null],'A5 fresh identity-bound sync context');assertSameValue(true,is_array($body['projection']??null)&&is_string($body['csrf']??null),'A5 fresh projection and CSRF');
    $page=$i->page('/pilot/construction-control');assertSameValue(true,str_contains($page['body'],'data-offline-summary'),'A2 readiness is public visible UI');
    assertSameValue(1,preg_match('/<link[^>]+rel="manifest"[^>]+href="([^"]+)"/',$page['body'],$match),'A2 installable manifest link');
    $manifest=$i->page(html_entity_decode($match[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));assertSameValue(200,$manifest['status'],'A2 manifest served');$m=json_decode($manifest['body'],true,flags:JSON_THROW_ON_ERROR);
    assertSameValue(['/pilot/construction-control','/pilot/','standalone'],[$m['start_url'],$m['scope'],$m['display']],'A2 offline-capable PWA start');assertSameValue(true,is_string($m['id']??null)&&$m['id']!=='','A2 stable install identity');assertSameValue(true,is_string($m['name']??null)&&$m['name']!==''&&is_string($m['theme_color']??null),'A2 install name/theme');
    foreach(['192x192','512x512']as$size){$icons=array_values(array_filter($m['icons'],fn($x)=>str_contains($x['sizes'],$size)));assertSameValue(true,count($icons)>0,'A2 icon '.$size);$icon=$i->page($icons[0]['src']);assertSameValue([200,"\x89PNG\r\n\x1a\n"],[$icon['status'],substr($icon['body'],0,8)],'A2 actual PNG icon');assertSameValue('image/png',explode(';',$icon['headers']['content-type'][0]??'')[0],'A2 PNG MIME');$dimensions=getimagesizefromstring($icon['body']);$expected=(int)explode('x',$size)[0];assertSameValue([$expected,$expected],[$dimensions[0],$dimensions[1]],'A2 actual icon pixels');}
    $shell=$i->page('/pilot/assets/field-shell.json');$shellManifest=json_decode($shell['body'],true,flags:JSON_THROW_ON_ERROR);assertSameValue(200,$shell['status'],'A2 exact build manifest served');assertSameValue(hash('sha256',json_encode($shellManifest['assets'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),$shellManifest['generation'],'A8 immutable aggregate asset generation');
    $worker=$i->page('/pilot/assets/checklist-sw.js');assertSameValue(200,$worker['status'],'A8 worker served');assertSameValue(false,str_contains($worker['body'],'__FMONITOR_WORKER_GENERATION__'),'A8 worker build placeholder rendered');assertSameValue(true,str_contains($worker['body'],$shellManifest['generation']),'A8 worker bound to exact shell build');
    foreach($shellManifest['assets']as$asset){assertSameValue(1,preg_match('~^/pilot/assets/[a-z0-9.-]+\?v=[a-f0-9]{12}$~D',$asset),'A2 exact content-versioned mandatory URL');assertSameValue(200,$i->page($asset)['status'],'A2 mandatory asset reachable '.$asset);}
    foreach(['/pilot/objects','/pilot/calendar']as$custom){$customPage=$i->page($custom);assertSameValue(200,$customPage['status'],'A8 custom authenticated shell');assertSameValue(true,str_contains($customPage['body'],'data-authenticated-user-id="73"')&&str_contains($customPage['body'],'field-offline-lifecycle.js?v='),'A8 shared identity/logout coordinator on custom shell');}
    // A8 an offline logout may leave the server cookie alive: explicit credentials
    // must really be checked, and a recovery route must not bounce to hidden pages.
    assertSameValue(303,$i->page('/pilot/login')['status'],'A8 ordinary authenticated GET login keeps existing redirect');
    $reauth=$i->page('/pilot/login?reauth=1');assertSameValue(200,$reauth['status'],'INTENDED_RED A8 forced reauthentication remains reachable with surviving cookie');assertSameValue(true,str_contains($reauth['body'],'name="email"'),'A8 explicit login form');
    assertSameValue(400,$h->request('POST','/pilot/login',['_csrf'=>'invalid','email'=>$h->emails[73],'password'=>$h->password],$i->cookies)['status'],'A8 reauthentication retains native CSRF enforcement');
    $wrongLogin=$h->request('POST','/pilot/login',['_csrf'=>$h->csrf($reauth['body']),'email'=>$h->emails[73],'password'=>'intentionally-incorrect-offline-test'],$i->cookies);assertSameValue(200,$wrongLogin['status'],'A8 cookie does not bypass explicit password verification');assertSameValue(true,str_contains($wrongLogin['body'],'name="password"')&&str_contains($wrongLogin['body'],'Не удалось войти'),'A8 failed password never emits successful login redirect');
    $goodLogin=$h->request('POST','/pilot/login',['_csrf'=>$h->csrf($wrongLogin['body']),'email'=>$h->emails[73],'password'=>$h->password],$i->cookies);assertSameValue(303,$goodLogin['status'],'A8 actual password login can resume preserved actor queue');
    assertSameValue(200,$i->page('/pilot/otiz/login?reauth=1')['status'],'A8 existing login alias supports explicit reauthentication');
    $empty=[];$h->login($empty,97);$emptyResponse=$h->request('GET','/pilot/construction-control/offline-manifest',[],$empty);$emptyBody=json_decode($emptyResponse['body'],true,flags:JSON_THROW_ON_ERROR);assertSameValue([200,0,[]],[$emptyResponse['status'],$emptyBody['total']??null,$emptyBody['items']??null],'A1 empty own set');
    // A9 no domain writes from reads: compare before permission-only fixture mutation.
    assertSameValue($before,$h->facts(),'A1/A9 preparation/read creates no domain facts');
    // A5/A6/A7 stable production reason codes; never infer from a mocked localized error.
    $csrf=InspectionFixture::csrf($i->page());$first=InspectionFixture::operation(9000,0,28);InspectionFixture::result($i->send($first,$csrf),200,'accepted');
    $checkCode=static function(array$r,int$status,string$code)use($h):void{$body=json_decode($r['body'],true,flags:JSON_THROW_ON_ERROR);assertSameValue([$status,$code],[$r['status'],$body['reasonCode']??null],'A5/A6 stable reason '.$code);};
    $checkCode($i->send(InspectionFixture::operation(9001,0,29),$csrf),409,'STALE_REVISION');
    $checkCode($i->send(array_replace($first,['itemId'=>29]),$csrf),409,'OPERATION_PAYLOAD_CONFLICT');
    $checkCode($i->send(InspectionFixture::operation(9002,1,29),''),400,'CSRF_INVALID');
    $bad='not-a-png';$op=array_replace(InspectionFixture::operation(9003,1),['type'=>'photo_uploaded','sectionId'=>2,'mime'=>'image/png','size'=>strlen($bad),'sha256'=>hash('sha256',$bad),'originalName'=>'invalid.png']);unset($op['itemId'],$op['installerTabIds']);
    $checkCode($i->send($op,$csrf,bytes:$bad),422,'PHOTO_INVALID');
    for($n=0;$n<11;$n++){$bytes=InspectionFixture::png(100+$n);$o=array_replace($op,['baseRevision'=>1+$n,'clientOperationId'=>InspectionFixture::operation(9010+$n)['clientOperationId'],'size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'originalName'=>'limit-'.$n.'.png']);$r=$i->send($o,$csrf,bytes:$bytes);if($n<10)InspectionFixture::result($r,200,'accepted');else$checkCode($r,422,'PHOTO_LIMIT_REACHED');}
    $readerPage=$h->request('GET','/pilot/objects/4512/checklist',[],$reader);$readerCsrf=InspectionFixture::csrf($readerPage);$checkCode($i->send(InspectionFixture::operation(9030,11,29),$readerCsrf,$reader),403,'ACCESS_DENIED');
    $closed=$h->request('POST','/pilot/objects/5051/checklist/operations',[],$i->cookies,['X-FM2-CSRF: '.$csrf,'Content-Type: application/json'],json_encode(InspectionFixture::operation(9040,0,28),JSON_THROW_ON_ERROR));$checkCode($closed,409,'CASE_NOT_WORKING');
    echo "PASS: RELIABLE-FIELD-OFFLINE-001 A1/A2/A5/A6/A7 HTTP\n";
}finally{if($f)$f->close();}
