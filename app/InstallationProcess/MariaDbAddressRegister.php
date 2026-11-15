<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

use yii\db\Connection;

final readonly class MariaDbAddressRegister
{
    private const PAGE_SIZE=50;
    private const STATUS_KEYS=['','no_case','needs_assignment_order','ready_to_open','installation','document_closeout','completed','needs_assignment_change'];

    public function __construct(private Connection $db,private string $prefix,private string $legacyPrefix,private MariaDbYiiObjectQueueProjection $projection)
    {
        foreach([$prefix,$legacyPrefix]as$p)if(strlen($p)>28||preg_match('/^[A-Za-z0-9_]*$/D',$p)!==1)throw new \InvalidArgumentException('Invalid address register prefix.');
    }

    /** @return array{objects:list<array<string,mixed>>,filters:array{q:string,status:string,page:int,pages:int,total:int}} */
    public function read(int $actor,string $q='',string $status='',int $page=1):array
    {
        $q=trim($q);
        if($actor<1||!mb_check_encoding($q,'UTF-8')||mb_strlen($q,'UTF-8')>120||$page<1||!in_array($status,self::STATUS_KEYS,true))throw new \InvalidArgumentException('Invalid address register query.');
        if(!$this->authorized($actor))throw new \DomainException('Address register access denied.');
        $p=$this->prefix;$l=$this->legacyPrefix;
        $effective=static fn(string$field,string$column):string=>MariaDbEffectiveObjectDetails::sqlValue($field,$column);
        $address=$effective('address','m.ordadr_address');$entrance=$effective('entrance','m.entrance');
        $registration=$effective('regnumber','m.regnumber');$factory=$effective('zavnumber','m.zavnumber');
        $joins=" FROM `{$l}fm_maintable` m LEFT JOIN `{$p}fm2_object_detail_edits` e ON e.object_id=m.id LEFT JOIN `{$p}fm2_installation_cases` c ON c.legacy_installation_object_id=m.id LEFT JOIN `{$p}fm2_assignment_orders` o ON o.installation_case_id=c.id AND o.version_no=(SELECT MAX(ox.version_no) FROM `{$p}fm2_assignment_orders` ox WHERE ox.installation_case_id=c.id) LEFT JOIN `{$p}fm2_assignment_order_applications` a ON a.application_id=(SELECT ax.application_id FROM `{$p}fm2_assignment_order_applications` ax WHERE ax.installation_case_id=c.id ORDER BY ax.application_sequence DESC LIMIT 1) LEFT JOIN `{$p}fm2_assignment_order_selections` s ON s.installation_case_id=c.id AND s.selection_revision=(SELECT MAX(sx.selection_revision) FROM `{$p}fm2_assignment_order_selections` sx WHERE sx.installation_case_id=c.id) LEFT JOIN `{$p}fm2_assignment_order_original_roots` r ON r.installation_case_id=c.id AND r.assignment_order_id=s.assignment_order_id AND r.composition_identity=s.composition_identity AND r.composition_sha256=s.composition_sha256 LEFT JOIN `{$p}fm2_assignment_order_original_revisions` v ON v.root_original_id=r.root_original_id AND v.revision_id=r.current_revision_id";
        $where=[];$params=[];
        if($q!==''){$where[]="(CAST(m.id AS CHAR) LIKE :q ESCAPE '\\\\' OR {$address} LIKE :q ESCAPE '\\\\' OR {$entrance} LIKE :q ESCAPE '\\\\' OR {$registration} LIKE :q ESCAPE '\\\\' OR {$factory} LIKE :q ESCAPE '\\\\')";$params[':q']='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';}
        if($status!=='')$where[]=$this->statusSql($status);
        $whereSql=$where===[]?'':' WHERE '.implode(' AND ',$where);
        $total=(int)$this->db->createCommand('SELECT COUNT(*)'.$joins.$whereSql,$params)->queryScalar();
        $pages=max(1,(int)ceil($total/self::PAGE_SIZE));if($page>$pages)throw new \InvalidArgumentException('Address register page is unavailable.');
        $offset=($page-1)*self::PAGE_SIZE;
        $rows=$this->db->createCommand("SELECT m.id,{$address} address,{$entrance} entrance,{$registration} registration_number,{$factory} factory_number,m.workdatestart,m.workdateendadjusted,m.plan_finish_date,c.id case_id,c.process_state,c.actual_start_date,c.opened_at,c.opened_by_user_id,o.status order_status,a.application_id,s.assignment_order_id selection_order_id,v.revision_id original_revision_id,CASE WHEN JSON_TYPE(JSON_EXTRACT(e.values_json,'$.comment'))='STRING' THEN JSON_UNQUOTE(JSON_EXTRACT(e.values_json,'$.comment')) ELSE NULL END comment{$joins}{$whereSql} ORDER BY {$address},{$entrance},m.id LIMIT ".self::PAGE_SIZE." OFFSET {$offset}",$params)->queryAll();
        $objects=[];foreach($rows as$row)$objects[]=$this->baseRow($row);
        $objects=$this->enrichDetails($objects);$objects=$this->enrichCases($objects,$actor);
        return['objects'=>$objects,'filters'=>compact('q','status','page','pages','total')];
    }

    private function authorized(int $actor):bool
    {
        $p=$this->prefix;
        return(bool)$this->db->createCommand("SELECT 1 FROM `{$p}fm2_pilot_users` u JOIN `{$p}fm2_pilot_user_roles` ur ON ur.user_id=u.user_id JOIN `{$p}fm2_pilot_roles` r ON r.role_id=ur.role_id JOIN `{$p}fm2_pilot_role_permissions` rp ON rp.role_id=r.role_id WHERE u.user_id=:actor AND u.status=1 AND BINARY u.activation_state='active' AND r.status=1 AND BINARY rp.permission='objects.read' LIMIT 1",[':actor'=>$actor])->queryScalar();
    }

    private function statusSql(string $status):string
    {
        if($status==='no_case')return'(c.id IS NULL)';$stages=MariaDbYiiObjectQueue::stagePredicates($this->prefix);
        return '('.($stages[$status]??'1=1').')';
    }

    private function baseRow(array $row):array
    {
        $case=$row['case_id']===null?null:(int)$row['case_id'];$start=$this->date($row['workdatestart']);$finish=$this->date($row['workdateendadjusted'])??$this->date($row['plan_finish_date']);
        return['id'=>(int)$row['id'],'caseId'=>$case,'address'=>$this->text($row['address']),'entrance'=>$this->text($row['entrance']),'registrationNumber'=>$this->text($row['registration_number']),'factoryNumber'=>$this->text($row['factory_number']),'status'=>$case===null?'Нет монтажного дела':$this->roughStatus((string)$row['process_state']),'completionProgress'=>null,'plannedStartDate'=>$start,'plannedFinishDate'=>$finish,'actualStartDate'=>$case===null?null:$this->date($row['actual_start_date']),'actualFinishDate'=>null,'controlEngineer'=>null,'installers'=>null,'floors'=>null,'weight'=>null,'speed'=>null,'lift_type'=>null,'pittype'=>null,'pitmaterial'=>null,'paired'=>null,'ptoDate'=>null,'declarationDate'=>null,'comment'=>is_string($row['comment'])?trim($row['comment']):null,'_case'=>['process_state'=>$row['process_state'],'actual_start_date'=>$row['actual_start_date'],'opened_at'=>$row['opened_at'],'opened_by_user_id'=>$row['opened_by_user_id'],'order_status'=>$row['order_status'],'application_id'=>$row['application_id'],'selection_order_id'=>$row['selection_order_id'],'original_revision_id'=>$row['original_revision_id']]];
    }

    private function enrichDetails(array $objects):array
    {
        if($objects===[])return$objects;[$marks,$params]=$this->parameters(array_column($objects,'id'),'object');$p=$this->prefix;
        $details=$this->db->createCommand("SELECT object_id,schema_version,content_sha256,payload_json FROM `{$p}fm2_pilot_object_details` WHERE object_id IN({$marks})",$params)->queryAll();$by=[];
        foreach($details as$row){$id=(int)$row['object_id'];$json=(string)$row['payload_json'];if($row['schema_version']!=='technical-object-detail-v1'||!hash_equals((string)$row['content_sha256'],hash('sha256',$json)))continue;try{$payload=json_decode($json,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){continue;}if(($payload['schemaVersion']??null)!=='technical-object-detail-v1'||($payload['objectId']??null)!==$id||!is_array($payload['fields']??null))continue;$by[$id]=$payload['fields'];}
        $edits=$this->db->createCommand("SELECT object_id,values_json FROM `{$p}fm2_object_detail_edits` WHERE object_id IN({$marks})",$params)->queryAll();$overrides=[];foreach($edits as$row){try{$value=json_decode((string)$row['values_json'],true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){continue;}if(is_array($value))$overrides[(int)$row['object_id']]=$value;}
        $registry=new ObjectDetailsFieldRegistry();foreach($objects as&$object){$id=$object['id'];foreach(['floors','weight','speed','lift_type','pittype','pitmaterial','paired']as$key){$field=$by[$id][$key]??[];$value=is_array($field)?($field['display']??$field['raw']??null):null;if(array_key_exists($key,$overrides[$id]??[])){try{$value=$registry->snapshot($key,$overrides[$id][$key])['display'];}catch(\Throwable){$value=null;}}$object[$key]=$value;}}unset($object);return$objects;
    }

    private function enrichCases(array $objects,int $actor):array
    {
        $caseIds=array_values(array_filter(array_map(static fn(array$o):?int=>$o['caseId'],$objects)));if($caseIds===[]){foreach($objects as&$o)unset($o['_case']);return$objects;}[$marks,$params]=$this->parameters($caseIds,'case');$p=$this->prefix;
        $orders=$this->db->createCommand("SELECT c.id case_id,o.status order_status,a.application_id,a.installation_case_id,a.object_id,a.assignment_order_id,a.order_version,a.control_engineer_user_id,a.composition_identity,a.composition_sha256,a.selected_snapshot_json,s.assignment_order_id selection_order_id,v.revision_id original_revision_id FROM `{$p}fm2_installation_cases` c LEFT JOIN `{$p}fm2_assignment_orders` o ON o.installation_case_id=c.id AND o.version_no=(SELECT MAX(ox.version_no) FROM `{$p}fm2_assignment_orders` ox WHERE ox.installation_case_id=c.id) LEFT JOIN `{$p}fm2_assignment_order_applications` a ON a.application_id=(SELECT ax.application_id FROM `{$p}fm2_assignment_order_applications` ax WHERE ax.installation_case_id=c.id ORDER BY ax.application_sequence DESC LIMIT 1) LEFT JOIN `{$p}fm2_assignment_order_selections` s ON s.installation_case_id=c.id AND s.selection_revision=(SELECT MAX(sx.selection_revision) FROM `{$p}fm2_assignment_order_selections` sx WHERE sx.installation_case_id=c.id) LEFT JOIN `{$p}fm2_assignment_order_original_roots` r ON r.installation_case_id=c.id AND r.assignment_order_id=s.assignment_order_id AND r.composition_identity=s.composition_identity AND r.composition_sha256=s.composition_sha256 LEFT JOIN `{$p}fm2_assignment_order_original_revisions` v ON v.root_original_id=r.root_original_id AND v.revision_id=r.current_revision_id WHERE c.id IN({$marks})",$params)->queryAll();$byCase=[];$applications=[];
        foreach($orders as$row){$case=(int)$row['case_id'];$byCase[$case]=$row;if($row['application_id']!==null)$applications[$case]=$row;}
        $facts=$this->db->createCommand("SELECT f.installation_case_id,f.fact_type,COALESCE(c.fact_date,f.fact_date) fact_date FROM `{$p}fm2_pilot_completion_facts` f LEFT JOIN `{$p}fm2_pilot_completion_fact_corrections` c ON c.root_fact_id=f.id AND c.version_no=(SELECT MAX(cx.version_no) FROM `{$p}fm2_pilot_completion_fact_corrections` cx WHERE cx.root_fact_id=f.id) WHERE f.installation_case_id IN({$marks}) AND f.fact_type IN('pto_act','declaration') ORDER BY f.installation_case_id,f.id",$params)->queryAll();$factBy=[];foreach($facts as$f)$factBy[(int)$f['installation_case_id']][(string)$f['fact_type']]=$this->date($f['fact_date']);
        foreach($objects as&$object){$case=$object['caseId'];if($case===null)continue;$order=$byCase[$case]??[];$projection=$object['_case']+['order_status'=>$order['order_status']??null,'application_id'=>$order['application_id']??null,'selection_order_id'=>$order['selection_order_id']??null,'original_revision_id'=>$order['original_revision_id']??null];$object['status']=InstallationCaseCurrentStatus::project($projection)['label'];}unset($object);
        $caseObjects=[];$casePositions=[];foreach($objects as$position=>$object)if($object['caseId']!==null){$casePositions[]=$position;$caseObjects[]=$object;}$caseObjects=$this->projection->decorate($caseObjects,$actor);foreach($caseObjects as$index=>$object)$objects[$casePositions[$index]]=$object;
        $engineers=$this->db->createCommand("SELECT a.installation_case_id,a.engineer_user_id,u.full_name,u.activation_state FROM `{$p}fm2_control_engineer_assignments` a JOIN `{$p}fm2_pilot_users` u ON u.user_id=a.engineer_user_id WHERE a.installation_case_id IN({$marks}) AND a.assignment_sequence=(SELECT MAX(x.assignment_sequence) FROM `{$p}fm2_control_engineer_assignments` x WHERE x.installation_case_id=a.installation_case_id)",$params)->queryAll();$engineerBy=[];foreach($engineers as$engineer){$case=(int)$engineer['installation_case_id'];$name=trim((string)$engineer['full_name']);if(isset($engineerBy[$case])||(int)$engineer['engineer_user_id']<1||$name==='')throw new \RuntimeException('Malformed current control engineer.');$engineerBy[$case]=$name;}
        foreach($objects as&$object){$case=$object['caseId'];if($case===null){unset($object['_case']);continue;}$caseFacts=$factBy[$case]??[];$object['ptoDate']=$caseFacts['pto_act']??null;$object['declarationDate']=$caseFacts['declaration']??null;if($object['status']==='Работы завершены')$object['completionProgress']=100;$object['controlEngineer']=$engineerBy[$case]??null;if(isset($applications[$case])){$application=$applications[$case];if((int)$application['installation_case_id']!==$case||(int)$application['object_id']!==$object['id'])throw new \RuntimeException('Applied composition linkage mismatch.');$selected=json_decode((string)$application['selected_snapshot_json'],true,64,JSON_THROW_ON_ERROR);YiiObjectCardApplicationIntegrity::validateComposition($application,$selected);$names=[];foreach($selected['selectedInstallers']as$installer)if(is_string($installer['fullName']??null)&&trim($installer['fullName'])!=='')$names[]=trim($installer['fullName']);$object['installers']=$names===[]?null:$names;}unset($object['_case'],$object['installerCount'],$object['nextStep']);}unset($object);return$objects;
    }

    private function roughStatus(string $state):string{return match($state){'needs_assignment_order','assignment_order_prepared'=>'Требуется распоряжение','working'=>'Монтажные работы','completed'=>'Работы завершены','needs_assignment_change'=>'Требуется изменение',default=>'—'};}
    private function date(mixed $value):?string{$value=trim((string)$value);return preg_match('/^(\d{4})-(\d{2})-(\d{2})/D',$value,$m)===1&&$m[1]!=='0000'&&checkdate((int)$m[2],(int)$m[3],(int)$m[1])?$m[1].'-'.$m[2].'-'.$m[3]:null;}
    private function text(mixed $value):?string{$value=trim((string)$value);return$value===''?null:$value;}
    private function parameters(array $ids,string $stem):array{$marks=[];$params=[];foreach(array_values(array_unique(array_map('intval',$ids)))as$i=>$id){$key=':'.$stem.$i;$marks[]=$key;$params[$key]=$id;}return[implode(',',$marks),$params];}
}
