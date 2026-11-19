<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final readonly class MariaDbObjectDetailsEditStore
{
    private function __construct(private \mysqli $db,private string $prefix,private MariaDbObjectDetailsMutation $mutation){}

    public static function create(\mysqli $db,string $prefix):self
    {
        return new self($db,$prefix,MariaDbObjectDetailsMutation::create($db,$prefix));
    }

    public function apply(ObjectDetailsEditCommand $command,array $patch,string $fingerprint,ObjectDetailsFieldRegistry $registry,callable $clock):array
    {
        try {
            $this->db->begin_transaction();
            $actor=$this->authorization($command->actorId,$command->objectId);
            if($actor===null){$this->db->rollback();return['status'=>'rejected','reasonCode'=>'authorization_denied'];}
            $statement=$this->db->prepare("SELECT id FROM `{$this->prefix}fm2_installation_cases` WHERE legacy_installation_object_id=? FOR UPDATE");
            $statement->execute([$command->objectId]);
            if(count($statement->get_result()->fetch_all(MYSQLI_ASSOC))!==1){$this->db->rollback();return['status'=>'rejected','reasonCode'=>'authorization_denied'];}
            $statement=$this->db->prepare("SELECT request_fingerprint,outcome_json FROM `{$this->prefix}fm2_object_detail_edit_requests` WHERE request_id=? FOR UPDATE");
            $statement->execute([$command->requestId]);$previous=$statement->get_result()->fetch_assoc();
            if($previous){$this->db->rollback();if(!hash_equals($previous['request_fingerprint'],$fingerprint))return['status'=>'conflict','reasonCode'=>'request_id_conflict'];$out=json_decode($previous['outcome_json'],true,flags:JSON_THROW_ON_ERROR);$out['status']='replayed';return$out;}
            try{$result=$this->mutation->applyWithinTransaction($command->objectId,$command->actorId,(string)$actor['full_name'],$command->expectedRevision,$patch,$command->requestId,['persistRequest'=>true,'requestFingerprint'=>$fingerprint,'eventType'=>'object_details_changed'],new \DateTimeImmutable($clock()));}
            catch(\RuntimeException $e){if($e->getCode()===MariaDbObjectDetailsMutation::STALE_REVISION){$this->db->rollback();$statement=$this->db->prepare("SELECT revision FROM `{$this->prefix}fm2_object_detail_edits` WHERE object_id=?");$statement->execute([$command->objectId]);return['status'=>'conflict','reasonCode'=>'object_details_changed','revision'=>(int)($statement->get_result()->fetch_column()?:0)];}throw$e;}
            if($result['changes']===[]){$this->db->rollback();return['status'=>'noop','revision'=>$result['revision']];}
            $this->db->commit();return['status'=>'applied','revision'=>$result['revision'],'eventId'=>$result['eventId']];
        } catch(\Throwable) {
            try{$this->db->rollback();}catch(\Throwable){}
            return['status'=>'failed','reasonCode'=>'persistence_unavailable'];
        }
    }

    private function authorization(int $actor,int $objectId):?array
    {
        $statement=$this->db->prepare("SELECT u.full_name,MAX(BINARY r.code IN('fkr_operator','manager') AND BINARY rp.permission='objects.details.edit') can_edit,MAX(BINARY rp.permission='objects.read') can_read_object FROM `{$this->prefix}fm2_pilot_users` u JOIN `{$this->prefix}fm2_pilot_user_roles` ur ON ur.user_id=u.user_id JOIN `{$this->prefix}fm2_pilot_roles` r ON r.role_id=ur.role_id AND r.status=1 JOIN `{$this->prefix}fm2_pilot_role_permissions` rp ON rp.role_id=r.role_id JOIN `{$this->prefix}fm2_installation_cases` scope_case ON scope_case.legacy_installation_object_id=? WHERE u.user_id=? AND u.status=1 AND BINARY u.activation_state='active' GROUP BY u.user_id,u.full_name,scope_case.id");
        $statement->execute([$objectId,$actor]);$row=$statement->get_result()->fetch_assoc();
        return$row&&(int)$row['can_edit']===1&&(int)$row['can_read_object']===1?$row:null;
    }
}
