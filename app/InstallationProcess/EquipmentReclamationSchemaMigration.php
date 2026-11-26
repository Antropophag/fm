<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class EquipmentReclamationSchemaMigration
{
    private const TABLE='fm2_equipment_reclamations';

    public static function apply(\mysqli$db,string$prefix=''):array
    {
        if(preg_match('/^[A-Za-z0-9_]{0,25}$/D',$prefix)!==1)throw new \InvalidArgumentException('INVALID_TABLE_PREFIX');
        $table=$prefix.self::TABLE;
        if(self::exists($db,$table)){
            if(!self::isCompleteCompatible($db,$prefix))throw new \RuntimeException('SCHEMA_MIGRATION_CONFLICT');
            return['applied'=>false,'schemaVersion'=>43,'tablesCreated'=>[],'tablesUpgraded'=>[]];
        }
        $caseTable=$prefix.'fm2_installation_cases';$userTable=$prefix.'fm2_pilot_users';
        $sql="CREATE TABLE `{$table}` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,installation_case_id BIGINT UNSIGNED NOT NULL,actor_user_id BIGINT UNSIGNED NOT NULL,number VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,normalized_number_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,delay_days SMALLINT UNSIGNED NOT NULL,request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,request_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,recorded_at DATETIME(6) NOT NULL,PRIMARY KEY(id),UNIQUE KEY uq_equipment_reclamation_request(actor_user_id,request_id),UNIQUE KEY uq_equipment_reclamation_number(installation_case_id,normalized_number_sha256),KEY ix_equipment_reclamation_case(installation_case_id,id),CONSTRAINT `{$prefix}eqr_case_fk` FOREIGN KEY(installation_case_id) REFERENCES `{$caseTable}`(id) ON UPDATE RESTRICT ON DELETE RESTRICT,CONSTRAINT `{$prefix}eqr_actor_fk` FOREIGN KEY(actor_user_id) REFERENCES `{$userTable}`(user_id) ON UPDATE RESTRICT ON DELETE RESTRICT,CHECK(delay_days BETWEEN 1 AND 65535),CHECK(normalized_number_sha256 REGEXP '^[0-9a-f]{64}$'),CHECK(request_fingerprint REGEXP '^[0-9a-f]{64}$')) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if(!$db->query($sql)||!self::isCompleteCompatible($db,$prefix))throw new \RuntimeException('SCHEMA_MIGRATION_FAILED');
        return['applied'=>true,'schemaVersion'=>43,'tablesCreated'=>[$table],'tablesUpgraded'=>[]];
    }

    public static function isCompleteCompatible(\mysqli$db,string$prefix=''):bool
    {
        try{
            if(preg_match('/^[A-Za-z0-9_]{0,25}$/D',$prefix)!==1)return false;$table=$prefix.self::TABLE;
            if(!self::exists($db,$table))return false;
            $q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$tableRow=$q->get_result()->fetch_assoc();if(($tableRow['ENGINE']??null)!=='InnoDB')return false;
            $q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$table]);$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);
            $expected=[['id','bigint(20) unsigned','NO',null,'auto_increment'],['installation_case_id','bigint(20) unsigned','NO',null,''],['actor_user_id','bigint(20) unsigned','NO',null,''],['number','varchar(255)','NO','utf8mb4_unicode_ci',''],['normalized_number_sha256','char(64)','NO','ascii_bin',''],['delay_days','smallint(5) unsigned','NO',null,''],['request_id','char(36)','NO','ascii_bin',''],['request_fingerprint','char(64)','NO','ascii_bin',''],['recorded_at','datetime(6)','NO',null,'']];
            $actual=array_map(static fn(array$r):array=>[$r['COLUMN_NAME'],strtolower($r['COLUMN_TYPE']),$r['IS_NULLABLE'],$r['COLLATION_NAME'],$r['EXTRA']],$rows);if($actual!==$expected)return false;
            $q=$db->prepare('SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE');$q->execute([$table]);$indexes=[];foreach($q->get_result()->fetch_all(MYSQLI_ASSOC)as$r)$indexes[$r['INDEX_NAME']]=[(int)$r['NON_UNIQUE'],$r['columns_list']];
            if(($indexes['PRIMARY']??null)!==[0,'id']||($indexes['uq_equipment_reclamation_request']??null)!==[0,'actor_user_id,request_id']||($indexes['uq_equipment_reclamation_number']??null)!==[0,'installation_case_id,normalized_number_sha256']||($indexes['ix_equipment_reclamation_case']??null)!==[1,'installation_case_id,id'])return false;
            $q=$db->prepare("SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL");$q->execute([$table]);$foreign=[];foreach($q->get_result()->fetch_all(MYSQLI_ASSOC)as$r)$foreign[$r['COLUMN_NAME']]=[$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME'],$r['UPDATE_RULE'],$r['DELETE_RULE']];
            if(($foreign['installation_case_id']??null)!==[$prefix.'fm2_installation_cases','id','RESTRICT','RESTRICT']||($foreign['actor_user_id']??null)!==[$prefix.'fm2_pilot_users','user_id','RESTRICT','RESTRICT'])return false;
            $q=$db->prepare('SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$clauses=array_map(static fn(array$r):string=>strtolower(str_replace(['`',' ','(',')'],['','','',''],(string)$r['CHECK_CLAUSE'])),$q->get_result()->fetch_all(MYSQLI_ASSOC));
            foreach(['delay_daysbetween1and65535',"normalized_number_sha256regexp'^[0-9a-f]{64}$'","request_fingerprintregexp'^[0-9a-f]{64}$'"]as$required){$found=false;foreach($clauses as$clause)if(str_contains($clause,$required)){$found=true;break;}if(!$found)return false;}
            return true;
        }catch(\Throwable){return false;}
    }

    private static function exists(\mysqli$db,string$table):bool{$q=$db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return(bool)$q->get_result()->fetch_column();}
}
