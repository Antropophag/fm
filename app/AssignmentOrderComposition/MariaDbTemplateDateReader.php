<?php
declare(strict_types=1);
namespace FMonitor2\AssignmentOrderComposition;

/** Caller owns authorization; this projection owns only its read snapshot. */
final readonly class MariaDbTemplateDateReader implements AssignmentOrderTemplateDateReader
{
    public function __construct(private MariaDbSelectionSql $sql) {}
    public function find(int $caseId,int $orderId):array
    {
        if(min($caseId,$orderId)<1)return ['status'=>'unavailable','date'=>null];
        try{return $this->sql->snapshot(function()use($caseId,$orderId){
            $source=(new MariaDbTemplateSource($this->sql))->load($caseId,$orderId);if($source===null)return ['status'=>'not_found','date'=>null];
            $rows=$this->sql->rows('SELECT * FROM '.$this->sql->table('fm2_process_events').' WHERE installation_case_id=? AND BINARY event_type=? ORDER BY id DESC',[$caseId,'assignment_order_template_generated']);
            $date=null;$appendixStartDate=null;$artifactAvailable=false;$h=$source['header'];
            foreach($rows as $row){
                $p=json_decode($row['payload_json'],true,512,JSON_THROW_ON_ERROR);
                if(!is_array($p)||!is_int($p['assignmentOrderId']??null)||$p['assignmentOrderId']<1)throw new \RuntimeException();
                if($p['assignmentOrderId']!==$orderId)continue;
                MariaDbSelectionSql::number($row['id']);MariaDbSelectionSql::number($row['actor_user_id']);
                $keys=array_keys($p);$legacy=['assignmentOrderId','assignmentOrderVersion','compositionIdentity','compositionSha256','templateDate'];$withArtifact=['assignmentOrderId','assignmentOrderVersion','compositionIdentity','compositionSha256','templateDate','artifactBase64','artifactSha256'];$withAppendix=['assignmentOrderId','assignmentOrderVersion','compositionIdentity','compositionSha256','templateDate','appendixStartDate','artifactBase64','artifactSha256'];
                if(!in_array($keys,[$legacy,$withArtifact,$withAppendix],true)||SelectionScalar::json($p)!==$row['payload_json']
                    ||$p['assignmentOrderVersion']!==$source['version']||$p['compositionIdentity']!==$h['composition_identity']||$p['compositionSha256']!==$h['composition_sha256']
                    ||!SelectionScalar::utc($row['occurred_at'])||!is_string($p['templateDate'])||$p['templateDate']!==SelectionScalar::selectionDate(new SelectionInstant($row['occurred_at'])))throw new \RuntimeException();
                if($date===null){$date=$p['templateDate'];$appendixStartDate=isset($p['appendixStartDate'])&&is_string($p['appendixStartDate'])&&SelectionScalar::date($p['appendixStartDate'])?$p['appendixStartDate']:null;$bytes=base64_decode((string)($p['artifactBase64']??''),true);$artifactAvailable=is_string($bytes)&&isset($p['artifactSha256'])&&hash_equals((string)$p['artifactSha256'],hash('sha256',$bytes));}
            }
            return ['status'=>$date===null?'not_found':'found','date'=>$date,'appendixStartDate'=>$appendixStartDate,'artifactAvailable'=>$artifactAvailable];
        });}catch(\Throwable){return ['status'=>'unavailable','date'=>null];}
    }
}
