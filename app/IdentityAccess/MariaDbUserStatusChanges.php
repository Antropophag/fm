<?php
declare(strict_types=1);
namespace FMonitor2\IdentityAccess;
use yii\db\Expression;

final class MariaDbUserStatusChanges
{
    public function __construct(private readonly MariaDbUserAccessTransaction $context) {}
    public function change(int $actorId, int $userId, string $action): array
    {
        return $this->context->run(function () use ($actorId, $userId, $action): array {
            if (!$this->context->authorized($actorId, 'access.administer')) return ['status'=>'access_denied'];
            if (!in_array($action, ['block','unblock'], true)) return ['status'=>'invalid'];
            if ($actorId === $userId) return ['status'=>'access_denied'];
            $db = $this->context->db;
            $user = $db->createCommand('SELECT u.status,u.activation_state,c.password_hash FROM '.$this->context->table('fm2_pilot_users').' u JOIN '.$this->context->table('fm2_pilot_auth_credentials').' c ON c.user_id=u.user_id WHERE u.user_id=:user FOR UPDATE', [':user'=>$userId])->queryOne();
            if ($user === false || ($action === 'block' && ((int)$user['status'] !== 1 || !in_array($user['activation_state'],['active','pending_password'],true))) || ($action === 'unblock' && ((int)$user['status'] !== 0 || $user['activation_state'] !== 'blocked'))) return ['status'=>'invalid'];
            if ($action === 'block' && $this->context->isSuper($userId) && $this->context->activeSupers() <= 1) return ['status'=>'invalid'];
            $now = $this->context->now();
            $next=$action==='block'?'blocked':($user['password_hash']===null?'pending_password':'active');
            $db->createCommand()->update($this->context->raw('fm2_pilot_users'), ['status'=>$action === 'block' ? 0 : 1, 'activation_state'=>$next, 'session_version'=>new Expression('session_version+1'), 'source_updated_at'=>$now], ['user_id'=>$userId])->execute();
            if($action==='block')$db->createCommand()->update($this->context->raw('fm2_password_recovery_requests'),['revoked_at_utc'=>new Expression('UTC_TIMESTAMP(6)')],'user_id=:user AND used_at_utc IS NULL AND revoked_at_utc IS NULL',[':user'=>$userId])->execute();
            $db->createCommand()->insert($this->context->raw('fm2_pilot_user_status_events'), ['user_id'=>$userId, 'action'=>$action === 'block' ? 'user_blocked' : 'user_unblocked', 'occurred_at'=>$now, 'actor_user_id'=>$actorId])->execute();
            return ['status'=>'changed'];
        });
    }
}
