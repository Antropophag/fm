<?php
declare(strict_types=1);require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__,2).'/vendor/autoload.php';
assertSameValue(true,class_exists(FMonitor2\InstallationProcess\AddressRegisterImportSchemaMigration::class),'INTENDED_RED ADDRESS-REGISTER-IMPORT-001 A11 schema');
use FMonitor2\InstallationProcess\AddressRegisterImportSchemaMigration as Migration;
use FMonitor2\InstallationProcess\CanonicalMigrationApplication;
use FMonitor2\InstallationProcess\ProductionPilotMigrationCatalogue;
use FMonitor2\RuntimeRestore\RuntimeRecoverySchemaV40;
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$admin=new mysqli(getenv('FMONITOR_TEST_DB_HOST')?:'127.0.0.1',getenv('FMONITOR_TEST_DB_ADMIN_USER')?:'root',getenv('FMONITOR_TEST_DB_ADMIN_PASSWORD')?:'fmonitor2_test_root_local','',(int)(getenv('FMONITOR_TEST_DB_PORT')?:23306));$name='t_address_schema_'.bin2hex(random_bytes(6));$admin->query("CREATE DATABASE `$name`");$admin->select_db($name);$p='ari_';
try{
 $catalog=ProductionPilotMigrationCatalogue::migrations();assertSameValue(Migration::class,$catalog[40]??null,'canonical successor40');$pre=$catalog;unset($pre[40]);assertSameValue(0,CanonicalMigrationApplication::run($admin,$p,$pre)['exitCode'],'v39 predecessor');$admin->query("INSERT INTO {$p}fm_maintable(id,regnumber,ordadr_address,entrance)VALUES(99991,'PREEXISTING','existing','1')");
 $before=$admin->query("SELECT * FROM {$p}fm_maintable")->fetch_all(MYSQLI_ASSOC);$first=Migration::apply($admin,$p);assertSameValue(true,$first['applied'],'new migration');assertSameValue($before,$admin->query("SELECT * FROM {$p}fm_maintable")->fetch_all(MYSQLI_ASSOC),'existing facts preserved');assertSameValue(false,Migration::apply($admin,$p)['applied'],'repeat safe');
 $required=['fm2_address_import_packages','fm2_address_import_cache','fm2_address_import_attempts','fm2_address_import_objects','fm2_address_import_operations','fm2_address_import_ids','fm2_address_import_payload_chunks'];$tables=array_column($admin->query('SHOW TABLES')->fetch_all(MYSQLI_NUM),0);$inventory=RuntimeRecoverySchemaV40::tables($p);foreach($required as$t){assertSameValue(true,in_array($p.$t,$tables,true),'created '.$t);assertSameValue(true,in_array($p.$t,$inventory,true),'restorable '.$t);}
 // Independent literal oracle: no production DDL/constants are used as expectations.
 $chunk=$p.'fm2_address_import_payload_chunks';
 $columns=$admin->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$chunk' ORDER BY ORDINAL_POSITION")->fetch_all(MYSQLI_NUM);
 foreach($columns as&$column)if($column[3]==='NULL')$column[3]=null;unset($column);
 assertSameValue([
  ['package_id','char(36)','NO',null,''],['generation_id','char(36)','NO',null,''],
  ['kind','varchar(32)','NO',null,''],['ordinal','int(10) unsigned','NO',null,''],
  ['item_count','smallint(5) unsigned','NO',null,''],['payload_json','longtext','NO',null,''],
  ['payload_sha256','char(64)','NO',null,''],['created_at_utc','datetime(6)','NO',null,''],
 ],$columns,'chunks exact ordered columns/types/null/default/no AUTO_INCREMENT');
 assertSameValue(['InnoDB','utf8mb4_bin'],$admin->query("SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$chunk'")->fetch_row(),'chunks engine/collation');
 assertSameValue(0,(int)$admin->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$chunk' AND CHARACTER_SET_NAME IS NOT NULL AND (CHARACTER_SET_NAME <> 'utf8mb4' OR COLLATION_NAME <> 'utf8mb4_bin')")->fetch_column(),'every text column binary utf8mb4');
 $keys=$admin->query("SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$chunk' ORDER BY BINARY INDEX_NAME,SEQ_IN_INDEX")->fetch_all(MYSQLI_NUM);
 foreach($keys as&$key){$key[1]=(int)$key[1];$key[2]=(int)$key[2];}unset($key);
 assertSameValue([['PRIMARY',0,1,'package_id',null,'BTREE'],['PRIMARY',0,2,'generation_id',null,'BTREE'],['PRIMARY',0,3,'kind',null,'BTREE'],['PRIMARY',0,4,'ordinal',null,'BTREE']],$keys,'only exact ordered full primary key');
 $foreignRows=$admin->query("SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='$chunk' ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION")->fetch_all(MYSQLI_NUM);
 assertSameValue([['package_id',$p.'fm2_address_import_packages','package_id','RESTRICT','RESTRICT']],$foreignRows,'chunks exact restrictive package FK');
 $checks=$admin->query("SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='$chunk'")->fetch_all(MYSQLI_ASSOC);
 // Normalize syntax only, preserving literals/operators so weakened OR/limit changes fail.
 $normalize=static fn(string$value):string=>strtolower(preg_replace('/[\\s`()]/','',$value));
 $expectedChecks=['json_validpayload_json','octet_lengthpayload_json<=4194304','payload_sha256=sha2payload_json,256',"json_typepayload_json='array'",'item_countbetween1and128','json_lengthpayload_json=item_count'];sort($expectedChecks);
 $actualChecks=array_map(static fn(array$r):string=>$normalize($r['CHECK_CLAUSE']),$checks);sort($actualChecks);
 assertSameValue($expectedChecks,$actualChecks,'exact JSON/checksum/size/array/count checks');
 // Exercise SQL enforcement, independently of introspection/readiness.
 $package='11111111-1111-4111-8111-111111111111';
 $admin->begin_transaction();
 try{
  $admin->query("INSERT INTO {$p}fm2_address_import_packages(package_id,target,operator_user_id,status,source_sha256,year,contract,mode,reason,source_json,report_json,budget_reason,created_at_utc,updated_at_utc) VALUES('$package','dev',18,'ready',REPEAT('a',64),2027,'TEST','fill_missing','','{}','{}','',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
  $insert=$admin->prepare("INSERT INTO `$chunk`(package_id,generation_id,kind,ordinal,item_count,payload_json,payload_sha256,created_at_utc) VALUES(?,?,'rows',0,?,?,?,UTC_TIMESTAMP(6))");
  $generation='22222222-2222-4222-8222-222222222222';
  $insert->execute([$package,$generation,1,'[1]',hash('sha256','[1]')]);
  foreach([
   [1,'[',hash('sha256','['),'invalid JSON'],
   [1,'[1]',str_repeat('0',64),'wrong checksum'],
   [1,'{}',hash('sha256','{}'),'non-array JSON'],
   [0,'[]',hash('sha256','[]'),'empty chunk'],
   [2,'[1]',hash('sha256','[1]'),'wrong item count'],
   [129,json_encode(array_fill(0,129,0)),hash('sha256',json_encode(array_fill(0,129,0))),'too many items'],
   [1,'["'.str_repeat('a',4194304).'"]',hash('sha256','["'.str_repeat('a',4194304).'"]'),'oversize chunk'],
  ]as[$count,$payload,$checksum,$label]){
   $failed=false;try{$insert->execute([$package,'33333333-3333-4333-8333-333333333333',$count,$payload,$checksum]);}catch(mysqli_sql_exception){$failed=true;}
   assertSameValue(true,$failed,'database rejects '.$label);
  }
 }finally{$admin->rollback();}
 // Each drift is tested independently through BOTH seams with a sibling absent.
 // The complete DDL inventory must stay byte-identical: neither repair nor partial creation.
 $chunkDdl=$admin->query("SHOW CREATE TABLE `$chunk`")->fetch_row()[1];
 $ids=$p.'fm2_address_import_ids';$idsDdl=$admin->query("SHOW CREATE TABLE `$ids`")->fetch_row()[1];
 $snapshot=static function()use($admin):array{$out=[];foreach($admin->query('SHOW TABLES')->fetch_all(MYSQLI_NUM)as[$t])$out[$t]=$admin->query("SHOW CREATE TABLE `$t`")->fetch_row()[1];ksort($out);return$out;};
 $chunkDrift=static function(string|array$sql,string$label)use($admin,$p,$chunk,$chunkDdl,$ids,$idsDdl,$snapshot):void{
  foreach((array)$sql as$statement)$admin->query($statement);
  foreach([false,true]as$missingSibling){if($missingSibling)$admin->query("DROP TABLE `$ids`");$before=$snapshot();
  foreach(['assertCompatible','apply']as$method){$failed=false;try{Migration::$method($admin,$p);}catch(Throwable){$failed=true;}assertSameValue(true,$failed,$label.' '.$method.' rejects');assertSameValue($before,$snapshot(),$label.' '.$method.' no repair or sibling creation');}}
  $admin->query("DROP TABLE `$chunk`");$admin->query($chunkDdl);$admin->query($idsDdl);Migration::assertCompatible($admin,$p);
 };
 foreach([
  ['MODIFY payload_json MEDIUMTEXT NOT NULL','payload SQL type'],
  ['MODIFY payload_sha256 CHAR(64) NULL','nullable checksum'],
  ['ALTER COLUMN ordinal SET DEFAULT 0','unexpected default'],
  ['MODIFY ordinal INT NOT NULL','signed ordinal'],
  ['ADD unwanted_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE','extra AUTO_INCREMENT'],
  ['DROP PRIMARY KEY, ADD PRIMARY KEY(package_id,kind,generation_id,ordinal)','key order'],
  ['DROP PRIMARY KEY, ADD PRIMARY KEY(package_id,generation_id,kind)','missing key part'],
  ['ADD KEY unexpected(kind)','extra secondary index'],
  ['COLLATE utf8mb4_unicode_ci','table collation'],
  ['MODIFY kind VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL','column collation'],
 ]as[$alter,$label])$chunkDrift("ALTER TABLE `$chunk` $alter",$label);
 $fk=$admin->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='$chunk' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetch_column();
 foreach(['',' ON DELETE CASCADE',' ON UPDATE CASCADE']as$rule){$sql="ALTER TABLE `$chunk` DROP FOREIGN KEY `$fk`";if($rule!=='')$sql.=", ADD CONSTRAINT `chunk_drift_fk` FOREIGN KEY(package_id) REFERENCES {$p}fm2_address_import_packages(package_id)$rule";$chunkDrift($sql,'foreign key '.$rule);}
 // An engine probe removes the FK first because MariaDB disallows MyISAM with FKs.
 $chunkDrift(["ALTER TABLE `$chunk` DROP FOREIGN KEY `$fk`","ALTER TABLE `$chunk` ENGINE=MyISAM"],'nontransactional chunks');
 foreach($checks as$check){$checkName=$check['CONSTRAINT_NAME'];$clause=$check['CHECK_CLAUSE'];$chunkDrift("ALTER TABLE `$chunk` DROP CONSTRAINT `$checkName`",'missing check '.$checkName);$chunkDrift("ALTER TABLE `$chunk` DROP CONSTRAINT `$checkName`, ADD CONSTRAINT `$checkName` CHECK (($clause) OR 1=1)",'weakened check '.$checkName);}
 // Drift in semantics matters even when table and column names still match.
 $rejectDrift=static function(string $change,string $restore,string $label)use($admin,$p):void{
  $admin->query($change);
  $ddlBefore=[];foreach($admin->query('SHOW TABLES')->fetch_all(MYSQLI_NUM)as[$table])$ddlBefore[$table]=$admin->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
  $failed=false;try{Migration::assertCompatible($admin,$p);}catch(Throwable){$failed=true;}
  assertSameValue(true,$failed,$label.' rejected');
  foreach($ddlBefore as$table=>$ddl)assertSameValue($ddl,$admin->query("SHOW CREATE TABLE `$table`")->fetch_row()[1],$label.' readiness read-only');
  $admin->query($restore);Migration::assertCompatible($admin,$p);
 };
 $rejectDrift("ALTER TABLE {$p}fm2_address_import_packages MODIFY budget_limit INT NULL DEFAULT NULL","ALTER TABLE {$p}fm2_address_import_packages MODIFY budget_limit INT UNSIGNED NOT NULL DEFAULT 0",'budget type/nullability');
 $objectIndex=$admin->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$p}fm2_address_import_objects' AND COLUMN_NAME='object_id' AND NON_UNIQUE=0")->fetch_column();
 assertSameValue(true,is_string($objectIndex),'object unique index exists');
 $rejectDrift("ALTER TABLE {$p}fm2_address_import_objects DROP INDEX `$objectIndex`","ALTER TABLE {$p}fm2_address_import_objects ADD UNIQUE KEY `$objectIndex`(object_id)",'object identity uniqueness');
 $foreign=$admin->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$p}fm2_address_import_operations' AND COLUMN_NAME='package_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetch_column();
 assertSameValue(true,is_string($foreign),'operation package foreign key exists');
 $rejectDrift("ALTER TABLE {$p}fm2_address_import_operations DROP FOREIGN KEY `$foreign`","ALTER TABLE {$p}fm2_address_import_operations ADD CONSTRAINT `$foreign` FOREIGN KEY(package_id) REFERENCES {$p}fm2_address_import_packages(package_id)",'operation package reference');
 // Public readiness must fail closed for drift and must not repair it.
 $admin->query("ALTER TABLE {$p}fm2_address_import_objects ENGINE=MyISAM");$beforeDdl=$admin->query("SHOW CREATE TABLE {$p}fm2_address_import_objects")->fetch_row()[1];$failed=false;try{Migration::assertCompatible($admin,$p);}catch(Throwable){$failed=true;}assertSameValue(true,$failed,'nontransactional engine rejected');assertSameValue($beforeDdl,$admin->query("SHOW CREATE TABLE {$p}fm2_address_import_objects")->fetch_row()[1],'readiness no DDL repair');
 // Preflight the complete existing family before creating any missing table.
 $admin->query("DROP TABLE {$p}fm2_address_import_ids");
 $failed=false;try{Migration::apply($admin,$p);}catch(Throwable){$failed=true;}
 assertSameValue(true,$failed,'partial incompatible family migration rejected');
 assertSameValue(0,(int)$admin->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$p}fm2_address_import_ids'")->fetch_column(),'no partial DDL before full preflight');
 // Preserve the canonical 25-byte prefix boundary and namespace isolation.
 foreach([str_repeat('p',25),str_repeat('q',25)]as$longPrefix){
  assertSameValue(0,CanonicalMigrationApplication::run($admin,$longPrefix,$pre)['exitCode'],'long-prefix v39 predecessor');
  $result=CanonicalMigrationApplication::run($admin,$longPrefix,$catalog);
  assertSameValue([0,40],[$result['exitCode'],$result['result']['schemaVersion']??null],'INTENDED_RED A11 canonical 25-byte prefix v40');
  foreach($required as$table)assertSameValue(1,(int)$admin->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$longPrefix}{$table}'")->fetch_column(),'long-prefix family '.$table);
  Migration::assertCompatible($admin,$longPrefix);$before=$snapshot();
  assertSameValue(false,Migration::apply($admin,$longPrefix)['applied'],'long-prefix repeat');
  assertSameValue($before,$snapshot(),'long-prefix repeat/readiness no DDL');
  // Simulate the compatible six-table partial state left by interrupted v40 DDL.
  $admin->query("DROP TABLE {$longPrefix}fm2_address_import_payload_chunks");$before=$snapshot();
  assertSameValue(true,Migration::apply($admin,$longPrefix)['applied'],'long-prefix missing chunks recovery');
  foreach($before as$table=>$ddl)assertSameValue($ddl,$admin->query("SHOW CREATE TABLE `$table`")->fetch_row()[1],'partial recovery preserves existing DDL');
  Migration::assertCompatible($admin,$longPrefix);
 }
 echo "PASS ADDRESS-REGISTER-IMPORT-001 A11 schema/inventory\n";
}finally{$admin->query("DROP DATABASE `$name`");$admin->close();}
