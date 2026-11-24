<?php
declare(strict_types=1);
require_once __DIR__.'/UserAccessFixture.php';

/** Test setup, deterministic clock and independent persisted-fact observation. */
final class PasswordAccessFixture
{
    public UserAccessFixture $web;
    public DateTimeImmutable $now;
    public const PASSWORD = 'Another password 2026!';
    public const NEW_PASSWORD = 'Replacement password 2026!';
    public function __construct()
    {
        $this->web = new UserAccessFixture(dirname(__DIR__,2));
        $this->now = new DateTimeImmutable('2026-11-24T12:00:00Z');
    }
    public function config(): array { return ['key'=>str_repeat('K',32),'keyId'=>'fixture-v1','publicBaseUrl'=>'https://portal.example.test']; }
    public function owner(): object
    {
        $e=$this->web->environment();
        $db=new yii\db\Connection(['dsn'=>'mysql:host='.$e['FMONITOR_DB_HOST'].';port='.$e['FMONITOR_DB_PORT'].';dbname='.$e['FMONITOR_DB_NAME'],'username'=>$e['FMONITOR_DB_USER'],'password'=>$e['FMONITOR_DB_PASSWORD'],'charset'=>'utf8mb4']);
        return new FMonitor2\IdentityAccess\YiiUserAccess(['db'=>$db,'tablePrefix'=>$this->web->p,'passwordClock'=>fn()=>$this->now,'passwordRecoveryConfig'=>$this->config()]);
    }
    public function start(?string $fault=null): void
    {
        $this->web->start($fault,extraEnvironment:[
            'FMONITOR_PASSWORD_RECOVERY_KEY'=>base64_encode(str_repeat('K',32)),
            'FMONITOR_PASSWORD_RECOVERY_KEY_ID'=>'fixture-v1',
            'FMONITOR_PUBLIC_BASE_URL'=>'https://portal.example.test',
            'FMONITOR_RUNTIME_ENV'=>'test','FMONITOR_SMTP_HOST'=>'127.0.0.1',
            'FMONITOR_SMTP_PORT'=>'2525','FMONITOR_SMTP_ENCRYPTION'=>'tls',
            'FMONITOR_SMTP_USERNAME'=>'fixture@shlz.ru','FMONITOR_SMTP_PASSWORD'=>'synthetic-mail-password',
            'FMONITOR_SMTP_FROM_ADDRESS'=>'fixture@shlz.ru','FMONITOR_SMTP_FROM_NAME'=>'FMonitor',
            'FMONITOR_SMTP_TIMEOUT_SECONDS'=>'1','FMONITOR_SMTP_VERIFY_PEER'=>'true',
        ]);
    }
    public function rows(string $table): array {return $this->web->rows($table);}
    public function credentials(int $id): array
    {
        return $this->web->db->query("SELECT * FROM {$this->web->p}fm2_pilot_auth_credentials WHERE user_id=$id")->fetch_assoc()??[];
    }
    public function snapshot(): array
    {
        $facts=$this->web->facts();
        foreach(['fm2_password_events','fm2_password_recovery_requests','fm2_outbox_intents'] as $table)$facts[$table]=$this->rows($table);
        return $facts;
    }
    public function uuid(): string
    {
        $h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12);
    }
    public function token(object $owner,string $requestId,array &$mail,string $outcome='delivered'): string
    {
        $result=$owner->deliverPasswordReset($requestId,function(array $wire)use(&$mail,$owner,$outcome):array{
            assertSameValue(false,$owner->db->getTransaction()?->getIsActive()??false,'transport outside transaction');
            $mail=$wire;return ['status'=>$outcome];
        });
        assertSameValue($outcome,$result['status'],'safe delivery result');
        assertSameValue(1,preg_match('#https://portal\.example\.test/pilot/reset-password\?token=([A-Za-z0-9_-]{43})#',$mail['text']??'',$m),'mail contains trusted reset URL');
        return $m[1];
    }
    public function parallel(array $operations): array
    {
        $w=$this->web;$e=$w->environment();$dir=$w->artifacts.'/race-'.bin2hex(random_bytes(4));mkdir($dir,0700);
        $config=['connection'=>['dsn'=>'mysql:host='.$e['FMONITOR_DB_HOST'].';port='.$e['FMONITOR_DB_PORT'].';dbname='.$e['FMONITOR_DB_NAME'],'username'=>$e['FMONITOR_DB_USER'],'password'=>$e['FMONITOR_DB_PASSWORD'],'charset'=>'utf8mb4'],'prefix'=>$w->p,'directory'=>$dir,'operations'=>$operations,'clock'=>$this->now->format(DATE_ATOM),'recovery'=>$this->config()];
        file_put_contents($dir.'/config.json',json_encode($config,JSON_THROW_ON_ERROR));chmod($dir.'/config.json',0600);$processes=[];
        try{
            foreach($operations as$i=>$op){$p=proc_open([PHP_BINARY,dirname(__DIR__).'/Support/password_access_worker.php',$dir.'/config.json',(string)$i],[0=>['file','/dev/null','r'],1=>['file',$dir.'/worker-'.$i.'.log','a'],2=>['file',$dir.'/worker-'.$i.'.log','a']],$pipes,$w->root);if(!is_resource($p))throw new TestFailure('worker setup');$processes[]=$p;}
            $deadline=microtime(true)+10;do{$ready=true;foreach($operations as$i=>$op)$ready=$ready&&is_file($dir.'/ready-'.$i);if($ready)break;usleep(10000);}while(microtime(true)<$deadline);
            assertSameValue(true,$ready,'all independent DB workers reach barrier');file_put_contents($dir.'/go','go');$results=[];
            foreach($processes as$i=>$p){$exit=proc_close($p);$processes[$i]=null;assertSameValue(0,$exit,'worker exit '.$i.' '.file_get_contents($dir.'/worker-'.$i.'.log'));$results[]=json_decode(file_get_contents($dir.'/result-'.$i.'.json'),true,flags:JSON_THROW_ON_ERROR);}
            return $results;
        }finally{foreach($processes as$p)if(is_resource($p)){proc_terminate($p,9);proc_close($p);}unlink($dir.'/config.json');}
    }
    public function close(): void {$this->web->close();}
}
