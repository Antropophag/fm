<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PasswordAccessFixture.php';
// YII2-USER-ACCESS-001: complete read/rejection/fault matrix; same vertical slice.
function yuaSafe(array $r,UserAccessFixture $f,string $label):void
{
 assertSameValue(503,$r['status'],$label);assertSameValue(['ok'=>false,'reason'=>'SERVICE_UNAVAILABLE'],json_decode($r['body'],true),$label.' generic body');
 assertSameValue(false,isset($r['headers']['location']),$label.' no redirect');assertSameValue(false,isset($r['headers']['set-cookie']),$label.' no cookie');assertSameValue(['no-store'],$r['headers']['cache-control']??[],$label.' no cache');
 foreach([$f->dmlPassword,$f->auth->password,$f->auth->passwordHash,$f->auth->sessionPath,'SQLSTATE','+7 (900) 111-22-33']as$v)assertSameValue(false,str_contains(serialize($r),$v),$label.' hides private content');
}
function yuaTime(string $value,int $start,string $label):void{$time=strtotime($value);assertSameValue(true,$time!==false&&$time>=$start-2&&$time<=time()+2,$label.' server event time');}
function yuaRow(string $html,int $id):string{assertSameValue(1,preg_match('#<tr[^>]*data-user-id="'.$id.'"[^>]*>(.*?)</tr>#s',$html,$m),'rendered row '.$id);return html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8');}
$pf=new PasswordAccessFixture();$f=$pf->web;
try {
$owner=$pf->owner();$before=$f->facts();
foreach([static fn()=>$owner->provision(9401,'not-email',''),static fn()=>$owner->changeRole(9401,9403,9212,'nonsense'),static fn()=>$owner->changeStatus(9401,9403,'nonsense')]as$operation)assertSameValue('access_denied',$operation()['status'],'authority precedes malformed input');
assertSameValue($before,$f->facts(),'denial unchanged');
$f->db->query("RENAME TABLE {$f->p}fm2_pilot_role_permissions TO {$f->p}unavailable_role_permissions");
try {foreach([static fn()=>$owner->provision(9101,'not-email',''),static fn()=>$owner->changeRole(9101,9403,9212,'nonsense'),static fn()=>$owner->changeStatus(9101,9101,'block')]as$operation){$failed=false;try{$operation();}catch(Throwable){$failed=true;}assertSameValue(true,$failed,'authorization source failure is not input denial');}}
finally{$f->db->query("RENAME TABLE {$f->p}unavailable_role_permissions TO {$f->p}fm2_pilot_role_permissions");}
$f->start();$admin=[];$f->page($admin);$r=$f->login($admin);assertSameValue('/pilot/admin/users',$r['headers']['location'][0]??null,'safe saved return');
$pending=$owner->provision(9101,'read.pending@shlz.ru','Read Pending');$pendingId=$pending['userId'];$before=$f->facts();
$directory=$owner->directory(9101);$users=array_column($directory['users'],null,'id');$roles=array_column($directory['roles'],null,'id');
assertSameValue(['Fixture 9403','other.person@shlz.ru','+7 (900) 111-22-33',true],[$users[9403]['name'],$users[9403]['email'],$users[9403]['phone'],$users[9403]['active']],'directory fields');assertSameValue(['checklist.edit'],$users[9403]['permissions'],'only active assigned permissions');assertSameValue(['control_engineer','Инженер',true],[$roles[9212]['code'],$roles[9212]['name'],$roles[9212]['active']],'role projection');
$page=$f->page($admin);assertSameValue(true,str_contains(yuaRow($page['body'],$pendingId),'Ожидает первого входа'),'pending label');
$pendingEditor=$f->request('GET','/pilot/admin/users/'.$pendingId.'/editor',[],$admin);assertSameValue(false,str_contains($pendingEditor['body'],'Перевыпустить'),'no reissue');assertSameValue(true,str_contains($pendingEditor['body'],'Заблокировать'),'pending may be blocked');
$editor=$f->request('GET','/pilot/admin/users/9403/editor',[],$admin);assertSameValue(true,str_contains($editor['body'],'Ведение чек-листов'),'effective role label');assertSameValue(false,str_contains($editor['body'],'Управление расчётами ОТиЗ'),'inactive role permission excluded');
// Assigned role labels must be visible in the same exact form as their removal action.
$roleDocument=new DOMDocument();$previous=libxml_use_internal_errors(true);
try{assertSameValue(true,$roleDocument->loadHTML('<?xml encoding="UTF-8">'.$editor['body']),'editor HTML parses');}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
$roleXPath=new DOMXPath($roleDocument);
foreach([9212=>'Инженер',9213=>'Архивная роль']as$roleId=>$name){
 $forms=$roleXPath->query('//form[@action="/pilot/admin/users/9403/roles/'.$roleId.'" and input[@name="action" and @value="detach"]]');
 assertSameValue(1,$forms->length,'one exact detach form for assigned role '.$roleId);
 $labels=$roleXPath->query('./span[not(@hidden)]',$forms->item(0));
 assertSameValue(1,$labels->length,'INTENDED_RED assigned role has visible label beside removal '.$roleId);
 assertSameValue($name,trim($labels->item(0)->textContent),'visible label belongs to exact assigned role');
 assertSameValue('Снять роль '.$name,$roleXPath->query('./button[@type="submit"]',$forms->item(0))->item(0)->getAttribute('aria-label'),'accessible removal name matches visible role');
}

foreach([$f->auth->passwordHash,$f->auth->password]as$v)assertSameValue(false,str_contains(serialize($directory).$page['body'].$editor['body'],$v),'read hides credentials');assertSameValue($before,$f->facts(),'read no facts');
$f->db->query("DELETE FROM {$f->p}fm2_pilot_role_permissions WHERE role_id=9201 AND permission='access.administer'");assertSameValue(403,$f->page($admin)['status'],'committed revoke next request');assertSameValue('access_denied',$owner->provision(9101,'revoked@shlz.ru','Revoked')['status'],'owner rereads permissions');$f->db->query("INSERT INTO {$f->p}fm2_pilot_role_permissions VALUES(9201,'access.administer')");
$f->db->query("RENAME TABLE {$f->p}fm2_pilot_user_roles TO {$f->p}hidden_user_roles");try{yuaSafe($f->page($admin),$f,'missing protected schema');}finally{$f->db->query("RENAME TABLE {$f->p}hidden_user_roles TO {$f->p}fm2_pilot_user_roles");}
foreach([[99999,9212,'attach','invalid'],[9403,9212,'nonsense','invalid'],[9401,9212,'detach','unchanged']]as[$target,$role,$action,$status]){$before=$f->facts();assertSameValue($status,$owner->changeRole(9101,$target,$role,$action)['status'],'complete role rejection/replay');assertSameValue($before,$f->facts(),'role rejected or absent detach no event');}
foreach([[9403,9210,'detach',400],[9403,9213,'attach',400],[9403,9211,'attach',403],[99999,9212,'attach',400],[9403,9212,'nonsense',400]]as[$target,$role,$action,$status]){$before=$f->facts();$r=$f->post('/pilot/admin/users/'.$target.'/roles/'.$role,['action'=>$action],$admin);assertSameValue($status,$r['status'],'role exact HTTP rejection');assertSameValue(false,str_contains($r['body'],'read.invited@shlz.ru'),'role denial no protected content');assertSameValue($before,$f->facts(),'role HTTP failure no facts');}
// Every successful administrative form consumes its old session CSRF, including reissue and roles.
foreach([['/pilot/admin/users/9403/roles/9212',['action'=>'detach']],['/pilot/admin/users/9403/roles',['action'=>'attach','roleId'=>'9212']],['/pilot/admin/users/9403/status',['action'=>'block']],['/pilot/admin/users/9403/status',['action'=>'unblock']]]as[$path,$fields]){$csrf=$f->csrf($f->page($admin)['body']);$priorEvents=[];foreach(['fm2_pilot_user_role_events','fm2_pilot_user_status_events']as$table)$priorEvents[$table]=$f->rows($table);$start=time();assertSameValue(303,$f->request('POST',$path,['_csrf'=>$csrf]+$fields,$admin)['status'],'valid mutation');$before=$f->facts();assertSameValue(400,$f->request('POST',$path,['_csrf'=>$csrf]+$fields,$admin)['status'],'old form token rejected');assertSameValue($before,$f->facts(),'old form no duplicate facts');foreach(['fm2_pilot_user_role_events','fm2_pilot_user_status_events']as$table){$rows=array_slice($f->rows($table),count($priorEvents[$table]));foreach($rows as$last){assertSameValue(9101,(int)$last['actor_user_id'],'event attribution');yuaTime($last['occurred_at'],$start,'role/status');}}}

foreach([['/pilot/admin/users/create',['email'=>['bad'],'fullName'=>'Bad']],['/pilot/admin/users/9403/roles',['roleId'=>['9212'],'action'=>'attach']],['/pilot/admin/users/9403/status',['action'=>['block']]]]as[$path,$fields]){$before=$f->facts();assertSameValue(400,$f->post($path,$fields,$admin)['status'],'non-scalar rejected');assertSameValue($before,$f->facts(),'non-scalar unchanged');}
// Failure after possible commit is not success and does not require invitation reissue.
$page=$f->page($admin);$csrf=$f->csrf($page['body']);$port=$f->server['port'];$f->stop();$f->start('write',$port);$r=$f->request('POST','/pilot/admin/users/create',['_csrf'=>$csrf,'email'=>'delivery.fault@shlz.ru','fullName'=>'Delivery fault'],$admin);yuaSafe($r,$f,'session delivery failure');$f->stop();$f->start(null,$port);
$rows=$f->db->query("SELECT user_id FROM {$f->p}fm2_pilot_users WHERE email='delivery.fault@shlz.ru'")->fetch_all(MYSQLI_ASSOC);assertSameValue(true,count($rows)<=1,'no duplicate after failure');if($rows===[])$owner->provision(9101,'delivery.fault@shlz.ru','Delivery fault');
assertSameValue('activated',$owner->setInitialPassword('delivery.fault@shlz.ru',PasswordAccessFixture::PASSWORD,PasswordAccessFixture::PASSWORD)['status'],'first password possible after uncertain create response');assertSameValue('invalid',$owner->setInitialPassword('delivery.fault@shlz.ru',PasswordAccessFixture::NEW_PASSWORD,PasswordAccessFixture::NEW_PASSWORD)['status'],'cannot overwrite committed password');
$log=file_get_contents($f->artifacts.'/server.log');foreach([$f->auth->password,$f->dmlPassword]as$v)assertSameValue(false,str_contains($log,$v),'safe server log');
$f->noLegacy();echo "PASS: user access projection, authorization, form replay and uncertain commit\n";
}finally{$pf->close();}
