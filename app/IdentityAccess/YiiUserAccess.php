<?php
declare(strict_types=1);

namespace FMonitor2\IdentityAccess;

use yii\base\Component;
use yii\db\Connection;

final class YiiUserAccess extends Component
{
    public Connection|string $db = 'db';
    public string $tablePrefix = '';
    public mixed $passwordClock = null;
    public ?array $passwordRecoveryConfig = null;

    private MariaDbUserAccessDirectory $directory;
    private MariaDbUserInvitations $invitations;
    private MariaDbUserActivation $activation;
    private MariaDbUserRoleChanges $roles;
    private MariaDbUserStatusChanges $statuses;
    private MariaDbUserAccessTransaction $context;
    private ?MariaDbPasswordAccess $passwords = null;

    public function init(): void
    {
        parent::init();
        if (is_string($this->db)) $this->db = \Yii::$app->get($this->db);
        if (!$this->db instanceof Connection || strlen($this->tablePrefix) > 32 || preg_match('/^[A-Za-z0-9_]*$/D', $this->tablePrefix) !== 1) {
            throw new \RuntimeException('User access configuration unavailable.');
        }
        $context = $this->context = new MariaDbUserAccessTransaction($this->db, $this->tablePrefix);
        $this->directory = new MariaDbUserAccessDirectory($context);
        $this->invitations = new MariaDbUserInvitations($context);
        $this->activation = new MariaDbUserActivation($context);
        $this->roles = new MariaDbUserRoleChanges($context);
        $this->statuses = new MariaDbUserStatusChanges($context);
    }

    public function directory(int $actorId): array { return $this->directory->read($actorId); }
    public function loginStep(string $email):string{return$this->passwords()->loginStep($email);}
    public function provision(int $actorId,string $email,string $fullName):array{return $this->passwords()->provision($actorId,$email,$fullName);}
    public function setInitialPassword(string $email,string $password,string $confirmation):array{return $this->passwords()->setInitial($email,$password,$confirmation);}
    public function requestPasswordReset(string $email,string $requestId,string $clientAddress):array{return $this->passwords()->request($email,$requestId,$clientAddress);}
    public function passwordReset(string $token):?array{return $this->passwords()->lookup($token);}
    public function resetPassword(string $token,string $password,string $confirmation):array{return $this->passwords()->reset($token,$password,$confirmation);}
    public function deliverPasswordReset(string $requestId,callable $sender):array{return $this->passwords()->deliver($requestId,$sender);}
    public function invite(int $actorId, string $email, string $fullName): array { return ['status'=>'invalid']; }
    public function reissue(int $actorId, int $userId): array { return ['status'=>'invalid']; }
    public function invitation(string $token): ?array { return null; }
    public function activate(string $token, string $password, string $confirmation): array { return ['status'=>'invalid']; }
    public function changeRole(int $actorId, int $userId, int $roleId, string $action): array { return $this->roles->change($actorId, $userId, $roleId, $action); }
    public function changeStatus(int $actorId, int $userId, string $action): array { return $this->statuses->change($actorId, $userId, $action); }
    private function passwords():MariaDbPasswordAccess
    {
        if($this->passwords!==null)return$this->passwords;
        $config=$this->passwordRecoveryConfig;
        $injected=$config!==null;
        if($config===null){$raw=getenv('FMONITOR_PASSWORD_RECOVERY_KEY');$key=is_string($raw)?base64_decode($raw,true):false;$config=['key'=>is_string($key)?$key:'','keyId'=>(string)getenv('FMONITOR_PASSWORD_RECOVERY_KEY_ID'),'publicBaseUrl'=>(string)getenv('FMONITOR_PUBLIC_BASE_URL')];}
        $clock=is_callable($this->passwordClock)?$this->passwordClock:$this->context->utcNow(...);
        try{$recovery=PasswordRecoveryConfig::from($config);}catch(\RuntimeException){$recovery=null;}
        if(!$injected&&$recovery!==null)try{\FMonitor2\Jobs\SmtpConfiguration::fromEnvironment(getenv());}catch(\RuntimeException){$recovery=null;}
        return$this->passwords=new MariaDbPasswordAccess($this->context,$recovery,$clock);
    }
}
