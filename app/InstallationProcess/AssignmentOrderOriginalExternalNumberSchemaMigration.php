<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

/** Additive v42 persistence for the signed document number from 1C DO. */
final class AssignmentOrderOriginalExternalNumberSchemaMigration
{
    public static function apply(\mysqli $db,string $prefix=''):array
    {
        if(preg_match('/^[A-Za-z0-9_]{0,25}$/D',$prefix)!==1)throw new \InvalidArgumentException('INVALID_TABLE_PREFIX');
        $table=$prefix.'fm2_assignment_order_original_starts';
        $s=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $column='external_document_number';$s->execute([$table,$column]);$row=$s->get_result()->fetch_assoc();$s->close();
        if($row===null){
            if(!$db->query("ALTER TABLE `{$table}` ADD COLUMN external_document_number VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER appendix_start_date"))throw new \RuntimeException('SCHEMA_MIGRATION_FAILED');
            return ['applied'=>true,'schemaVersion'=>42,'tablesCreated'=>[],'tablesUpgraded'=>[$table]];
        }
        if(strtolower((string)$row['COLUMN_TYPE'])!=='varchar(255)'||$row['IS_NULLABLE']!=='YES'||$row['COLLATION_NAME']!=='utf8mb4_unicode_ci')throw new \RuntimeException('SCHEMA_MIGRATION_CONFLICT');
        return ['applied'=>false,'schemaVersion'=>42,'tablesCreated'=>[],'tablesUpgraded'=>[]];
    }
    public static function isCompleteCompatible(\mysqli$db,string$prefix=''):bool
    {try{if(preg_match('/^[A-Za-z0-9_]{0,25}$/D',$prefix)!==1)return false;$table=$prefix.'fm2_assignment_order_original_starts';$column='external_document_number';$s=$db->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);$row=$s->get_result()->fetch_assoc();$s->close();return$row!==null&&strtolower((string)$row['COLUMN_TYPE'])==='varchar(255)'&&$row['IS_NULLABLE']==='YES'&&$row['COLLATION_NAME']==='utf8mb4_unicode_ci';}catch(\Throwable){return false;}}
}
