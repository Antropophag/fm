<?php
declare(strict_types=1);
namespace FMonitor2\Tests\Support;

/** Explicit valid stops/contract inputs for pre-existing financial fixture oracles. */
final class OtizContractFixture
{
    public static function seed(object $http):void
    {
        $db=$http->db;$p=$http->p;
        $db->query("UPDATE {$p}fm_maintable m JOIN {$p}fm2_installation_cases c ON c.legacy_installation_object_id=m.id SET m.workdatestart=c.actual_start_date WHERE c.actual_start_date IS NOT NULL");
        foreach($db->query("SELECT object_id,payload_json FROM {$p}fm2_pilot_object_details")->fetch_all(MYSQLI_ASSOC)as$row){
            $payload=json_decode($row['payload_json'],true,flags:JSON_THROW_ON_ERROR);
            if(!isset($payload['fields']['stops'])&&isset($payload['fields']['floors']['raw'])){
                $value=$payload['fields']['floors']['raw'];$payload['fields']['stops']=['raw'=>$value,'display'=>(string)$value];
                $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $db->prepare("UPDATE {$p}fm2_pilot_object_details SET payload_json=?,content_sha256=? WHERE object_id=?")->execute([$json,hash('sha256',$json),$row['object_id']]);
            }
        }
    }
}
