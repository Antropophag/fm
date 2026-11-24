<?php
declare(strict_types=1);
namespace FMonitor2\IdentityAccess;

use yii\db\Expression;

final class MariaDbPasswordAccess
{
    private \Closure $clock;
    public function __construct(private readonly MariaDbUserAccessTransaction $context,private readonly ?PasswordRecoveryConfig $config,callable $clock){$this->clock=\Closure::fromCallable($clock);}
    public function loginStep(string $rawEmail):string
    {
        $email=mb_strtolower(trim($rawEmail));$rows=$this->context->db->createCommand('SELECT u.status,u.activation_state,c.password_hash FROM '.$this->context->table('fm2_pilot_users').' u JOIN '.$this->context->table('fm2_pilot_auth_credentials').' c ON c.user_id=u.user_id WHERE BINARY c.email_normalized=BINARY :email LIMIT 2',[':email'=>$email])->queryAll();
        if(count($rows)!==1||(int)$rows[0]['status']!==1)return'absent';
        if($rows[0]['activation_state']==='pending_password'&&$rows[0]['password_hash']===null)return'initial';
        return$rows[0]['activation_state']==='active'&&is_string($rows[0]['password_hash'])?'password':'absent';
    }
    public function provision(int $actor,string $email,string $name):array
    {
        return $this->context->run(function()use($actor,$email,$name):array{
            if(!$this->context->authorized($actor,'access.administer'))return['status'=>'access_denied'];
            $email=mb_strtolower(trim($email));$name=trim($name);
            if(filter_var($email,FILTER_VALIDATE_EMAIL)===false||preg_match('/^[^@]+@shlz\.ru$/D',$email)!==1||$name===''||mb_strlen($name)>300)return['status'=>'invalid'];
            $db=$this->context->db;
            if($db->createCommand('SELECT user_id FROM '.$this->context->table('fm2_pilot_users').' WHERE BINARY email=BINARY :email FOR UPDATE',[':email'=>$email])->queryOne()!==false)return['status'=>'invalid'];
            $now=$this->context->now();
            $db->createCommand()->insert($this->context->raw('fm2_pilot_users'),['full_name'=>$name,'email'=>$email,'phone'=>'','status'=>1,'activation_state'=>'pending_password','session_version'=>1,'source_updated_at'=>$now])->execute();$id=(int)$db->getLastInsertID();
            $db->createCommand()->insert($this->context->raw('fm2_pilot_auth_credentials'),['user_id'=>$id,'email_normalized'=>$email,'password_hash'=>null,'password_set_at'=>null,'updated_at'=>$now])->execute();
            $role=$db->createCommand('SELECT role_id FROM '.$this->context->table('fm2_pilot_roles')." WHERE BINARY code=BINARY 'user' AND status=1 LIMIT 1")->queryScalar();if($role===false)throw new \RuntimeException('Default role unavailable.');
            $db->createCommand()->insert($this->context->raw('fm2_pilot_user_roles'),['user_id'=>$id,'role_id'=>(int)$role,'origin'=>'administrator','assigned_at'=>$now,'assigned_by_user_id'=>$actor])->execute();
            $this->event($id,'user_provisioned',$actor,$this->mysqlNow());return['status'=>'created','userId'=>$id];
        });
    }
    public function setInitial(string $email,string $password,string $confirmation):array
    {
        $email=mb_strtolower(trim($email));
        return $this->context->run(function()use($email,$password,$confirmation):array{
            $db=$this->context->db;$row=$db->createCommand('SELECT u.user_id,u.status,u.activation_state,u.email,c.password_hash FROM '.$this->context->table('fm2_pilot_users').' u JOIN '.$this->context->table('fm2_pilot_auth_credentials').' c ON c.user_id=u.user_id WHERE BINARY c.email_normalized=BINARY :email FOR UPDATE',[':email'=>$email])->queryOne();
            if($row===false||(int)$row['status']!==1||$row['activation_state']!=='pending_password'||$row['password_hash']!==null)return['status'=>'invalid'];
            $error=PasswordPolicy::error($password,$confirmation,(string)$row['email']);if($error!==null)return['status'=>'invalid_password','reason'=>$error];
            $hash=password_hash($password,PASSWORD_ARGON2ID);if(!is_string($hash))throw new \RuntimeException('Password hashing unavailable.');$now=$this->context->now();$id=(int)$row['user_id'];
            if($db->createCommand()->update($this->context->raw('fm2_pilot_auth_credentials'),['password_hash'=>$hash,'password_set_at'=>$now,'updated_at'=>$now],'user_id=:id AND password_hash IS NULL',[':id'=>$id])->execute()!==1)return['status'=>'invalid'];
            $db->createCommand()->update($this->context->raw('fm2_pilot_users'),['activation_state'=>'active','source_updated_at'=>$now],['user_id'=>$id])->execute();$this->event($id,'initial_password_set',null,$this->mysqlNow());return['status'=>'activated'];
        });
    }
    public function request(string $rawEmail,string $requestId,string $client):array
    {
        $config=$this->recoveryConfig();
        if(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di',$requestId)!==1)return['status'=>'accepted'];
        $trimmed=trim($rawEmail);$encoding=mb_check_encoding($trimmed,'UTF-8');$normalized=$encoding?mb_strtolower($trimmed):'';$emailFits=$encoding&&strlen($normalized)<=254;$email=$emailFits?$normalized:'invalid:'.hash('sha256',$rawEmail);$clientAddress=strlen($client)<=45?$client:'invalid:'.substr(hash('sha256',$client),0,37);$eligibleEmail=$emailFits&&filter_var($normalized,FILTER_VALIDATE_EMAIL)!==false&&preg_match('/^[^@]+@shlz\.ru$/D',$normalized)===1;$instant=$this->instant();
        return $this->context->run(function()use($email,$normalized,$eligibleEmail,$requestId,$clientAddress,$config,$instant):array{
            $db=$this->context->db;$existing=$db->createCommand('SELECT email_normalized FROM '.$this->context->table('fm2_password_recovery_attempts').' WHERE request_id=:id FOR UPDATE',[':id'=>$requestId])->queryOne();if($existing!==false)return['status'=>'accepted'];
            $now=$this->mysqlTime($instant);$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),['encrypted_token'=>null,'delivery_state'=>'suppressed'],'expires_at_utc<=:now AND encrypted_token IS NOT NULL',[':now'=>$now])->execute();$cut=$instant->modify('-15 minutes')->format('Y-m-d H:i:s.u');
            $ip=(int)$db->createCommand('SELECT COUNT(*) FROM '.$this->context->table('fm2_password_recovery_attempts').' WHERE client_address=:client AND attempted_at_utc>=:cut',[':client'=>$clientAddress,':cut'=>$cut])->queryScalar();
            $recent=$db->createCommand('SELECT attempted_at_utc FROM '.$this->context->table('fm2_password_recovery_attempts').' WHERE BINARY email_normalized=BINARY :email AND accepted=1 AND attempted_at_utc>=:cut ORDER BY attempted_at_utc DESC LIMIT 3 FOR UPDATE',[':email'=>$email,':cut'=>$cut])->queryAll();
            $cooldownCut=$instant->modify('-60 seconds')->format('Y-m-d H:i:s.u');$cooldown=(int)$db->createCommand('SELECT COUNT(*) FROM '.$this->context->table('fm2_password_recovery_attempts').' WHERE BINARY email_normalized=BINARY :email AND accepted=1 AND attempted_at_utc>:cut',[':email'=>$email,':cut'=>$cooldownCut])->queryScalar();
            $eligible=$ip<30&&count($recent)<3&&$cooldown===0;
            $user=$eligibleEmail?$db->createCommand('SELECT u.user_id,u.email,u.status,u.activation_state,u.session_version,c.password_hash FROM '.$this->context->table('fm2_pilot_users').' u JOIN '.$this->context->table('fm2_pilot_auth_credentials').' c ON c.user_id=u.user_id WHERE BINARY c.email_normalized=BINARY :email FOR UPDATE',[':email'=>$normalized])->queryOne():false;
            $accepted=$eligible&&$eligibleEmail&&$user!==false&&(int)$user['status']===1&&$user['activation_state']==='active'&&is_string($user['password_hash']);
            $db->createCommand()->insert($this->context->raw('fm2_password_recovery_attempts'),['request_id'=>$requestId,'email_normalized'=>$email,'client_address'=>$clientAddress,'attempted_at_utc'=>$now,'accepted'=>$accepted?1:0])->execute();
            if(!$accepted)return['status'=>'accepted'];
            $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$expires=$instant->modify('+1800 seconds')->format('Y-m-d H:i:s.u');$id=(int)$user['user_id'];$encrypted=$this->encrypt($token,$this->associatedData($requestId,$id,$normalized,(int)$user['session_version'],$config->keyId));
            $db->createCommand()->insert($this->context->raw('fm2_password_recovery_requests'),['request_id'=>$requestId,'user_id'=>$id,'email_normalized'=>$normalized,'session_version'=>(int)$user['session_version'],'token_hash'=>hash('sha256',$token,true),'encrypted_token'=>$encrypted,'key_id'=>$config->keyId,'created_at_utc'=>$now,'expires_at_utc'=>$expires,'used_at_utc'=>null,'revoked_at_utc'=>null,'delivery_state'=>'pending'])->execute();
            $this->event($id,'password_reset_requested',null,$now);
            $data=json_encode(['requestId'=>$requestId],JSON_THROW_ON_ERROR);$event='password-recovery/v1/'.$requestId;$fingerprint=hash('sha256',json_encode([$event,'email','password-recovery',1,$data],JSON_THROW_ON_ERROR));
            $db->createCommand('INSERT INTO '.$this->context->table('fm2_outbox_intents').'(domain_event_id,channel,template_reference,payload_version,data_json,fingerprint,created_at_utc) VALUES(:event,\'email\',\'password-recovery\',1,:data,:fingerprint,:created)',[':event'=>$event,':data'=>$data,':fingerprint'=>$fingerprint,':created'=>$now])->execute();
            return['status'=>'accepted'];
        });
    }
    public function lookup(string $token):?array{$row=$this->grant($token,false);return$row===null?null:['email'=>$row['email_normalized']];}
    public function reset(string $token,string $password,string $confirmation):array
    {
        if(!$this->validToken($token))return['status'=>'invalid'];
        return $this->context->run(function()use($token,$password,$confirmation):array{
            $row=$this->grant($token,true);if($row===null)return['status'=>'invalid'];$error=PasswordPolicy::error($password,$confirmation,$row['email_normalized']);if($error!==null)return['status'=>'invalid_password','reason'=>$error];
            $hash=password_hash($password,PASSWORD_ARGON2ID);if(!is_string($hash))throw new \RuntimeException('Password hashing unavailable.');$db=$this->context->db;$now=$this->mysqlNow();$source=$this->context->now();$id=(int)$row['user_id'];
            $db->createCommand()->update($this->context->raw('fm2_pilot_auth_credentials'),['password_hash'=>$hash,'password_set_at'=>$source,'updated_at'=>$source],['user_id'=>$id])->execute();$db->createCommand()->update($this->context->raw('fm2_pilot_users'),['session_version'=>new Expression('session_version+1'),'source_updated_at'=>$source],['user_id'=>$id])->execute();$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),['used_at_utc'=>$now,'encrypted_token'=>null],'user_id=:id AND used_at_utc IS NULL AND revoked_at_utc IS NULL',[':id'=>$id])->execute();$this->event($id,'password_reset',null,$now);return['status'=>'changed'];
        });
    }
    public function deliver(string $requestId,callable $sender):array
    {
        $config=$this->recoveryConfig();$instant=$this->instant();$db=$this->context->db;$tx=$db->beginTransaction(\yii\db\Transaction::SERIALIZABLE);
        try {
            $row=$db->createCommand('SELECT r.*,u.email,u.status,u.activation_state,u.session_version current_version,c.email_normalized current_email FROM '.$this->context->table('fm2_password_recovery_requests').' r JOIN '.$this->context->table('fm2_pilot_users').' u ON u.user_id=r.user_id JOIN '.$this->context->table('fm2_pilot_auth_credentials').' c ON c.user_id=u.user_id WHERE r.request_id=:id FOR UPDATE',[':id'=>$requestId])->queryOne();
            if($row===false){$tx->commit();return['status'=>'permanent'];}
            if($row['delivery_state']==='delivered'){$tx->commit();return['status'=>'delivered'];}
            if($row['delivery_state']==='unknown'){$tx->commit();return['status'=>'unknown'];}
            if(in_array($row['delivery_state'],['permanent','suppressed'],true)){$tx->commit();return['status'=>'permanent'];}
            $valid=$this->deliveryEligible($row,$config,$instant);
            if(!$valid){$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),['delivery_state'=>'suppressed','encrypted_token'=>null],['request_id'=>$requestId])->execute();$tx->commit();return['status'=>'permanent'];}
            $ad=$this->associatedData((string)$row['request_id'],(int)$row['user_id'],(string)$row['email_normalized'],(int)$row['session_version'],(string)$row['key_id']);$token=$this->decrypt((string)$row['encrypted_token'],$ad);
            if(!hash_equals((string)$row['token_hash'],hash('sha256',$token,true)))throw new \RuntimeException('Recovery envelope invalid.');
            $to=(string)$row['email_normalized'];$envelope=(string)$row['encrypted_token'];$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),['delivery_state'=>'unknown','encrypted_token'=>null],['request_id'=>$requestId])->execute();$tx->commit();
        }catch(\Throwable $e){if($tx->isActive)$tx->rollBack();throw$e;}
        $url=$config->publicBaseUrl.'/pilot/reset-password?token='.$token;$subject='Восстановление пароля FMonitor';$text="Для смены пароля откройте ссылку (она действует 30 минут):\n{$url}\n\nЕсли вы не запрашивали восстановление, проигнорируйте письмо.";$html='<p>Для смены пароля откройте ссылку (она действует 30 минут):</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">Сменить пароль</a></p><p>Если вы не запрашивали восстановление, проигнорируйте письмо.</p>';
        try{$out=$sender(['to'=>$to,'subject'=>$subject,'html'=>$html,'text'=>$text]);}catch(\Throwable){return['status'=>'unknown'];}
        $status=match($out['status']??null){'delivered'=>'delivered','transient'=>'transient','unknown'=>'unknown',default=>'permanent'};
        if($status==='transient')return$this->settleTransient($requestId,$config,$envelope);
        $values=['delivery_state'=>$status,'encrypted_token'=>null];
        try{$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),$values,['request_id'=>$requestId])->execute();}catch(\Throwable){return['status'=>'unknown'];}
        $safe=['status'=>$status];if(isset($out['providerReference'])&&is_string($out['providerReference']))$safe['providerReference']=$out['providerReference'];return$safe;
    }
    private function deliveryEligible(array$row,PasswordRecoveryConfig$config,\DateTimeImmutable$instant):bool{return(int)$row['status']===1&&$row['activation_state']==='active'&&(int)$row['session_version']===(int)$row['current_version']&&hash_equals($row['email_normalized'],$row['email'])&&hash_equals($row['email_normalized'],$row['current_email'])&&hash_equals($config->keyId,(string)$row['key_id'])&&$instant<(new \DateTimeImmutable($row['expires_at_utc'],new \DateTimeZone('UTC')))&&$row['used_at_utc']===null&&$row['revoked_at_utc']===null;}
    private function settleTransient(string$requestId,PasswordRecoveryConfig$config,string$envelope):array{$instant=$this->instant();$db=$this->context->db;$tx=$db->beginTransaction(\yii\db\Transaction::SERIALIZABLE);try{$row=$db->createCommand('SELECT r.*,u.email,u.status,u.activation_state,u.session_version current_version,c.email_normalized current_email FROM '.$this->context->table('fm2_password_recovery_requests').' r JOIN '.$this->context->table('fm2_pilot_users').' u ON u.user_id=r.user_id JOIN '.$this->context->table('fm2_pilot_auth_credentials').' c ON c.user_id=u.user_id WHERE r.request_id=:id FOR UPDATE',[':id'=>$requestId])->queryOne();if($row===false||$row['delivery_state']!=='unknown'||!$this->deliveryEligible($row,$config,$instant)){$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),['delivery_state'=>'suppressed','encrypted_token'=>null],['request_id'=>$requestId])->execute();$tx->commit();return['status'=>'permanent'];}$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),['delivery_state'=>'pending','encrypted_token'=>$envelope],['request_id'=>$requestId])->execute();$tx->commit();return['status'=>'transient'];}catch(\Throwable$e){if($tx->isActive)$tx->rollBack();return['status'=>'unknown'];}}
    private function grant(string $token,bool $lock):?array{if(!$this->validToken($token))return null;$sql='SELECT r.*,u.email,u.status,u.activation_state,u.session_version current_version,c.email_normalized current_email FROM '.$this->context->table('fm2_password_recovery_requests').' r JOIN '.$this->context->table('fm2_pilot_users').' u ON u.user_id=r.user_id JOIN '.$this->context->table('fm2_pilot_auth_credentials').' c ON c.user_id=u.user_id WHERE r.token_hash=:hash'.($lock?' FOR UPDATE':'');$r=$this->context->db->createCommand($sql,[':hash'=>hash('sha256',$token,true)])->queryOne();if($r===false||(int)$r['status']!==1||$r['activation_state']!=='active'||$r['used_at_utc']!==null||$r['revoked_at_utc']!==null||(int)$r['session_version']!==(int)$r['current_version']||!hash_equals($r['email_normalized'],$r['email'])||!hash_equals($r['email_normalized'],$r['current_email'])||$this->instant()>=(new \DateTimeImmutable($r['expires_at_utc'],new \DateTimeZone('UTC'))))return null;return$r;}
    private function event(int $user,string $action,?int $actor,string $now):void{$this->context->db->createCommand()->insert($this->context->raw('fm2_password_events'),['user_id'=>$user,'action'=>$action,'actor_user_id'=>$actor,'occurred_at_utc'=>$now])->execute();}
    private function instant():\DateTimeImmutable{$v=($this->clock)();if(!$v instanceof \DateTimeImmutable)throw new \RuntimeException('Password clock unavailable.');return$v->setTimezone(new \DateTimeZone('UTC'));}
    private function mysqlNow():string{return$this->mysqlTime($this->instant());}
    private function mysqlTime(\DateTimeImmutable $instant):string{return$instant->format('Y-m-d H:i:s.u');}
    private function validToken(string $v):bool{return preg_match('/^[A-Za-z0-9_-]{43}$/D',$v)===1;}
    private function associatedData(string$requestId,int$userId,string$email,int$generation,string$keyId):string{return json_encode(['password-recovery-envelope-v1',$requestId,$userId,$email,$generation,$keyId],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function encrypt(string $token,string$associatedData):string{$c=$this->recoveryConfig();$nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);$cipher=sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($token,$associatedData,$nonce,$c->key);return base64_encode($nonce.$cipher);}
    private function decrypt(string $value,string$associatedData):string{$c=$this->recoveryConfig();$raw=base64_decode($value,true);$nonceBytes=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;if(!is_string($raw)||strlen($raw)<$nonceBytes+SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES+1)throw new \RuntimeException('Recovery envelope invalid.');$token=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw,$nonceBytes),$associatedData,substr($raw,0,$nonceBytes),$c->key);if(!is_string($token)||!$this->validToken($token))throw new \RuntimeException('Recovery envelope invalid.');return$token;}
    private function recoveryConfig():PasswordRecoveryConfig{if($this->config===null)throw new \RuntimeException('Password recovery configuration unavailable.');return$this->config;}
}
