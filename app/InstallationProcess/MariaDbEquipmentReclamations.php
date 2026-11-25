<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

class MariaDbEquipmentReclamations
{
    public function __construct(private \mysqli$db,private string$prefix,private \Closure$clock)
    {if(preg_match('/^[A-Za-z0-9_]{0,25}$/D',$prefix)!==1)throw new \InvalidArgumentException('INVALID_TABLE_PREFIX');}

    public function add(array$command):array
    {
        $c=$this->command($command);$this->authorize($c['actorId'],'deadline_certificate.write');$this->ready();$fingerprint=$this->fingerprint($c);$normalized=hash('sha256',mb_strtolower($c['number'],'UTF-8'));
        if((int)$this->db->query('SELECT @@in_transaction active')->fetch_assoc()['active']!==0)throw new \LogicException('Caller transaction not supported.');
        try{
            $this->db->begin_transaction();$case=$this->db->prepare('SELECT id FROM '.$this->table('fm2_installation_cases').' WHERE id=? FOR UPDATE');$case->execute([$c['installationCaseId']]);if(!$case->get_result()->fetch_column())throw new \DomainException('CASE_NOT_FOUND');
            $prior=$this->db->prepare('SELECT * FROM '.$this->table('fm2_equipment_reclamations').' WHERE actor_user_id=? AND request_id=? FOR UPDATE');$prior->execute([$c['actorId'],$c['requestId']]);$row=$prior->get_result()->fetch_assoc();if($row){if(!hash_equals((string)$row['request_fingerprint'],$fingerprint))throw new \DomainException('OPERATION_CONFLICT');$this->db->rollback();return['status'=>'replayed','reclamation'=>$this->map($row)];}
            $duplicate=$this->db->prepare('SELECT id FROM '.$this->table('fm2_equipment_reclamations').' WHERE installation_case_id=? AND normalized_number_sha256=? FOR UPDATE');$duplicate->execute([$c['installationCaseId'],$normalized]);if($duplicate->get_result()->fetch_column())throw new \DomainException('NUMBER_CONFLICT');
            $recordedAt=$this->instant(($this->clock)());$insert=$this->db->prepare('INSERT INTO '.$this->table('fm2_equipment_reclamations').' (installation_case_id,actor_user_id,number,normalized_number_sha256,delay_days,request_id,request_fingerprint,recorded_at) VALUES (?,?,?,?,?,?,?,?)');$insert->execute([$c['installationCaseId'],$c['actorId'],$c['number'],$normalized,$c['delayDays'],$c['requestId'],$fingerprint,$recordedAt]);$id=(int)$this->db->insert_id;
            $this->db->commit();return['status'=>'added','reclamation'=>$this->row($id)];
        }catch(\DomainException$e){try{$this->db->rollback();}catch(\Throwable){}throw$e;}catch(\Throwable$e){try{$this->db->rollback();}catch(\Throwable){}throw new \DomainException('STORAGE_UNAVAILABLE',0,$e);}
    }

    public function read(int$actor,int$case):array{$this->authorize($actor,'deadline_certificate.read');$this->case($case);return$this->evidence($case);}
    public function currentEvidence(int$case):array{$this->case($case);return$this->evidence($case);}

    private function evidence(int$case):array
    {
        $this->ready();$q=$this->db->prepare('SELECT * FROM '.$this->table('fm2_equipment_reclamations').' WHERE installation_case_id=? ORDER BY id');$q->execute([$case]);$items=[];foreach($q->get_result()->fetch_all(MYSQLI_ASSOC)as$row){$this->stored($row,$case);$items[]=$this->map($row);}$total=0;foreach($items as$item){$total+=$item['delayDays'];if($total>PHP_INT_MAX)throw new \DomainException('EVIDENCE_INVALID');}
        $canonical=json_encode($items,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);return['items'=>$items,'totalDays'=>$total,'source'=>['label'=>'Рекламации по оборудованию','locator'=>'fm2_equipment_reclamations/case/'.$case,'contentSha256'=>hash('sha256',$canonical)]];
    }
    private function command(array$c):array
    {
        if(array_keys($c)!==['requestId','installationCaseId','actorId','number','delayDays'])throw new \InvalidArgumentException('INVALID_COMMAND');$number=is_string($c['number'])?trim($c['number']):'';
        if(!is_string($c['requestId'])||preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$c['requestId'])!==1||!is_int($c['installationCaseId'])||$c['installationCaseId']<1||!is_int($c['actorId'])||$c['actorId']<1||$number===''||mb_strlen($number)>255||!is_int($c['delayDays'])||$c['delayDays']<1||$c['delayDays']>65535)throw new \InvalidArgumentException('INVALID_COMMAND');
        return array_replace($c,['number'=>$number]);
    }
    private function fingerprint(array$c):string{return hash('sha256',json_encode([$c['requestId'],$c['installationCaseId'],$c['actorId'],$c['number'],$c['delayDays']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
    private function map(array$r):array{return['id'=>(int)$r['id'],'installationCaseId'=>(int)$r['installation_case_id'],'number'=>(string)$r['number'],'delayDays'=>(int)$r['delay_days'],'actorId'=>(int)$r['actor_user_id'],'recordedAt'=>$this->rfc3339((string)$r['recorded_at'])];}
    private function stored(array$r,?int$case=null):void{$number=(string)($r['number']??'');if((int)($r['id']??0)<1||(int)($r['installation_case_id']??0)<1||($case!==null&&(int)$r['installation_case_id']!==$case)||(int)($r['actor_user_id']??0)<1||$number===''||trim($number)!==$number||mb_strlen($number)>255||(int)($r['delay_days']??0)<1||(int)$r['delay_days']>65535||!is_string($r['normalized_number_sha256']??null)||!hash_equals(hash('sha256',mb_strtolower($number,'UTF-8')),$r['normalized_number_sha256'])||preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',(string)($r['request_id']??''))!==1||preg_match('/^[0-9a-f]{64}$/D',(string)($r['request_fingerprint']??''))!==1)throw new \DomainException('EVIDENCE_INVALID');$this->rfc3339((string)($r['recorded_at']??''));}
    private function row(int$id):array{$q=$this->db->prepare('SELECT * FROM '.$this->table('fm2_equipment_reclamations').' WHERE id=?');$q->execute([$id]);$r=$q->get_result()->fetch_assoc();if(!$r)throw new \DomainException('STORAGE_UNAVAILABLE');$this->stored($r);return$this->map($r);}
    private function authorize(int$actor,string$permission):void{$q=$this->db->prepare('SELECT 1 FROM '.$this->table('fm2_pilot_users').' u JOIN '.$this->table('fm2_pilot_user_roles').' ur ON ur.user_id=u.user_id JOIN '.$this->table('fm2_pilot_roles').' r ON r.role_id=ur.role_id JOIN '.$this->table('fm2_pilot_role_permissions').' rp ON rp.role_id=r.role_id WHERE u.user_id=? AND u.status=1 AND BINARY u.activation_state=\'active\' AND r.status=1 AND BINARY rp.permission=? LIMIT 1');$q->execute([$actor,$permission]);if(!$q->get_result()->fetch_column())throw new \DomainException('FORBIDDEN');}
    private function case(int$case):void{$this->ready();$q=$this->db->prepare('SELECT 1 FROM '.$this->table('fm2_installation_cases').' WHERE id=?');$q->execute([$case]);if(!$q->get_result()->fetch_column())throw new \DomainException('CASE_NOT_FOUND');}
    private function ready():void{if(!EquipmentReclamationSchemaMigration::isCompleteCompatible($this->db,$this->prefix))throw new \DomainException('STORAGE_UNAVAILABLE');}
    private function table(string$name):string{return'`'.$this->prefix.$name.'`';}
    private function instant(mixed$value):string{$d=is_string($value)?\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$value,new \DateTimeZone('UTC')):false;if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$value)throw new \DomainException('CLOCK_UNAVAILABLE');return$d->format('Y-m-d H:i:s.u');}
    private function rfc3339(string$value):string{$d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u',$value,new \DateTimeZone('UTC'));if(!$d)throw new \DomainException('EVIDENCE_INVALID');return$d->format('Y-m-d\TH:i:s\Z');}
}
