<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PasswordAccessFixture.php';
use FMonitor2\IdentityAccess\YiiUserAccess;
use FMonitor2\Jobs\{MariaDbJobQueue,JobsRuntimeConfiguration,JobHandlerRuntime};
$f=new PasswordAccessFixture();$w=$f->web;$failures=[];$saved=[];
$check=static function(bool$ok,string$label)use(&$failures):void{if(!$ok)$failures[]=$label;};
try {
    $owner=$f->owner();assertSameValue('created',$owner->provision(9101,'integrity.ready@shlz.ru','Integrity Ready')['status'],'new public owner fixture reachable');
    // Historical values remain representable; active facade must still retire all old writers.
    $w->db->query("ALTER TABLE {$w->p}fm2_pilot_users MODIFY activation_state ENUM('pending_invitation','invited','pending_password','active','blocked') NOT NULL");
    $before=$f->snapshot();$r=$owner->invite(9101,'legacy.create@shlz.ru','Legacy Create');$check(($r['status']??null)==='invalid'&&$before===$f->snapshot(),'active facade cannot invite');
    foreach(['reissue','invitation','activate']as$i=>$method){
        $user=9600+$i;$token=rtrim(strtr(base64_encode(str_repeat(chr(65+$i),32)),'+/','-_'),'=');$hash=hash('sha256',$token);
        $w->db->query("INSERT INTO {$w->p}fm2_pilot_users VALUES($user,'Historical','historical.$i@shlz.ru','',1,'invited',1,'2026-11-24')");$w->db->query("INSERT INTO {$w->p}fm2_pilot_auth_credentials VALUES($user,'historical.$i@shlz.ru',NULL,NULL,'2026-11-24')");$w->db->query("INSERT INTO {$w->p}fm2_pilot_invitations(user_id,token_hash,expires_at,created_at) VALUES($user,UNHEX('$hash'),DATE_ADD(NOW(6),INTERVAL 24 HOUR),NOW(6))");$before=$f->snapshot();
        $r=match($method){'reissue'=>$owner->reissue(9101,$user),'invitation'=>$owner->invitation($token),'activate'=>$owner->activate($token,PasswordAccessFixture::PASSWORD,PasswordAccessFixture::PASSWORD)};
        $check(($method==='invitation'?$r===null:($r['status']??null)==='invalid')&&$before===$f->snapshot(),'active facade retires '.$method);
    }
    // No injected clock: the authoritative default must follow this DB session's UTC clock.
    $production=new YiiUserAccess(['db'=>$f->owner()->db,'tablePrefix'=>$w->p,'passwordRecoveryConfig'=>$f->config()]);$fixed=new DateTimeImmutable('2030-01-02T03:04:05Z');$production->db->createCommand('SET timestamp='.$fixed->getTimestamp())->execute();
    try{$id=$f->uuid();$production->requestPasswordReset('ordinary.person@shlz.ru',$id,'192.0.2.101');$row=$w->db->query("SELECT created_at_utc,expires_at_utc FROM {$w->p}fm2_password_recovery_requests WHERE request_id='$id'")->fetch_assoc();$check(($row['created_at_utc']??null)==='2030-01-02 03:04:05.000000'&&($row['expires_at_utc']??null)==='2030-01-02 03:34:05.000000','production clock is one database UTC instant plus exact1800s');}finally{$production->db->createCommand('SET timestamp=0')->execute();}
    $f->now=new DateTimeImmutable('2031-01-02T03:04:05Z');
    foreach(['seal.one@shlz.ru','seal.two@shlz.ru']as$email){$owner->provision(9101,$email,'Sealed Recipient');$owner->setInitialPassword($email,PasswordAccessFixture::PASSWORD,PasswordAccessFixture::PASSWORD);}
    $a=$f->uuid();$b=$f->uuid();$owner->requestPasswordReset('seal.one@shlz.ru',$a,'192.0.2.102');$owner->requestPasswordReset('seal.two@shlz.ru',$b,'192.0.2.102');
    $cipher=$w->db->query("SELECT encrypted_token FROM {$w->p}fm2_password_recovery_requests WHERE request_id='$a'")->fetch_column();$statement=$w->db->prepare("UPDATE {$w->p}fm2_password_recovery_requests SET encrypted_token=? WHERE request_id=?");$statement->execute([$cipher,$b]);
    $called=0;try{$owner->deliverPasswordReset($b,function(array$wire)use(&$called):array{$called++;return['status'=>'delivered'];});}catch(Throwable){}$check($called===0,'swapped ciphertext cannot mail another identity recovery link');
    foreach(['key-id','token-digest','request-identity']as$cause){
        $f->now=$f->now->modify('+1 hour');$id=$f->uuid();$owner->requestPasswordReset('seal.one@shlz.ru',$id,'192.0.2.103');
        if($cause==='key-id')$w->db->query("UPDATE {$w->p}fm2_password_recovery_requests SET key_id='wrong-key-version' WHERE request_id='$id'");
        if($cause==='token-digest')$w->db->query("UPDATE {$w->p}fm2_password_recovery_requests SET token_hash=UNHEX(REPEAT('ef',32)) WHERE request_id='$id'");
        if($cause==='request-identity'){$other=$f->uuid();$w->db->query("UPDATE {$w->p}fm2_password_recovery_requests SET request_id='$other' WHERE request_id='$id'");$id=$other;}
        $called=0;try{$owner->deliverPasswordReset($id,function(array$wire)use(&$called):array{$called++;return['status'=>'delivered'];});}catch(Throwable){}$check($called===0,'envelope validates '.$cause.' before sender');
    }
    // Real production router must reject a future/unrecognized template version before delivery.
    $f->now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$owner->provision(9101,'version.user@shlz.ru','Version User');$owner->setInitialPassword('version.user@shlz.ru',PasswordAccessFixture::PASSWORD,PasswordAccessFixture::PASSWORD);$id=$f->uuid();$owner->requestPasswordReset('version.user@shlz.ru',$id,'192.0.2.104');
    $intent=(int)$w->db->query("SELECT intent_id FROM {$w->p}fm2_outbox_intents WHERE domain_event_id='password-recovery/v1/$id'")->fetch_column();$w->db->query("UPDATE {$w->p}fm2_outbox_intents SET payload_version=2 WHERE intent_id=$intent");
    $environment=$w->environment()+['FMONITOR_PASSWORD_RECOVERY_KEY'=>base64_encode(str_repeat('K',32)),'FMONITOR_PASSWORD_RECOVERY_KEY_ID'=>'fixture-v1','FMONITOR_PUBLIC_BASE_URL'=>'https://portal.example.test','FMONITOR_RUNTIME_ENV'=>'test','FMONITOR_SMTP_HOST'=>'127.0.0.1','FMONITOR_SMTP_PORT'=>'2525','FMONITOR_SMTP_ENCRYPTION'=>'tls','FMONITOR_SMTP_USERNAME'=>'fixture@shlz.ru','FMONITOR_SMTP_PASSWORD'=>'synthetic-smtp-secret','FMONITOR_SMTP_FROM_ADDRESS'=>'fixture@shlz.ru','FMONITOR_SMTP_FROM_NAME'=>'FMonitor','FMONITOR_SMTP_TIMEOUT_SECONDS'=>'1','FMONITOR_SMTP_VERIFY_PEER'=>'true'];
    foreach($environment as$key=>$value){$saved[$key]=getenv($key);putenv($key.'='.$value);}
    $queue=new MariaDbJobQueue($w->db,$w->p,null,null,['outbox.dispatch'=>[1]]);$queue->enqueue(['jobType'=>'outbox.dispatch','payloadVersion'=>1,'payload'=>['intentId'=>$intent],'availableAtUtc'=>$f->now->format('Y-m-d\TH:i:s.u\Z'),'idempotencyKey'=>$f->uuid(),'actor'=>['type'=>'system','id'=>'recovery-integrity']]);$job=$queue->claim(['workerId'=>'integrity-worker','batch'=>1])[0];$called=0;$r=JobHandlerRuntime::handle($job,JobsRuntimeConfiguration::fromEnvironment(),null,function(array$wire)use(&$called):array{$called++;return['status'=>'delivered'];});$check($called===0&&($r['status']??null)==='permanent','dispatch rejects unknown recovery template version');
    // Valid recovery key/origin alone is insufficient when SMTP is not configured.
    $w->start(extraEnvironment:['FMONITOR_PASSWORD_RECOVERY_KEY'=>base64_encode(str_repeat('K',32)),'FMONITOR_PASSWORD_RECOVERY_KEY_ID'=>'fixture-v1','FMONITOR_PUBLIC_BASE_URL'=>'https://portal.example.test','FMONITOR_RUNTIME_ENV'=>'test']);$anon=[];$form=$w->request('GET','/pilot/forgot-password',[],$anon);if($form['status']===200){preg_match('/name="requestId" value="([^"]+)"/',$form['body'],$m);$form=$w->request('POST','/pilot/forgot-password',['_csrf'=>$w->csrf($form['body']),'requestId'=>$m[1]??$f->uuid(),'email'=>$w->auth->email],$anon);}$check($form['status']===503,'missing SMTP returns recovery-only503 with valid key');$admin=[];$check($w->login($admin)['status']===303,'missing SMTP preserves normal login');
    assertSameValue([],$failures,'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 recovery integrity: '.implode('; ',$failures));
    echo "PASS: EMAIL-FIRST-PASSWORD-RECOVERY-001 retired facade, sealed identity, DB clock and delivery readiness\n";
}finally{foreach($saved as$key=>$value)putenv($value===false?$key:$key.'='.$value);$f->close();}
