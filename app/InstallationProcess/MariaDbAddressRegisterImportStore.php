<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class MariaDbAddressRegisterImportStore
{
 private array $preparationLocks = [];

 private function __construct(private \mysqli $db,private string $p,private string $legacy)
 {
     foreach([$p,$legacy] as $prefix)if(strlen($prefix)>28||preg_match('/^[A-Za-z0-9_]*$/D',$prefix)!==1)throw new \InvalidArgumentException();
 }
 public static function create(\mysqli $db,string $p,string $legacy):self
 {
     return new self($db,$p,$legacy);
 }
 public function acquirePreparation(string $package,string $owner,string $now):bool
 {
     $name=$this->preparationLockName($package);$statement=$this->db->prepare('SELECT GET_LOCK(?,0)');$statement->execute([$name]);
     if((int)$statement->get_result()->fetch_column()!==1)return false;
     $statement=$this->db->prepare("SELECT 1 FROM `{$this->p}fm2_address_import_packages` WHERE package_id=?");$statement->execute([$package]);
     if(!$statement->get_result()->fetch_column()){$this->releaseNamedLock($name);return false;}
     $this->preparationLocks[$package]=['owner'=>$owner,'name'=>$name];return true;
 }
 public function releasePreparation(string $package,string $owner):void
 {
     $lock=$this->preparationLocks[$package]??null;if(!is_array($lock)||!hash_equals((string)$lock['owner'],$owner))return;
     unset($this->preparationLocks[$package]);$this->releaseNamedLock((string)$lock['name']);
 }
 public function authorized(int $actor):bool
 {
     $statement=$this->db->prepare("SELECT MAX(BINARY r.code IN('fkr_operator','manager') AND BINARY rp.permission='objects.details.edit') e,MAX(BINARY rp.permission='objects.read') r FROM `{$this->p}fm2_pilot_users` u JOIN `{$this->p}fm2_pilot_user_roles` ur ON ur.user_id=u.user_id JOIN `{$this->p}fm2_pilot_roles` r ON r.role_id=ur.role_id AND r.status=1 JOIN `{$this->p}fm2_pilot_role_permissions` rp ON rp.role_id=r.role_id WHERE u.user_id=? AND u.status=1 AND BINARY u.activation_state='active'");
     $statement->execute([$actor]);$row=$statement->get_result()->fetch_assoc();return(int)($row['e']??0)===1&&(int)($row['r']??0)===1;
 }
 private function actor(int $id):array
 {
     $statement=$this->db->prepare("SELECT full_name FROM `{$this->p}fm2_pilot_users` WHERE user_id=?");$statement->execute([$id]);return$statement->get_result()->fetch_assoc()?:[];
 }
 public function savePackage(array $package):void
 {
     $this->db->begin_transaction();
     try{$statement=$this->db->prepare("INSERT INTO `{$this->p}fm2_address_import_packages`(package_id,target,operator_user_id,status,source_sha256,preview_sha256,year,contract,mode,reason,source_json,report_json,budget_limit,budget_reason,created_at_utc,updated_at_utc)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
     $statement->execute([$package['packageId'],$package['target'],$package['actor'],$package['status'],$package['sourceSha'],$package['previewSha'],$package['year'],$package['contract'],$package['mode'],$package['reason'],'{}','{}',$package['budgetLimit'],$package['budgetReason'],$package['now'],$package['now']]);
     $source=$this->payloads()->store($package['packageId'],$package['source'],$package['now']);$report=$this->payloads()->store($package['packageId'],$package['report'],$package['now']);$statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_packages` SET source_json=?,report_json=? WHERE package_id=?");$statement->execute([$this->json($source),$this->json($report),$package['packageId']]);$this->db->commit();}catch(\Throwable$error){$this->db->rollback();throw$error;}
 }
 public function package(string $id):?array
 {
     $statement=$this->db->prepare("SELECT * FROM `{$this->p}fm2_address_import_packages` WHERE package_id=?");$statement->execute([$id]);$row=$statement->get_result()->fetch_assoc();return$row?$this->decodePackage($row):null;
 }
 public function updatePackage(string $id,string $status,array $report,string $now,int $limit=0,string $reason=''):void
 {
     $this->db->begin_transaction();try{$stored=$this->payloads()->store($id,$report,$now);$statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_packages` SET status=?,report_json=?,budget_limit=GREATEST(budget_limit,?),budget_reason=IF(?='',budget_reason,?),updated_at_utc=? WHERE package_id=? AND status<>'applied'");
     $statement->execute([$status,$this->json($stored),$limit,$reason,$reason,$now,$id]);$this->db->commit();}catch(\Throwable$error){$this->db->rollback();throw$error;}
 }
 public function readyPackage(string $id,string $sha,array $source,array $report,string $now,int $limit,string $reason):void
 {
     $this->db->begin_transaction();try{$storedSource=$this->payloads()->store($id,$source,$now);$storedReport=$this->payloads()->store($id,$report,$now);$statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_packages` SET status='ready',preview_sha256=?,source_json=?,report_json=?,budget_limit=GREATEST(budget_limit,?),budget_reason=IF(?='',budget_reason,?),updated_at_utc=? WHERE package_id=? AND status<>'applied'");
     $statement->execute([$sha,$this->json($storedSource),$this->json($storedReport),$limit,$reason,$reason,$now,$id]);$this->db->commit();}catch(\Throwable$error){$this->db->rollback();throw$error;}
 }
 public function cache(string $hash):?array
 {
     return$this->cacheRow($hash,false);
 }
 public function readCompletedCache(string $hash):?array
 {
     return$this->cacheRow($hash,true);
 }
 public function claimCache(string $hash,string $owner,array $request=[]):bool
 {
     $lock=$this->cacheAllocatorLock();if(!$this->acquireNamedLock($lock,10))return false;$inserted=false;
     try{$this->db->begin_transaction();$version=$this->nextCacheVersion($hash);$statement=$this->db->prepare("INSERT INTO `{$this->p}fm2_address_import_cache`(entry_id,request_hash,entry_version,provider,request_json,response_json,response_hash,captured_at_utc,accepted,rules_version,lock_owner,lock_key)VALUES(?,?,?,'dadata-clean-address',?,'{}',?,UTC_TIMESTAMP(6),0,'ADDRESS-REGISTER-IMPORT-001@2026-11-18',?,?)");$statement->execute([$this->uuid(),$hash,$version,$this->json($request),str_repeat('0',64),$owner,$hash]);$this->db->commit();$inserted=true;}catch(\mysqli_sql_exception){try{$this->db->rollback();}catch(\Throwable){}}catch(\Throwable$error){try{$this->db->rollback();}catch(\Throwable){}throw$error;}finally{$this->releaseNamedLock($lock);}
     if($inserted)return true;$statement=$this->db->prepare("SELECT lock_owner FROM `{$this->p}fm2_address_import_cache` WHERE lock_key=?");$statement->execute([$hash]);$existing=$statement->get_result()->fetch_column();return is_string($existing)&&hash_equals($existing,$owner);
 }
 public function storeCache(string $hash,array $response,bool $accepted,string $now,string $owner):void
 {
     $json=$this->json($response);$statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_cache` SET response_json=?,response_hash=?,captured_at_utc=?,accepted=?,rules_version='ADDRESS-REGISTER-IMPORT-001@2026-11-18',lock_owner=NULL,lock_key=NULL WHERE request_hash=? AND lock_owner=?");
     $statement->execute([$json,hash('sha256',$json),$now,$accepted?1:0,$hash,$owner]);if($statement->affected_rows!==1)throw new \RuntimeException('CACHE_CLAIM_LOST');
 }
 public function discardClaim(string $hash,string $owner):void
 {
     $statement=$this->db->prepare("DELETE FROM `{$this->p}fm2_address_import_cache` WHERE request_hash=? AND lock_owner=?");$statement->execute([$hash,$owner]);
 }
 public function recoverClaim(string$hash,string$owner,bool$retryUnknown,string$reason,string$now):bool
 {
     if(!$retryUnknown||trim($reason)==='')return false;
     $this->db->begin_transaction();
     try{
         $statement=$this->db->prepare("SELECT entry_id,lock_owner FROM `{$this->p}fm2_address_import_cache` WHERE lock_key=? FOR UPDATE");
         $statement->execute([$hash]);$claim=$statement->get_result()->fetch_assoc();
         if(!$claim||!is_string($claim['lock_owner'])||!hash_equals($claim['lock_owner'],$owner)){$this->db->rollback();return false;}
         $statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_attempts` SET outcome='unknown' WHERE package_id=? AND request_hash=? AND outcome='reserved'");
         $statement->execute([$owner,$hash]);
         $statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_packages` SET budget_reason=?,updated_at_utc=? WHERE package_id=?");
         $statement->execute([$reason,$now,$owner]);
         $this->db->commit();return true;
     }catch(\Throwable$error){$this->db->rollback();throw$error;}
 }
 public function reserveAttempt(string $package,string $hash,int $requestedLimit,string $budgetReason,string $now):array
 {
     $this->db->begin_transaction();
     try{
         $statement=$this->db->prepare("SELECT budget_limit FROM `{$this->p}fm2_address_import_packages` WHERE package_id=? FOR UPDATE");$statement->execute([$package]);$old=$statement->get_result()->fetch_column();if($old===false)throw new \RuntimeException('PACKAGE_NOT_FOUND');$old=(int)$old;
         if($requestedLimit>$old&&trim($budgetReason)===''){$this->db->rollback();return['reserved'=>false,'reasonCode'=>'BUDGET_REASON_REQUIRED'];}
         $limit=max($old,$requestedLimit);$statement=$this->db->prepare("SELECT COUNT(*) FROM `{$this->p}fm2_address_import_attempts` WHERE package_id=?");$statement->execute([$package]);$used=(int)$statement->get_result()->fetch_column();
         if($used>=$limit){$this->db->rollback();return['reserved'=>false,'reasonCode'=>'REQUEST_BUDGET_EXCEEDED','paidAttempts'=>$used,'remainingBudget'=>0,'budgetLimit'=>$limit];}
         $id=$this->uuid();$statement=$this->db->prepare("INSERT INTO `{$this->p}fm2_address_import_attempts` VALUES(?,?,?,?,?,?)");$statement->execute([$id,$package,$hash,$used+1,'reserved',$now]);
         $statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_packages` SET budget_limit=?,budget_reason=IF(?='',budget_reason,?) WHERE package_id=?");$statement->execute([$limit,$budgetReason,$budgetReason,$package]);
         $this->db->commit();return['reserved'=>true,'attemptId'=>$id,'attemptNo'=>$used+1,'paidAttempts'=>$used+1,'remainingBudget'=>$limit-$used-1,'budgetLimit'=>$limit];
     }catch(\Throwable$error){$this->db->rollback();throw$error;}
 }
 public function settleAttempt(string $id,string $outcome):void
 {
     $statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_attempts` SET outcome=? WHERE attempt_id=? AND outcome='reserved'");$statement->execute([$outcome,$id]);
 }
 public function attempts(string $package):int
 {
     $statement=$this->db->prepare("SELECT COUNT(*) FROM `{$this->p}fm2_address_import_attempts` WHERE package_id=?");$statement->execute([$package]);return(int)$statement->get_result()->fetch_column();
 }
 public function caches():iterable
 {
     $hash='';$captured='0000-00-00 00:00:00.000000';$version='0';
     while(true){$statement=$this->db->prepare("SELECT * FROM `{$this->p}fm2_address_import_cache` WHERE lock_owner IS NULL AND (request_hash>? OR (request_hash=? AND captured_at_utc>?) OR (request_hash=? AND captured_at_utc=? AND entry_version>?)) ORDER BY request_hash,captured_at_utc,entry_version LIMIT 1");$statement->execute([$hash,$hash,$captured,$hash,$captured,$version]);$row=$statement->get_result()->fetch_assoc();if(!$row)return;$hash=(string)$row['request_hash'];$captured=(string)$row['captured_at_utc'];$version=(string)$row['entry_version'];$row['request']=json_decode($row['request_json'],true,flags:JSON_THROW_ON_ERROR);$row['response']=json_decode($row['response_json'],true,flags:JSON_THROW_ON_ERROR);yield$row;}
 }
 public function importCache(iterable $entries):void
 {
     $lock=$this->cacheAllocatorLock();if(!$this->acquireNamedLock($lock,10))throw new \RuntimeException('CACHE_BUSY');
     try{$this->db->begin_transaction();foreach($entries as$entry){$hash=(string)$entry['requestHash'];$version=$this->nextCacheVersion($hash);$json=$this->json($entry['response']);$statement=$this->db->prepare("INSERT INTO `{$this->p}fm2_address_import_cache`(entry_id,request_hash,entry_version,provider,request_json,response_json,response_hash,captured_at_utc,accepted,rules_version,lock_owner,lock_key)VALUES(?,?,?,'dadata-clean-address',?,?,?,?,1,'ADDRESS-REGISTER-IMPORT-001@2026-11-18',NULL,NULL)");$statement->execute([$this->uuid(),$hash,$version,$this->json($entry['request']),$json,hash('sha256',$json),$entry['capturedAt']]);}$this->db->commit();}catch(\Throwable$error){try{$this->db->rollback();}catch(\Throwable){}throw$error;}finally{$this->releaseNamedLock($lock);}
 }
 public function findMany(iterable $sourceObjects):AddressRegisterIdentityLookup
 {
     return AddressRegisterIdentityLookup::create($sourceObjects,$this->identityCandidateRows());
 }
 private function identityCandidateRows():iterable
 {
     $effectiveRegistration="CASE WHEN JSON_CONTAINS_PATH(e.values_json,'one','$.regnumber') THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.values_json,'$.regnumber')),'null') ELSE m.regnumber END";
     $effectiveEntrance="CASE WHEN JSON_CONTAINS_PATH(e.values_json,'one','$.entrance') THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.values_json,'$.entrance')),'null') ELSE m.entrance END";
     $effectiveUnom="CASE WHEN JSON_CONTAINS_PATH(e.values_json,'one','$.unom') THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.values_json,'$.unom')),'null') ELSE o.unom END";
     $select="SELECT m.id,$effectiveRegistration regnumber,m.ordadr_address,$effectiveEntrance entrance,$effectiveUnom unom,o.house_fias_id,o.registration_number source_registration,o.source_identity_hash FROM `{$this->legacy}fm_maintable` m LEFT JOIN `{$this->p}fm2_address_import_objects` o ON o.object_id=m.id LEFT JOIN `{$this->p}fm2_object_detail_edits` e ON e.object_id=m.id";
     $last=null;
     while(true){if($last===null){$statement=$this->db->prepare($select.' ORDER BY m.id LIMIT 256');$statement->execute();}else{$statement=$this->db->prepare($select.' WHERE m.id>? ORDER BY m.id LIMIT 256');$statement->execute([$last]);}$rows=$statement->get_result()->fetch_all(MYSQLI_ASSOC);if($rows===[])return;foreach($rows as$row){$last=(string)$row['id'];yield$row;}if(count($rows)<256)return;}
 }
 public function find(array$f):array
 {
     $registration=(string)$f['regnumber'];
     $identity=hash('sha256',$f['regnumber'].'|'.$f['unom'].'|'.$f['entrance']);
     $effectiveRegistration="CASE WHEN JSON_CONTAINS_PATH(e.values_json,'one','$.regnumber') THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.values_json,'$.regnumber')),'null') ELSE m.regnumber END";
     $effectiveEntrance="CASE WHEN JSON_CONTAINS_PATH(e.values_json,'one','$.entrance') THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.values_json,'$.entrance')),'null') ELSE m.entrance END";
     $effectiveUnom="CASE WHEN JSON_CONTAINS_PATH(e.values_json,'one','$.unom') THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.values_json,'$.unom')),'null') ELSE o.unom END";
     $statement=$this->db->prepare("SELECT m.id,$effectiveRegistration regnumber,m.ordadr_address,$effectiveEntrance entrance,$effectiveUnom unom,o.house_fias_id FROM `{$this->legacy}fm_maintable` m LEFT JOIN `{$this->p}fm2_address_import_objects` o ON o.object_id=m.id LEFT JOIN `{$this->p}fm2_object_detail_edits` e ON e.object_id=m.id WHERE BINARY $effectiveRegistration=? OR BINARY o.registration_number=? OR BINARY o.source_identity_hash=?");
     $statement->execute([$registration,$registration,$identity]);
     return$statement->get_result()->fetch_all(MYSQLI_ASSOC);
 }
 public function details(int $id):array
 {
     return MariaDbEffectiveObjectDetails::create($this->db,$this->p,$this->legacy)->read($id);
 }
 public function apply(array $package,string $previewSha,string $operationId,int $actorId,string $now):array
 {
     $packageId=(string)$package['package_id'];
     $fingerprint=hash('sha256',$packageId.'|'.$previewSha);
     $lockName='fm2-import-'.substr(hash('sha256',$this->p),0,52);
     $statement=$this->db->prepare('SELECT GET_LOCK(?,10)');
     $statement->execute([$lockName]);
     if((int)$statement->get_result()->fetch_column()!==1)return['status'=>'failed','reasonCode'=>'PREVIEW_STALE'];
     $currentPackage=$package;
     $commitAttempted=false;
     $unknownReport=null;
     try {
         $this->db->begin_transaction();
         $statement=$this->db->prepare("SELECT * FROM `{$this->p}fm2_address_import_packages` WHERE package_id=? FOR UPDATE");
         $statement->execute([$packageId]);
         $row=$statement->get_result()->fetch_assoc();
         if(!$row)throw new \RuntimeException('PREVIEW_STALE');
         $currentPackage=$this->decodePackage($row);

         if(!$this->authorized($actorId))throw new \RuntimeException('AUTHORIZATION_DENIED');

         $statement=$this->db->prepare("SELECT request_fingerprint,outcome_json FROM `{$this->p}fm2_address_import_operations` WHERE operation_id=? FOR UPDATE");
         $statement->execute([$operationId]);
         $previous=$statement->get_result()->fetch_assoc();
         if($previous){
             $this->db->rollback();
             return hash_equals((string)$previous['request_fingerprint'],$fingerprint)
                 ?array_replace($this->payloads()->hydrate($packageId,json_decode((string)$previous['outcome_json'],true,flags:JSON_THROW_ON_ERROR)),['status'=>'replayed'])
                 :['status'=>'failed','reasonCode'=>'OPERATION_CONFLICT'];
         }
         if($currentPackage['target']!==($currentPackage['report']['target']??null))throw new \RuntimeException('TARGET_MISMATCH');
         if($currentPackage['status']!=='ready'||$currentPackage['preview_sha256']!==$previewSha)throw new \RuntimeException('PREVIEW_STALE');

         $actor=$this->actor($actorId);
         if(!isset($actor['full_name']))throw new \RuntimeException('AUTHORIZATION_DENIED');
         $objectIds=[];
         foreach($currentPackage['source']['objects']as$object){
             $fields=$object['fields'];
             $matches=$this->find($fields);
             if(count($matches)>1)throw new \RuntimeException('IDENTITY_CONFLICT');
             $created=$matches===[];
             $objectId=$created?$this->createObject($fields,$now):(int)$matches[0]['id'];
             $this->lockObject($objectId,(int)($object['expectedRevision']??0));
             $effective=$created?['_revision'=>0]:$this->details($objectId);
             if(!$created&&(int)$effective['_revision']!==(int)($object['expectedRevision']??0))throw new \RuntimeException('PREVIEW_STALE');
             $this->assertIdentity($objectId,$object,$effective);
             $this->mutate($objectId,$fields,$currentPackage['mode']==='overwrite',$packageId,(string)$currentPackage['reason'],$actorId,(string)$actor['full_name'],$now,$created,$effective);
             $this->bindSource($objectId,$object,$now);
             $objectIds[(string)$fields['regnumber']]=$objectId;
         }
         $rows=new AddressRegisterPayloadWriter();
         foreach($currentPackage['report']['rows']as$row){
             $row['objectId']=$objectIds[(string)$row['registrationNumber']]??null;
             if(in_array($row['action']??null,['FILLED','REPLACED'],true)){
                 $row['message']=($row['action']==='REPLACED')?'Значение заменено.':'Поле заполнено.';
                 $row['nextAction']='Действий не требуется.';
             }
             $rows->add($row);
         }
         $out=array_replace($currentPackage['report'],['status'=>'applied','reasonCode'=>null,'phase'=>'applied','rows'=>$rows->finish()]);
         $unknownReport=$this->snapshot(array_replace($currentPackage['report'],['status'=>'unknown','reasonCode'=>'COMMIT_OUTCOME_UNKNOWN']));
         $storedOut=$this->payloads()->store($packageId,$out,$now);
         $statement=$this->db->prepare("INSERT INTO `{$this->p}fm2_address_import_operations`(operation_id,request_fingerprint,package_id,outcome_json,created_at_utc)VALUES(?,?,?,?,?)");
         $statement->execute([$operationId,$fingerprint,$packageId,$this->json($storedOut),$now]);
         $statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_packages` SET status='applied',report_json=?,updated_at_utc=? WHERE package_id=? AND status='ready' AND preview_sha256=?");
         $statement->execute([$this->json($storedOut),$now,$packageId,$previewSha]);
         if($statement->affected_rows!==1)throw new \RuntimeException('PREVIEW_STALE');
         $commitAttempted=true;
         $this->db->commit();
         return$out;
     } catch(\Throwable $error) {
         if(!$commitAttempted)try{$this->db->rollback();}catch(\Throwable){}
         if($commitAttempted){
             try{$statement=$this->db->prepare("SELECT outcome_json FROM `{$this->p}fm2_address_import_operations` WHERE operation_id=? AND request_fingerprint=?");$statement->execute([$operationId,$fingerprint]);$json=$statement->get_result()->fetch_column();if(is_string($json))return $this->payloads()->hydrate($packageId,json_decode($json,true,flags:JSON_THROW_ON_ERROR));}catch(\Throwable){}
             return is_array($unknownReport)?$unknownReport:array_replace($currentPackage['report'],['status'=>'unknown','reasonCode'=>'COMMIT_OUTCOME_UNKNOWN']);
         }
         $message=$error->getMessage();
         if($message==='AUTHORIZATION_DENIED')return['status'=>'failed','reasonCode'=>'AUTHORIZATION_DENIED'];
         if($message==='TARGET_MISMATCH')return['status'=>'failed','reasonCode'=>'TARGET_MISMATCH'];
         $summary=$this->rollbackSummary($currentPackage['report']['summary']??[]);
         if(in_array($message,['PREVIEW_STALE','IDENTITY_CONFLICT','OBJECT_DETAILS_REVISION_STALE'],true)||in_array($error->getCode(),[1062,MariaDbObjectDetailsMutation::STALE_REVISION],true))return array_replace($currentPackage['report'],['status'=>'failed','reasonCode'=>'PREVIEW_STALE','summary'=>$summary]);
         $failure=array_replace($currentPackage['report'],['status'=>'failed','reasonCode'=>'PERSISTENCE_FAILED','summary'=>$summary]);
         try{$this->persistApplyFailure($packageId,$summary,$now);}catch(\Throwable){}
         return$failure;
     } finally {
         $this->releaseLock($lockName);
     }
 }
 private function createObject(array $fields,string $now):int
 {
     $allocationId=0;
     for($attempt=0;$attempt<10000;$attempt++){
         $this->db->query("INSERT INTO `{$this->p}fm2_address_import_ids`(object_id)VALUES(NULL)");
         $candidate=(int)$this->db->insert_id;
         $statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_ids` SET object_id=? WHERE allocation_id=? AND object_id IS NULL");
         $statement->execute([$candidate,$candidate]);
         if($statement->affected_rows!==1)throw new \RuntimeException('IDENTITY_CONFLICT');
         $statement=$this->db->prepare("SELECT 1 FROM `{$this->legacy}fm_maintable` WHERE id=?");
         $statement->execute([$candidate]);
         if(!$statement->get_result()->fetch_column()){$allocationId=$candidate;break;}
     }
     if($allocationId===0)throw new \RuntimeException('PERSISTENCE_FAILED');
     $statement=$this->db->prepare("INSERT INTO `{$this->legacy}fm_maintable`(id,ordadr_address,entrance,regnumber,workdatestart,workdateendadjusted,plan_finish_date,workdatefinish,ptoactdate)VALUES(?,?,?,?,NULL,NULL,NULL,NULL,NULL)");
     $statement->execute([$allocationId,$fields['address'],$fields['entrance'],$fields['regnumber']]);
     $statement=$this->db->prepare("INSERT INTO `{$this->p}fm2_installation_cases`(legacy_installation_object_id,process_state,actual_start_date,opened_at,opened_by_user_id,created_at,updated_at,lock_version)VALUES(?,'needs_assignment_order',NULL,NULL,NULL,?,?,1)");
     $statement->execute([$allocationId,$now,$now]);
     return$allocationId;
 }
 private function lockObject(int$objectId,int$expectedRevision):void
 {
     $statement=$this->db->prepare("SELECT id FROM `{$this->p}fm2_installation_cases` WHERE legacy_installation_object_id=? FOR UPDATE");
     $statement->execute([$objectId]);
     if(count($statement->get_result()->fetch_all(MYSQLI_ASSOC))!==1)throw new \RuntimeException('IDENTITY_CONFLICT');
     $statement=$this->db->prepare("SELECT revision FROM `{$this->p}fm2_object_detail_edits` WHERE object_id=? FOR UPDATE");
     $statement->execute([$objectId]);
     $revision=$statement->get_result()->fetch_column();
     if((int)($revision===false?0:$revision)!==$expectedRevision)throw new \RuntimeException('PREVIEW_STALE');
 }
 private function assertIdentity(int$objectId,array$object,array$effective):void
 {
     $fields=$object['fields'];
     $identity=hash('sha256',$fields['regnumber'].'|'.$fields['unom'].'|'.$fields['entrance']);
     $statement=$this->db->prepare("SELECT object_id,registration_number,unom,house_fias_id,entrance FROM `{$this->p}fm2_address_import_objects` WHERE source_identity_hash=? OR object_id=? FOR UPDATE");
     $statement->execute([$identity,$objectId]);$bindings=$statement->get_result()->fetch_all(MYSQLI_ASSOC);
     if(count($bindings)>1)throw new \RuntimeException('IDENTITY_CONFLICT');
     if($bindings){
         $binding=$bindings[0];
         if((int)$binding['object_id']!==$objectId||$binding['registration_number']!==$fields['regnumber']||$binding['unom']!==$fields['unom']||$binding['entrance']!==$fields['entrance']||$binding['house_fias_id']!==$object['evidence']['house_fias_id'])throw new \RuntimeException('IDENTITY_CONFLICT');
     }
     foreach(['regnumber','unom','entrance']as$field){
         $current=$effective[$field]['value']??null;
         if($current!==null&&trim((string)$current)!==trim((string)$fields[$field]))throw new \RuntimeException('IDENTITY_CONFLICT');
     }
 }
 private function mutate(int$id,array$fields,bool$overwrite,string$package,string$reason,int$actor,string$name,string$now,bool$created,array$current):void
 {
     $values=[];
     foreach(array_keys((new ObjectDetailsFieldRegistry())->importDefinitions())as$field){
         $input=$fields[$field]??null;
         if($input===null)continue;
         if(in_array($field,['pittype','pitmaterial'],true)&&!array_key_exists((string)$input,ObjectDetailsReferenceCatalogue::options($field)))$input=ObjectDetailsReferenceCatalogue::codeForDisplay($field,(string)$input);
         $old=$current[$field]['value']??null;
         $manualTombstone=array_key_exists($field,$current)&&$old===null&&($current[$field]['source']??null)==='manual';
         if($created||$overwrite||($old===null&&!$manualTombstone))$values[$field]=$input;
     }
     try{
         MariaDbObjectDetailsMutation::create($this->db,$this->p)->applyWithinTransaction($id,$actor,$name,(int)$current['_revision'],$values,$this->uuid(),['persistRequest'=>false,'eventType'=>'object_details_changed','source'=>'address_register_import','packageId'=>$package,'reason'=>$reason,'newObject'=>$created],new \DateTimeImmutable($now,new \DateTimeZone('UTC')));
     }catch(\RuntimeException$error){if($error->getCode()===MariaDbObjectDetailsMutation::STALE_REVISION)throw new \RuntimeException('PREVIEW_STALE');throw$error;}
     if(array_key_exists('plannedStartDate',$values)||array_key_exists('plannedFinishDate',$values)){
         $start=$values['plannedStartDate']??null;$finish=$values['plannedFinishDate']??null;
         $statement=$this->db->prepare("UPDATE `{$this->legacy}fm_maintable` SET workdatestart=IF(? IS NULL,workdatestart,?),plan_finish_date=IF(? IS NULL,plan_finish_date,?) WHERE id=?");
         $statement->execute([$start,$start,$finish,$finish,$id]);
     }
 }
 private function bindSource(int$objectId,array$object,string$now):void
 {
     $fields=$object['fields'];$identity=hash('sha256',$fields['regnumber'].'|'.$fields['unom'].'|'.$fields['entrance']);
     $statement=$this->db->prepare("SELECT object_id,registration_number,unom,house_fias_id,entrance FROM `{$this->p}fm2_address_import_objects` WHERE source_identity_hash=? FOR UPDATE");
     $statement->execute([$identity]);$bound=$statement->get_result()->fetch_assoc();
     if($bound){if((int)$bound['object_id']!==$objectId||$bound['registration_number']!==$fields['regnumber']||$bound['unom']!==$fields['unom']||$bound['entrance']!==$fields['entrance']||$bound['house_fias_id']!==$object['evidence']['house_fias_id'])throw new \RuntimeException('IDENTITY_CONFLICT');return;}
     $statement=$this->db->prepare("INSERT INTO `{$this->p}fm2_address_import_objects`(source_identity_hash,object_id,registration_number,unom,house_fias_id,entrance,source_json,created_at_utc)VALUES(?,?,?,?,?,?,?,?)");
     $statement->execute([$identity,$objectId,$fields['regnumber'],$fields['unom'],$object['evidence']['house_fias_id'],$fields['entrance'],$this->json($object),$now]);
 }
 private function cacheRow(string $hash,bool $completedOnly):?array
 {
     $condition=$completedOnly?' AND lock_owner IS NULL':'';$order=$completedOnly?'captured_at_utc DESC,entry_version DESC':'(lock_owner IS NOT NULL) DESC,captured_at_utc DESC,entry_version DESC';
     $statement=$this->db->prepare("SELECT * FROM `{$this->p}fm2_address_import_cache` WHERE request_hash=?$condition ORDER BY $order LIMIT 1");$statement->execute([$hash]);$row=$statement->get_result()->fetch_assoc();
     if(!$row)return null;$row['request']=json_decode($row['request_json'],true,flags:JSON_THROW_ON_ERROR);$row['response']=json_decode($row['response_json'],true,flags:JSON_THROW_ON_ERROR);return$row;
 }
 private function decodePackage(array $row):array
 {
     $row['source']=$this->payloads()->hydrate((string)$row['package_id'],json_decode($row['source_json'],true,flags:JSON_THROW_ON_ERROR));$row['report']=$this->payloads()->hydrate((string)$row['package_id'],json_decode($row['report_json'],true,flags:JSON_THROW_ON_ERROR));return$row;
 }
 private function payloads():MariaDbAddressRegisterImportPayloads{return MariaDbAddressRegisterImportPayloads::create($this->db,$this->p);}
 private function rollbackSummary(array $summary):array{foreach(['created','updated','filled','replaced']as$key)$summary[$key]=0;return$summary;}
 private function snapshot(mixed $value):mixed
 {
     if($value instanceof \Traversable){$writer=new AddressRegisterPayloadWriter();foreach($value as$item)$writer->add($this->snapshot($item));return$writer->finish();}
     if(!is_array($value))return$value;$out=[];foreach($value as$key=>$item)$out[$key]=$this->snapshot($item);return$out;
 }
 private function persistApplyFailure(string $packageId,array $summary,string $now):void
 {
     $statement=$this->db->prepare("SELECT report_json FROM `{$this->p}fm2_address_import_packages` WHERE package_id=? AND status<>'applied'");$statement->execute([$packageId]);$json=$statement->get_result()->fetch_column();if(!is_string($json))return;
     $report=json_decode($json,true,flags:JSON_THROW_ON_ERROR);$report['status']='failed';$report['reasonCode']='PERSISTENCE_FAILED';$report['summary']=$summary;
     $statement=$this->db->prepare("UPDATE `{$this->p}fm2_address_import_packages` SET status='failed',report_json=?,updated_at_utc=? WHERE package_id=? AND status<>'applied'");$statement->execute([$this->json($report),$now,$packageId]);
 }
 private function json(mixed $value):string
 {
     return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 }
 private function releaseLock(string $name):void
 {
     $this->releaseNamedLock($name);
 }
 private function preparationLockName(string $package):string
 {
     return'fm2-prepare-'.substr(hash('sha256',$this->p.'|'.$package),0,48);
 }
 private function cacheAllocatorLock():string
 {
     $database=(string)$this->db->query('SELECT DATABASE()')->fetch_column();return'fm2-cache-version-'.substr(hash('sha256',$database.'|'.$this->p),0,45);
 }
 private function acquireNamedLock(string $name,int $timeout):bool
 {
     $statement=$this->db->prepare('SELECT GET_LOCK(?,?)');$statement->execute([$name,$timeout]);return(int)$statement->get_result()->fetch_column()===1;
 }
 private function nextCacheVersion(string $hash):string
 {
     $statement=$this->db->prepare("SELECT COALESCE(MAX(entry_version),0) FROM `{$this->p}fm2_address_import_cache` WHERE request_hash=?");$statement->execute([$hash]);$current=(string)$statement->get_result()->fetch_column();if(!preg_match('/^[0-9]+$/D',$current)||$current==='18446744073709551615')throw new \RuntimeException('CACHE_VERSION_EXHAUSTED');$digits=str_split($current);$carry=1;for($i=count($digits)-1;$i>=0&&$carry===1;$i--){$n=(ord($digits[$i])-48)+1;$digits[$i]=(string)($n%10);$carry=intdiv($n,10);}if($carry===1)array_unshift($digits,'1');$next=ltrim(implode('',$digits),'0');return$next===''?'1':$next;
 }
 private function releaseNamedLock(string $name):void
 {
     try{$statement=$this->db->prepare('SELECT RELEASE_LOCK(?)');$statement->execute([$name]);}catch(\Throwable){}
 }
 private function uuid():string
 {
     $hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-8'.substr($hex,17,3).'-'.substr($hex,20,12);
 }
}
