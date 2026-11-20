<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final readonly class MariaDbObjectDetailsMutation
{
    public const STALE_REVISION = 40901;

    private function __construct(
        private \mysqli $db,
        private string $prefix,
        private MariaDbEffectiveObjectDetails $effective,
        private ObjectDetailsFieldRegistry $registry,
    ) {}

    public static function create(\mysqli $db,string $prefix):self
    {
        return new self($db,$prefix,MariaDbEffectiveObjectDetails::create($db,$prefix,$prefix),new ObjectDetailsFieldRegistry());
    }

    public function applyWithinTransaction(
        int $objectId,
        int $actorId,
        string $actorDisplay,
        int $expectedRevision,
        array $normalizedValues,
        string $requestId,
        array $metadata,
        \DateTimeImmutable $occurredAt,
    ):array {
        $statement=$this->db->prepare("SELECT id FROM `{$this->prefix}fm2_installation_cases` WHERE legacy_installation_object_id=? FOR UPDATE");
        $statement->execute([$objectId]);
        $cases=$statement->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($cases)!==1)throw new \RuntimeException('OBJECT_DETAILS_CASE_UNAVAILABLE');
        $caseId=(int)$cases[0]['id'];

        $statement=$this->db->prepare("SELECT revision,values_json FROM `{$this->prefix}fm2_object_detail_edits` WHERE object_id=? FOR UPDATE");
        $statement->execute([$objectId]);
        $current=$statement->get_result()->fetch_assoc();
        $revision=(int)($current['revision']??0);
        if($revision!==$expectedRevision)throw new \RuntimeException('OBJECT_DETAILS_REVISION_STALE',self::STALE_REVISION);

        $values=$current?json_decode($current['values_json'],true,flags:JSON_THROW_ON_ERROR):[];
        $effective=$this->effective->read($objectId);
        $import=($metadata['source']??null)==='address_register_import';
        $definitions=$import?$this->registry->importDefinitions():$this->registry->definitions();
        $changes=[];
        foreach($normalizedValues as$field=>$value){
            if(!is_string($field)||!isset($definitions[$field]))throw new \InvalidArgumentException('Invalid object details field.');
            $old=($metadata['newObject']??false)?null:($effective[$field]['value']??null);
            if($this->same($old,$value))continue;
            $oldSnapshot=$import?$this->registry->snapshotImport($field,$old):$this->registry->snapshot($field,$old,false);
            if(!($metadata['newObject']??false)&&($effective[$field]['display']??null)!==null)$oldSnapshot['display']=$effective[$field]['display'];
            $change=[
                'field'=>$field,
                'label'=>$definitions[$field]['label'],
                'old'=>$oldSnapshot,
                'new'=>$import?$this->registry->snapshotImport($field,$value):$this->registry->snapshot($field,$value),
            ];
            foreach(['source','packageId','reason']as$key)if(array_key_exists($key,$metadata))$change[$key]=$metadata[$key];
            $changes[]=$change;
            $values[$field]=$value;
        }
        if($changes===[])return['revision'=>$revision,'eventId'=>null,'changes'=>[]];

        $next=$revision+1;
        $mysql=$occurredAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $json=$this->json($values);
        if($current){
            $statement=$this->db->prepare("UPDATE `{$this->prefix}fm2_object_detail_edits` SET revision=?,values_json=?,updated_at_utc=?,updated_by_user_id=? WHERE object_id=? AND revision=?");
            $statement->execute([$next,$json,$mysql,$actorId,$objectId,$revision]);
            if($statement->affected_rows!==1)throw new \RuntimeException('OBJECT_DETAILS_REVISION_STALE',self::STALE_REVISION);
        }else{
            $statement=$this->db->prepare("INSERT INTO `{$this->prefix}fm2_object_detail_edits`(object_id,revision,values_json,updated_at_utc,updated_by_user_id)VALUES(?,?,?,?,?)");
            $statement->execute([$objectId,$next,$json,$mysql,$actorId]);
        }
        $eventType=(string)($metadata['eventType']??'object_details_changed');
        $statement=$this->db->prepare("INSERT INTO `{$this->prefix}fm2_object_detail_edit_events`(installation_case_id,object_id,revision,event_type,changes_json,actor_user_id,actor_display_snapshot,occurred_at_utc,request_id)VALUES(?,?,?,?,?,?,?,?,?)");
        $statement->execute([$caseId,$objectId,$next,$eventType,$this->json($changes),$actorId,$actorDisplay,$mysql,$requestId]);
        $eventId=(int)$this->db->insert_id;
        $result=['revision'=>$next,'eventId'=>$eventId,'changes'=>$changes];
        if($metadata['persistRequest']??false){
            $outcome=$metadata['requestOutcome']??['status'=>'applied','revision'=>$next,'eventId'=>$eventId];
            $statement=$this->db->prepare("INSERT INTO `{$this->prefix}fm2_object_detail_edit_requests`(request_id,request_fingerprint,object_id,outcome_json,created_at_utc)VALUES(?,?,?,?,?)");
            $statement->execute([$requestId,(string)($metadata['requestFingerprint']??''),$objectId,$this->json($outcome),$mysql]);
        }
        return$result;
    }

    private function json(array $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function same(mixed $left,mixed $right):bool{return get_debug_type($left)===get_debug_type($right)?$left===$right:(is_scalar($left)&&is_scalar($right)&&(string)$left===(string)$right);}
}
