<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PasswordAccessFixture.php';
// Public HTTP and owner boundaries: new form versus replay, durable network uncertainty.
$f=new PasswordAccessFixture();$w=$f->web;$failures=[];
$check=static function(bool $ok,string $message)use(&$failures):void{if(!$ok)$failures[]=$message;};
try {
    $f->start();$cookies=[];$form=$w->request('GET','/pilot/forgot-password',[],$cookies);
    $check(($form['headers']['cache-control'][0]??null)==='no-store'&&($form['headers']['referrer-policy'][0]??null)==='no-referrer','forgot form has recovery privacy headers');
    preg_match('/name="requestId" value="([^"]+)"/',$form['body'],$m);$id=$m[1]??'';
    assertSameValue(true,$id!=='','fixture reaches public recovery form');
    $post=['_csrf'=>$w->csrf($form['body']),'requestId'=>$id,'email'=>'ordinary.person@shlz.ru'];
    $posted=$w->request('POST','/pilot/forgot-password',$post,$cookies);assertSameValue(200,$posted['status'],'first request accepted');
    $check(($posted['headers']['cache-control'][0]??null)==='no-store'&&($posted['headers']['referrer-policy'][0]??null)==='no-referrer','forgot confirmation has recovery privacy headers');
    $facts=$f->snapshot();$replay=$w->request('POST','/pilot/forgot-password',$post,$cookies);
    $check($replay['status']===200&&$facts===$f->snapshot(),'same posted form remains neutral and idempotent');
    $form2=$w->request('GET','/pilot/forgot-password',[],$cookies);preg_match('/name="requestId" value="([^"]+)"/',$form2['body'],$m2);$next=$m2[1]??'';
    $check($next!==''&&$next!==$id,'new form in same session obtains a new command identity');
    $r=$w->request('POST','/pilot/forgot-password',['_csrf'=>$w->csrf($form2['body']),'requestId'=>$next,'email'=>'other.person@shlz.ru'],$cookies);
    $check($r['status']===200&&count($f->rows('fm2_password_recovery_requests'))===2,'same browser can submit a later eligible request');
    // Two independent DB connections overlap at the real sender boundary.
    $f->now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$owner=$f->owner();$other=$f->owner();$calls=0;$mail=[];
    $result=$owner->deliverPasswordReset($id,function(array $wire)use(&$calls,&$mail,$other,$id,$owner,$f,$check):array{
        assertSameValue(false,$owner->db->getTransaction()?->getIsActive()??false,'network remains outside transaction');
        $claims=array_values(array_filter($f->rows('fm2_password_recovery_requests'),static fn(array $row):bool=>$row['request_id']===$id));
        $check(($claims[0]['delivery_state']??null)==='unknown'&&($claims[0]['encrypted_token']??null)===null,'durable claim is already unknown without envelope before transport can crash');
        $calls++;$mail=$wire;$other->deliverPasswordReset($id,function()use(&$calls):array{$calls++;return['status'=>'delivered'];});
        return['status'=>'delivered'];
    });
    $check($result['status']==='delivered'&&$calls===1,'overlapping independent dispatch calls send once');
    $check(str_contains($mail['text']??'',"\nhttps://")&&!str_contains($mail['text']??'','\\n'),'plain text instruction contains real line breaks');
    $owner->deliverPasswordReset($id,function()use(&$calls):array{$calls++;return['status'=>'delivered'];});
    $check($calls===1,'completed dispatch replay does not send again');
    // A transport may accept mail before durable settlement fails. Replay must not resend.
    $f->now=$f->now->modify('+1 hour');$fault=$f->uuid();$owner->requestPasswordReset('super.person@shlz.ru',$fault,'192.0.2.50');
    $trigger=$w->p.'delivery_settlement_fault';
    $w->db->query("CREATE TRIGGER $trigger BEFORE UPDATE ON {$w->p}fm2_password_recovery_requests FOR EACH ROW BEGIN IF NEW.delivery_state='delivered' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic settlement fault'; END IF; END");
    $sent=0;try{$owner->deliverPasswordReset($fault,function()use(&$sent):array{$sent++;return['status'=>'delivered'];});}catch(Throwable){}
    $w->db->query("DROP TRIGGER $trigger");$state=$w->db->query("SELECT delivery_state,encrypted_token FROM {$w->p}fm2_password_recovery_requests WHERE request_id='$fault'")->fetch_assoc();
    $check($sent===1&&$state['delivery_state']==='unknown'&&$state['encrypted_token']===null,'accepted mail with failed settlement remains durable unknown without envelope');
    $owner->deliverPasswordReset($fault,function()use(&$sent):array{$sent++;return['status'=>'delivered'];});
    $check($sent===1,'settlement failure replay cannot repeat network send');
    // An exception after entering transport has the same uncertain external outcome.
    $f->now=$f->now->modify('+1 hour');$throwing=$f->uuid();$owner->requestPasswordReset('super.person@shlz.ru',$throwing,'192.0.2.51');$entered=0;
    try{$owner->deliverPasswordReset($throwing,function()use(&$entered):array{$entered++;throw new RuntimeException('synthetic lost transport result');});}catch(Throwable){}
    $owner->deliverPasswordReset($throwing,function()use(&$entered):array{$entered++;return['status'=>'delivered'];});
    $check($entered===1,'transport exception cannot be automatically resent');
    $created=$owner->provision(9101,'waiting.directory@shlz.ru','Waiting Directory');assertSameValue('created',$created['status'],'pending directory fixture');
    $admin=[];assertSameValue(303,$w->login($admin)['status'],'directory administrator fixture');
    $directory=$w->request('GET','/pilot/admin/users?q=waiting.directory',[],$admin);
    $check($directory['status']===200&&str_contains($directory['body'],'Ожидает первого входа')&&!str_contains(mb_strtolower(strip_tags($directory['body'])),'приглаш'),'pending user directory contains first-login status without invitation state');
    // Malformed public inputs use bounded quota facts and remain neutral, never SQL errors.
    $f->now=$f->now->modify('+1 hour');$before=count($f->rows('fm2_password_recovery_requests'));$emailAttempts=count($f->rows('fm2_password_recovery_attempts'));
    foreach([str_repeat('x',254),str_repeat('x',255),str_repeat('я',300)]as$badEmail){
        try{$out=$owner->requestPasswordReset($badEmail,$f->uuid(),'192.0.2.60');$check($out['status']==='accepted','oversized email neutral response');}
        catch(Throwable){$check(false,'oversized email must not escape as storage failure');}
    }
    $check(count($f->rows('fm2_password_recovery_requests'))===$before,'malformed email creates no grant');
    $check(count($f->rows('fm2_password_recovery_attempts'))===$emailAttempts+3,'every malformed email request creates a bounded quota attempt');
    $f->now=$f->now->modify('+1 hour');$attempts=count($f->rows('fm2_password_recovery_attempts'));
    foreach([str_repeat('x',45),str_repeat('x',46),str_repeat('x',300)]as$client){try{$out=$owner->requestPasswordReset('missing@shlz.ru',$f->uuid(),$client);$check($out['status']==='accepted','oversized client neutral response');}catch(Throwable){$check(false,'oversized client must not escape as storage failure');}}
    $check(count($f->rows('fm2_password_recovery_attempts'))===$attempts+3,'malformed clients still create bounded quota attempts');
    foreach($f->rows('fm2_password_recovery_attempts')as$attempt)$check(mb_strlen($attempt['email_normalized'])<=254&&mb_strlen($attempt['client_address'])<=45,'attempt representation fits canonical schema');
    foreach(['https://portal.example.test/path','https://portal.example.test?x=y','https://portal.example.test#fragment','https://user:password@portal.example.test']as$origin){$rejected=false;try{FMonitor2\IdentityAccess\PasswordRecoveryConfig::from($f->config()+[]);$config=$f->config();$config['publicBaseUrl']=$origin;FMonitor2\IdentityAccess\PasswordRecoveryConfig::from($config);}catch(RuntimeException){$rejected=true;}$check($rejected,'recovery config rejects non-origin URL '.$origin);}
    foreach(['https://portal.example.test','https://portal.example.test:8443','https://portal.example.test/']as$origin){$config=$f->config();$config['publicBaseUrl']=$origin;$valid=FMonitor2\IdentityAccess\PasswordRecoveryConfig::from($config);$check($valid->publicBaseUrl===rtrim($origin,'/'),'valid HTTPS origin and port preserved');}
    assertSameValue([], $failures,'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 delivery safety: '.implode('; ',$failures));
    echo "PASS: EMAIL-FIRST-PASSWORD-RECOVERY-001 new form, replay, concurrent dispatch and uncertain settlement\n";
}finally{$f->close();}
