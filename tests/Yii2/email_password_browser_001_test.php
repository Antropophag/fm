<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PasswordAccessFixture.php';
$f=new PasswordAccessFixture();$w=$f->web;$process=null;$configPath=$w->artifacts.'/password-browser.json';
try {
    $f->start();$mailPath=$w->artifacts.'/recovery-mail.json';
    file_put_contents($configPath,json_encode(['origin'=>'http://127.0.0.1:'.$w->server['port'],'adminEmail'=>$w->auth->email,'adminPassword'=>$w->auth->password,'password'=>PasswordAccessFixture::PASSWORD,'newPassword'=>PasswordAccessFixture::NEW_PASSWORD,'mailbox'=>$mailPath,'artifacts'=>$w->artifacts,'playwright'=>getenv('FMONITOR_TEST_PLAYWRIGHT_MODULE')?:dirname($w->root).'/shlz-ui/node_modules/playwright'],JSON_THROW_ON_ERROR));chmod($configPath,0600);
    $process=proc_open([getenv('FMONITOR_TEST_NODE_BINARY')?:'node',__DIR__.'/email_password_browser.mjs',$configPath],[0=>['file','/dev/null','r'],1=>['file',$w->artifacts.'/browser.log','a'],2=>['file',$w->artifacts.'/browser.log','a']],$pipes,$w->root);if(!is_resource($process))throw new TestFailure('browser setup');$deadline=microtime(true)+90;
    $sent=false;
    do {
        $state=proc_get_status($process);if(!$state['running'])break;
        if(!$sent){$table=$w->p.'fm2_password_recovery_requests';$exists=(int)$w->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'")->fetch_column();if($exists){$row=$w->db->query("SELECT request_id FROM $table WHERE email_normalized='browser.new@shlz.ru' ORDER BY created_at_utc DESC LIMIT 1")->fetch_assoc();if($row){$f->now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$mail=[];$f->token($f->owner(),$row['request_id'],$mail);file_put_contents($mailPath,json_encode($mail,JSON_THROW_ON_ERROR));chmod($mailPath,0600);$sent=true;}}}
        usleep(20000);
    }while(microtime(true)<$deadline);
    if($state['running'])throw new TestFailure('browser deadline '.$w->artifacts);$exit=$state['exitcode'];proc_close($process);$process=null;
    assertSameValue(0,$exit,'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 browser workflow '.file_get_contents($w->artifacts.'/browser.log'));
    $user=$w->db->query("SELECT user_id,activation_state FROM {$w->p}fm2_pilot_users WHERE email='browser.new@shlz.ru'")->fetch_assoc();assertSameValue('active',$user['activation_state'],'browser active identity');assertSameValue(true,password_verify(PasswordAccessFixture::NEW_PASSWORD,$f->credentials((int)$user['user_id'])['password_hash']),'browser reset committed');assertSameValue([],$f->rows('fm2_pilot_invitations'),'browser never invited');
    echo 'PASS: EMAIL-FIRST-PASSWORD-RECOVERY-001 browser; artifacts '.$w->artifacts."\n";
}finally{if(is_resource($process)){proc_terminate($process,9);proc_close($process);}if(is_file($configPath))unlink($configPath);$f->close();}
