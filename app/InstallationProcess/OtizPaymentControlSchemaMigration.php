<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;
final class OtizPaymentControlSchemaMigration
{
 public static function apply(\mysqli$db,string$prefix):array
 {
  if(preg_match('/^[A-Za-z0-9_]{0,25}$/D',$prefix)!==1)throw new \InvalidArgumentException('INVALID_TABLE_PREFIX');
  $defs=self::definitions($prefix);$missing=[];
  foreach($defs as$name=>$definition){$table=$prefix.$name;if(!self::exists($db,$table))$missing[$name]=$definition;elseif(!self::compatible($db,$table,$definition))throw new \RuntimeException('OTIZ_PAYMENT_CONTROL_SCHEMA_CONFLICT');}
  $created=[];foreach($missing as$name=>$definition){$table=$prefix.$name;$columns=preg_replace_callback('/CONSTRAINT ([A-Za-z0-9_]+)/',static fn(array$m):string=>'CONSTRAINT `'.substr(hash('sha256',$prefix.$m[1]),0,24).'`',$definition['ddl']);$db->query("CREATE TABLE `{$table}`({$columns}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$created[]=$table;}
  foreach($defs as$name=>$definition)if(!self::compatible($db,$prefix.$name,$definition))throw new \RuntimeException('OTIZ_PAYMENT_CONTROL_SCHEMA_CONFLICT');
  return['applied'=>$created!==[],'schemaVersion'=>41,'tablesCreated'=>$created];
 }
 private static function definitions(string$p):array{return[
  'fm2_otiz_object_payment_hold_events'=>[
   'ddl'=>"id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,object_id BIGINT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,held TINYINT NOT NULL,reason VARCHAR(500) NOT NULL,actor_user_id BIGINT UNSIGNED NOT NULL,created_at VARCHAR(40) NOT NULL,PRIMARY KEY(id),UNIQUE KEY object_revision(object_id,revision),KEY object_latest(object_id,revision),CONSTRAINT hold_revision CHECK(revision>0),CONSTRAINT hold_value CHECK(held IN(0,1)),CONSTRAINT hold_reason CHECK(CHAR_LENGTH(TRIM(reason))>0)",
   'columns'=>[['id','bigint(20) unsigned','NO',null,'auto_increment',null],['object_id','bigint(20) unsigned','NO',null,'',null],['revision','int(10) unsigned','NO',null,'',null],['held','tinyint(4)','NO',null,'',null],['reason','varchar(500)','NO',null,'','utf8mb4_unicode_ci'],['actor_user_id','bigint(20) unsigned','NO',null,'',null],['created_at','varchar(40)','NO',null,'','utf8mb4_unicode_ci']],
   'indexes'=>[['object_latest','1','BTREE','object_id,revision'],['object_revision','0','BTREE','object_id,revision'],['PRIMARY','0','BTREE','id']],'checks'=>['char_length(trim(reason))>0','heldin(0,1)','revision>0'],'foreignKeys'=>[]],
  'fm2_otiz_payment_allocations'=>[
   'ddl'=>"id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,payment_id BIGINT UNSIGNED NOT NULL,obligation_id BIGINT UNSIGNED NOT NULL,amount_cents BIGINT NOT NULL,PRIMARY KEY(id),UNIQUE KEY payment_obligation(payment_id,obligation_id),KEY obligation(obligation_id),CONSTRAINT allocation_payment FOREIGN KEY(payment_id) REFERENCES `{$p}fm2_otiz_payment_facts`(id),CONSTRAINT allocation_obligation FOREIGN KEY(obligation_id) REFERENCES `{$p}fm2_otiz_recipient_obligations`(id),CONSTRAINT allocation_amount CHECK(amount_cents>0)",
   'columns'=>[['id','bigint(20) unsigned','NO',null,'auto_increment',null],['payment_id','bigint(20) unsigned','NO',null,'',null],['obligation_id','bigint(20) unsigned','NO',null,'',null],['amount_cents','bigint(20)','NO',null,'',null]],
   'indexes'=>[['obligation','1','BTREE','obligation_id'],['payment_obligation','0','BTREE','payment_id,obligation_id'],['PRIMARY','0','BTREE','id']],'checks'=>['amount_cents>0'],'foreignKeys'=>[['obligation_id',$p.'fm2_otiz_recipient_obligations','id','RESTRICT','RESTRICT'],['payment_id',$p.'fm2_otiz_payment_facts','id','RESTRICT','RESTRICT']]]];}
 private static function exists(\mysqli$db,string$table):bool{$s=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return(int)$s->get_result()->fetch_column()===1;}
 private static function compatible(\mysqli$db,string$table,array$d):bool
 {
  if(!self::exists($db,$table))return false;
  $s=$db->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);if(($s->get_result()->fetch_row()?:[])!==['InnoDB','utf8mb4_unicode_ci'])return false;
  $s=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$s->execute([$table]);if(array_map(static fn(array$r):array=>array_values($r),$s->get_result()->fetch_all(MYSQLI_NUM))!==$d['columns'])return false;
  $s=$db->prepare('SELECT INDEX_NAME,NON_UNIQUE,INDEX_TYPE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE,INDEX_TYPE ORDER BY INDEX_NAME');$s->execute([$table]);if(array_map(static fn(array$r):array=>array_map('strval',array_values($r)),$s->get_result()->fetch_all(MYSQLI_NUM))!==$d['indexes'])return false;
  $s=$db->prepare("SELECT LOWER(REPLACE(REPLACE(CHECK_CLAUSE,'`',''),' ','')) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY 1");$s->execute([$table]);if(array_column($s->get_result()->fetch_all(MYSQLI_NUM),0)!==$d['checks'])return false;
  $s=$db->prepare('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.COLUMN_NAME');$s->execute([$table]);return array_map(static fn(array$r):array=>array_values($r),$s->get_result()->fetch_all(MYSQLI_NUM))===$d['foreignKeys'];
 }
}
