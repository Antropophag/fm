<?php
declare(strict_types=1);
require_once __DIR__.'/InspectionFixture.php';

/** RELIABLE-FIELD-OFFLINE-001: setup only, writes under test use real HTTP. */
final class ReliableFieldOfflineFixture
{
    public InspectionFixture $inspection;
    public function __construct(string $root)
    {
        $this->inspection=new InspectionFixture($root);
        $h=$this->inspection->http;
        $h->db->query("DELETE FROM {$h->p}fm2_pilot_user_roles WHERE user_id=73 AND role_id=7");
        $this->inspection->open();
        $object=$h->db->query("SELECT * FROM {$h->p}fm_maintable WHERE id=4512")->fetch_assoc();
        $case=$h->rows('fm2_installation_cases')[0];
        $association=$h->db->query("SELECT * FROM {$h->p}fm2_checklist_template_associations WHERE subject_kind='operational_case' AND subject_id='6101'")->fetch_assoc();
        for($i=0;$i<54;$i++){
            $id=5000+$i;$cid=8000+$i;$actor=$i<52?73:94;
            $row=$object;$row['id']=$id;$row['regnumber']='FIELD-'.$id;$row['ordadr_address']='Полевой объект '.$id;$h->insert($h->p.'fm_maintable',$row);
            $row=$case;$row['id']=$cid;$row['legacy_installation_object_id']=$id;$h->insert($h->p.'fm2_installation_cases',$row);
            $linked=$association;unset($linked['id']);$linked['subject_id']=(string)$cid;$h->insert($h->p.'fm2_checklist_template_associations',$linked);
            $h->insert($h->p.'fm2_control_engineer_assignments',[
                'installation_case_id'=>$cid,'object_id'=>$id,'assignment_sequence'=>1,'engineer_user_id'=>$actor,
                'engineer_fio_snapshot'=>'Инженер '.$actor,'engineer_position_snapshot'=>'Инженер строительного контроля',
                'previous_assignment_id'=>null,'previous_engineer_user_id'=>null,'bootstrap_application_id'=>null,
                'assigned_by_user_id'=>18,'assigned_at_utc'=>'2026-09-20 06:00:00',
                'request_id'=>sprintf('71717171-0000-4171-8171-%012d',$id),'request_fingerprint'=>hash('sha256','field-'.$id),
            ]);
        }
        $h->db->query("UPDATE {$h->p}fm2_installation_cases SET process_state='completed' WHERE legacy_installation_object_id=5051");
        foreach(['pto_act','declaration']as$type)$h->insert($h->p.'fm2_pilot_completion_facts',['installation_case_id'=>8051,'fact_type'=>$type,'fact_date'=>'2026-09-20','details'=>$type==='declaration'?'Fixture completion':'','recorded_at'=>'2026-09-20T09:00:00+03:00','recorded_by_user_id'=>18]);
    }
    public function close():void{$this->inspection->close();}
}
