<?php
/** OBJECT-GENERAL-PHOTOS-001 A1-A3: public HTTP, independent ledger assertions. */
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/ReliableFieldOfflineFixture.php';
$f=null;$fixture=null;
try{
 $fixture=new ReliableFieldOfflineFixture(dirname(__DIR__,2));$f=$fixture->inspection;$h=$f->http;
 $csrf=InspectionFixture::csrf($f->page());$revision=0;$sequence=9700;
 $photo=static function(int $section,int $revision,int $sequence,string $bytes):array{
  $op=array_replace(InspectionFixture::operation($sequence,$revision),['type'=>'photo_uploaded','sectionId'=>$section,'mime'=>'image/png','size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'originalName'=>'general-'.$sequence.'.png']);unset($op['itemId'],$op['installerTabIds']);return $op;
 };
 $sendTo=static function(int $object,array $op,string $token,?string $bytes=null,?array &$cookies=null)use($f,$h):array{
  $cookies??=$f->cookies;$headers=['X-FM2-CSRF: '.$token,'Origin: http://127.0.0.1:'.$h->server['port'],'Sec-Fetch-Site: same-origin'];
  if($bytes!==null){$headers[]='Content-Type: '.$op['mime'];$headers[]='X-FM2-Operation: '.base64_encode(json_encode($op,JSON_THROW_ON_ERROR));}else $headers[]='Content-Type: application/json';
  return $h->request('POST','/pilot/objects/'.$object.'/checklist/'.($bytes!==null?'photos':'operations'),[],$cookies,$headers,$bytes??json_encode($op,JSON_THROW_ON_ERROR));
 };
 $png=InspectionFixture::png(21);$first=$photo(0,0,$sequence++,$png);
 $accepted=InspectionFixture::result($f->send($first,$csrf,bytes:$png),200,'accepted');$revision++;
 assertSameValue(1,$accepted['revision'],'INTENDED_RED general photo accepted as first revision');
 $saved=$accepted['projection']['photos'][0];assertSameValue(0,$saved['sectionId'],'general target retained');
 assertSameValue(0,count($accepted['projection']['items']),'photo does not create item completion');
 $operation=$h->rows('fm2_checklist_operations')[0];$photoRow=$h->rows('fm2_checklist_photos')[0];
 assertSameValue([$first['clientOperationId'],$first['deviceInstallationId'],'73',$first['deviceTime'],'0','1'],[(string)$operation['client_operation_id'],(string)$operation['device_installation_id'],(string)$operation['actor_user_id'],(string)$operation['device_time'],(string)$operation['section_id'],(string)$operation['accepted_revision']],'general upload audit identity');
 assertSameValue(['sha256'=>$first['sha256'],'mime'=>'image/png','size'=>strlen($png),'originalName'=>$first['originalName']],json_decode($operation['payload_json'],true),'immutable photo payload');
 assertSameValue([$first['clientOperationId'],73,$first['deviceTime'],$first['sha256'],0],[$saved['clientOperationId'],$saved['actorUserId'],$saved['deviceTime'],$saved['sha256'],$saved['sectionId']],'receipt projection matches exact general upload');
 assertSameValue(true,is_string($saved['serverReceivedAt'])&&$saved['serverReceivedAt']!=='','server timestamp exists');
 assertSameValue([$saved['serverReceivedAt'],$first['sha256'],$first['originalName'],(string)strlen($png)],[$photoRow['server_received_at'],$photoRow['sha256'],$photoRow['original_name'],(string)$photoRow['byte_size']],'stored byte/audit fields match receipt');

 assertSameValue($png,$f->page($saved['viewUrl'])['body'],'protected photo read exact bytes');
 assertSameValue(404,$f->page('/pilot/objects/4513/checklist/photos/'.$saved['id'])['status'],'cross-object photo isolated');
 InspectionFixture::result($f->send($first,$csrf,bytes:$png),200,'duplicate');
 InspectionFixture::result($f->send(array_replace($first,['sectionId'=>1]),$csrf,bytes:$png),409,'conflict');
 assertSameValue(1,count($h->rows('fm2_checklist_operations')),'replay/conflict append no facts');
 $sameHash=$photo(0,$revision,$sequence++,$png);InspectionFixture::result($f->send($sameHash,$csrf,bytes:$png),200,'duplicate');assertSameValue(1,count($h->rows('fm2_checklist_photos')),'new request same hash consumes no general slot');
 $before=$h->facts();$targetConflict=InspectionFixture::result($sendTo(5000,$first,$csrf,$png),409,'conflict');assertSameValue('OPERATION_PAYLOAD_CONFLICT',$targetConflict['reasonCode'],'authorized upload ID cannot change object target');assertSameValue($before,$h->facts(),'same upload ID cannot move to another object');
 $otherBytes=InspectionFixture::png(199);$other=InspectionFixture::result($sendTo(5000,$photo(0,0,$sequence++,$otherBytes),$csrf,$otherBytes),200,'accepted');$foreign=$other['projection']['photos'][0];
 assertSameValue(1,count(InspectionFixture::projection($f->page())['photos']),'other-object upload absent from current projection');
 $before=$h->facts();$guest=[];$guestResponse=$sendTo(4512,$photo(0,$revision,$sequence++,$png),$csrf,$png,$guest);assertSameValue(303,$guestResponse['status'],'guest upload requires login');
 $badCsrf=InspectionFixture::result($f->send($photo(0,$revision,$sequence++,$png),'invalid',bytes:$png),400,'rejected');assertSameValue('CSRF_INVALID',$badCsrf['reasonCode'],'general photo CSRF');
 $bad='not-an-image';$invalid=InspectionFixture::result($f->send($photo(0,$revision,$sequence++,$bad),$csrf,bytes:$bad),422,'rejected');assertSameValue('PHOTO_INVALID',$invalid['reasonCode'],'general file validation');
 $closed=InspectionFixture::result($sendTo(5051,$photo(0,0,$sequence++,$png),$csrf,$png),403,'rejected');assertSameValue('ACCESS_DENIED',$closed['reasonCode'],'closed-case upload forbidden');
 foreach([999999,(int)$foreign['id']]as$id){$op=['clientOperationId'=>InspectionFixture::operation($sequence++)['clientOperationId'],'deviceInstallationId'=>$first['deviceInstallationId'],'deviceTime'=>$first['deviceTime'],'type'=>'photo_revoked','sectionId'=>0,'photoId'=>$id,'baseRevision'=>$revision,'reason'=>'fixture invalid target'];InspectionFixture::result($f->send($op,$csrf),422,'rejected');}
 assertSameValue($before,$h->facts(),'all auth/CSRF/file/closed/missing/foreign rejections append nothing');

 foreach([-1,8,'0',null]as$bad){$op=$photo(0,$revision,$sequence++,$png);$op['sectionId']=$bad;InspectionFixture::result($f->send($op,$csrf,bytes:$png),422,'rejected');}
 $before=$h->facts();foreach(['section_completed','item_completed','item_installers_changed','completion_retracted']as$type){$op=array_replace(InspectionFixture::operation($sequence++,$revision),['type'=>$type,'sectionId'=>0,'reason'=>'fixture','originalClientOperationId'=>$first['clientOperationId']]);InspectionFixture::result($f->send($op,$csrf),422,'rejected');}assertSameValue($before,$h->facts(),'zero reserved exclusively for photo commands');
 $reader=[];$h->login($reader,95);$readerPage=$h->request('GET','/pilot/objects/4512/checklist',[],$reader);$readerCsrf=InspectionFixture::csrf($readerPage);
 InspectionFixture::result($f->send($photo(0,$revision,$sequence++,$png),$readerCsrf,$reader,$png),403,'rejected');
 // A separate bucket permits the same bytes in a real section.
 InspectionFixture::result($f->send($photo(1,$revision,$sequence++,$png),$csrf,bytes:$png),200,'accepted');$revision++;
 for($n=1;$n<10;$n++){$bytes=InspectionFixture::png(21+$n);InspectionFixture::result($f->send($photo(0,$revision,$sequence++,$bytes),$csrf,bytes:$bytes),200,'accepted');$revision++;}
 $extra=InspectionFixture::png(101);$cap=InspectionFixture::result($f->send($photo(0,$revision,$sequence++,$extra),$csrf,bytes:$extra),422,'rejected');assertSameValue('PHOTO_LIMIT_REACHED',$cap['reasonCode'],'separate ten-photo general cap');
 // General photos never supply the photo required by section2.
 foreach([37,38,39,40,41]as$item){$op=array_replace(InspectionFixture::operation($sequence++,$revision,$item),['sectionId'=>2]);InspectionFixture::result($f->send($op,$csrf),200,'accepted');$revision++;}
 $complete=array_replace(InspectionFixture::operation($sequence++,$revision),['type'=>'section_completed','sectionId'=>2]);unset($complete['itemId'],$complete['installerTabIds']);$before=$h->facts();InspectionFixture::result($f->send($complete,$csrf),422,'rejected');assertSameValue($before,$h->facts(),'general photo cannot complete section2');
 $revoke=array_replace($complete,['clientOperationId'=>InspectionFixture::operation($sequence++)['clientOperationId'],'type'=>'photo_revoked','sectionId'=>0,'photoId'=>$saved['id'],'reason'=>'Ошибочный общий снимок']);
 InspectionFixture::result($f->send($revoke,$readerCsrf,$reader),403,'rejected');
 InspectionFixture::result($f->send($revoke,$csrf),200,'accepted');$revision++;
 InspectionFixture::result($f->send($revoke,$csrf),200,'duplicate');
 $before=$h->facts();$again=array_replace($revoke,['clientOperationId'=>InspectionFixture::operation($sequence++)['clientOperationId'],'baseRevision'=>$revision]);InspectionFixture::result($f->send($again,$csrf),422,'rejected');$foreignRevoke=array_replace($revoke,['clientOperationId'=>InspectionFixture::operation($sequence++)['clientOperationId']]);$denied=InspectionFixture::result($sendTo(5000,$foreignRevoke,$csrf),403,'rejected');assertSameValue('ACCESS_DENIED',$denied['reasonCode'],'fresh foreign revoke respects assignment boundary');
 $changedRevoke=array_replace($revoke,['reason'=>'Другая причина']);$conflict=InspectionFixture::result($f->send($changedRevoke,$csrf),409,'conflict');assertSameValue('OPERATION_PAYLOAD_CONFLICT',$conflict['reasonCode'],'authorized accepted revoke ID cannot change payload');assertSameValue($before,$h->facts(),'already-revoked and cross-object revoke append nothing');
 $revokeRows=array_values(array_filter($h->rows('fm2_checklist_operations'),fn($r)=>$r['operation_type']==='photo_revoked'));
 assertSameValue([$revoke['clientOperationId'],$revoke['deviceInstallationId'],'73',$revoke['deviceTime'],'0','17'],[$revokeRows[0]['client_operation_id'],$revokeRows[0]['device_installation_id'],(string)$revokeRows[0]['actor_user_id'],$revokeRows[0]['device_time'],(string)$revokeRows[0]['section_id'],(string)$revokeRows[0]['accepted_revision']],'revoke immutable audit identity');
 assertSameValue(['photoId'=>$saved['id'],'reason'=>'Ошибочный общий снимок'],json_decode($revokeRows[0]['payload_json'],true),'revoke reason and target preserved');

 $rows=array_values(array_filter($h->rows('fm2_checklist_photos'),fn($r)=>(int)$r['installation_case_id']===6101));assertSameValue(11,count($rows),'original upload rows preserved');assertSameValue(1,count(array_filter($rows,fn($r)=>$r['revoked_at']!==null)),'one explicit revocation');
 $operations=array_values(array_filter($h->rows('fm2_checklist_operations'),fn($r)=>(int)$r['installation_case_id']===6101));assertSameValue(17,count($operations),'11 uploads +5 item facts +1 revoke, no rejected/replay facts');
 assertSameValue(1,count(array_filter($operations,fn($r)=>$r['operation_type']==='photo_revoked'&&(int)$r['section_id']===0)),'general revoke history retained');
 echo "PASS: OBJECT-GENERAL-PHOTOS-001 A1-A3 HTTP\n";
}finally{if($fixture)$fixture->close();}
