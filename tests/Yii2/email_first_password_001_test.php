<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PasswordAccessFixture.php';
// EMAIL-FIRST-PASSWORD-RECOVERY-001: actual HTTP commands, independently observed facts.
$f=new PasswordAccessFixture();$w=$f->web;
try {
    $f->start();$admin=[];assertSameValue(303,$w->login($admin)['status'],'existing admin password preserved');
    $page=$w->page($admin);
    assertSameValue(true,str_contains($page['body'],'/pilot/admin/users/create'),'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 admin creates user without invitation');
    assertSameValue(false,str_contains($page['body'],'data-invitation-copy'),'no invitation UI');
    $before=$f->snapshot();$guest=[];$form=$w->request('GET','/pilot/login',[],$guest);
    $csrf=$w->csrf($form['body']);
    assertSameValue(303,$w->request('POST','/pilot/admin/users/create',['_csrf'=>$csrf,'email'=>'guest@shlz.ru','fullName'=>'Guest'],$guest)['status'],'anonymous cannot provision');
    assertSameValue($before,$f->snapshot(),'anonymous does not mutate');
    $regular=[];$regularForm=$w->request('GET','/pilot/login',[],$regular);$regularCsrf=$w->csrf($regularForm['body']);assertSameValue(303,$w->login($regular,'ordinary.person@shlz.ru')['status'],'regular login');
    assertSameValue(403,$w->request('POST','/pilot/admin/users/create',['_csrf'=>$regularCsrf,'email'=>'denied@shlz.ru','actorId'=>9101],$regular)['status'],'spoofed actor cannot provision');
    foreach(['GET','HEAD']as$method)assertSameValue(405,$w->request($method,'/pilot/admin/users/create',[],$admin)['status'],'create mutation method');
    assertSameValue(400,$w->request('POST','/pilot/admin/users/create',['_csrf'=>'wrong'],$admin)['status'],'create CSRF');
    assertSameValue($before,$f->snapshot(),'denials have no identity facts');
    foreach([['bad@example.org','Valid Name'],['good@shlz.ru',''],['ordinary.person@shlz.ru','Duplicate']]as[$email,$name]){
        assertSameValue(303,$w->post('/pilot/admin/users/create',['email'=>$email,'fullName'=>$name],$admin)['status'],'invalid create redirects');
        $r=$w->page($admin);assertSameValue(true,str_contains($r['body'],'role="alert"'),'invalid create actionable');assertSameValue(true,str_contains($r['body'],htmlspecialchars($email,ENT_QUOTES)),'email retained');assertSameValue($before,$f->snapshot(),'invalid create no writes');
    }
    assertSameValue(303,$w->post('/pilot/admin/users/create',['email'=>' New.Person@SHLZ.RU ','fullName'=>'New <Person>'],$admin)['status'],'create success');
    $rows=$w->db->query("SELECT * FROM {$w->p}fm2_pilot_users WHERE email='new.person@shlz.ru'")->fetch_all(MYSQLI_ASSOC);
    assertSameValue(1,count($rows),'normalized unique identity');$id=(int)$rows[0]['user_id'];
    assertSameValue('pending_password',$rows[0]['activation_state'],'awaiting first password');assertSameValue(null,$f->credentials($id)['password_hash'],'no initial hash');
    assertSameValue([],$f->rows('fm2_pilot_invitations'),'provision creates no invitation');assertSameValue([],$f->rows('fm2_outbox_intents'),'provision sends no mail');
    $roles=$w->db->query("SELECT role_id FROM {$w->p}fm2_pilot_user_roles WHERE user_id=$id")->fetch_all(MYSQLI_ASSOC);assertSameValue([9210],array_map('intval',array_column($roles,'role_id')),'default role only');
    $recipient=[];$login=$w->request('GET','/pilot/login',[],$recipient);$r=$w->request('POST','/pilot/login',['_csrf'=>$w->csrf($login['body']),'email'=>' New.Person@SHLZ.RU '],$recipient);
    assertSameValue(200,$r['status'],'email step');assertSameValue(true,str_contains($r['body'],'/pilot/first-password'),'initial password form');assertSameValue(false,str_contains($r['body'],'New &lt;Person&gt;'),'no personal name leak');
    $unknown=[];$form=$w->request('GET','/pilot/login',[],$unknown);$r2=$w->request('POST','/pilot/login',['_csrf'=>$w->csrf($form['body']),'email'=>'unknown@shlz.ru'],$unknown);assertSameValue(true,str_contains($r2['body'],'ИТСМ'),'unknown access guidance');
    $fields=['_csrf'=>$w->csrf($r['body']),'email'=>'new.person@shlz.ru','password'=>PasswordAccessFixture::PASSWORD,'passwordConfirmation'=>PasswordAccessFixture::PASSWORD];
    foreach([['short','short'],[str_repeat('z',201),str_repeat('z',201)],['new.person forbidden password','new.person forbidden password'],['Another valid password','Mismatch valid password']]as[$p,$c]){
        $before=$f->snapshot();$bad=$w->request('POST','/pilot/first-password',array_replace($fields,['password'=>$p,'passwordConfirmation'=>$c]),$recipient);assertSameValue(200,$bad['status'],'password correction');assertSameValue(true,str_contains($bad['body'],'role="alert"'),'password error');assertSameValue($before,$f->snapshot(),'invalid password no writes');
    }
    assertSameValue(200,$w->request('POST','/pilot/first-password',$fields,$recipient)['status'],'first password saved');
    $hash=$f->credentials($id)['password_hash'];assertSameValue(true,password_verify(PasswordAccessFixture::PASSWORD,$hash),'new hash verifies');assertSameValue('argon2id',password_get_info($hash)['algoName'],'hash algorithm preserved');
    assertSameValue('active',$w->user($id)['activation_state'],'active after password');assertSameValue(303,$w->page($recipient)['status'],'no auto login');
    $before=$f->snapshot();$w->request('POST','/pilot/first-password',array_replace($fields,['password'=>PasswordAccessFixture::NEW_PASSWORD,'passwordConfirmation'=>PasswordAccessFixture::NEW_PASSWORD]),$recipient);assertSameValue($before,$f->snapshot(),'cannot overwrite first password');
    assertSameValue(303,$w->login($recipient,'new.person@shlz.ru',PasswordAccessFixture::PASSWORD)['status'],'new user can log in');assertSameValue($hash,$f->credentials($id)['password_hash'],'login hash unchanged');
    $owner=$f->owner();$pending=$owner->provision(9101,'pending.person@shlz.ru','Pending');$pendingId=$pending['userId'];assertSameValue('changed',$owner->changeStatus(9101,$pendingId,'block')['status'],'block before activation');assertSameValue('invalid',$owner->setInitialPassword('pending.person@shlz.ru',PasswordAccessFixture::PASSWORD,PasswordAccessFixture::PASSWORD)['status'],'blocked initial rejected');assertSameValue('changed',$owner->changeStatus(9101,$pendingId,'unblock')['status'],'unblock pending');assertSameValue('pending_password',$w->user($pendingId)['activation_state'],'unblock does not activate');
    $parallel=$f->parallel([['method'=>'setInitialPassword','arguments'=>['pending.person@shlz.ru',PasswordAccessFixture::PASSWORD,PasswordAccessFixture::PASSWORD]],['method'=>'setInitialPassword','arguments'=>['pending.person@shlz.ru',PasswordAccessFixture::NEW_PASSWORD,PasswordAccessFixture::NEW_PASSWORD]]]);$statuses=array_column($parallel,'status');sort($statuses);assertSameValue(['activated','invalid'],$statuses,'one winner for initial password race');
    $dups=$f->parallel([['method'=>'provision','arguments'=>[9101,'race.person@shlz.ru','Race']],['method'=>'provision','arguments'=>[9101,'race.person@shlz.ru','Race']]]);$statuses=array_column($dups,'status');sort($statuses);assertSameValue(['created','invalid'],$statuses,'one winner for provision race');
    $before=$f->snapshot();foreach(['/pilot/admin/users/invite','/pilot/admin/users/'.$id.'/invitation']as$path)assertSameValue(410,$w->post($path,['email'=>'old@shlz.ru','fullName'=>'Old'],$admin)['status'],'retired invitation writer');
    $old=$w->request('GET','/pilot/activate?token='.str_repeat('a',43),[],$guest);assertSameValue(200,$old['status'],'old link transition');assertSameValue(false,str_contains($old['body'],'name="password"'),'old link cannot activate');assertSameValue($before,$f->snapshot(),'old routes no changes');
    $trigger=$w->p.'password_event_fault';$w->db->query("CREATE TRIGGER $trigger BEFORE INSERT ON {$w->p}fm2_password_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture secret fault'");$before=$f->snapshot();$failed=false;try{$owner->provision(9101,'rollback@shlz.ru','Rollback');}catch(Throwable){$failed=true;}assertSameValue(true,$failed,'audit fault surfaced');assertSameValue($before,$f->snapshot(),'provision transaction rollback');$w->db->query("DROP TRIGGER $trigger");
    $w->noLegacy();echo "PASS: EMAIL-FIRST-PASSWORD-RECOVERY-001 HTTP initial password and authorization\n";
} finally {$f->close();}
