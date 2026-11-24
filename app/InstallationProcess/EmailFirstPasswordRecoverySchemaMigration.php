<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

/** Canonical v40 transition from invitation activation to email-first passwords. */
final class EmailFirstPasswordRecoverySchemaMigration
{
    private const TABLES=['fm2_password_events','fm2_password_recovery_requests','fm2_password_recovery_attempts'];
    private const SUCCESSOR="enum('pending_invitation','invited','pending_password','active','blocked')";

    public static function apply(\mysqli$db,string$prefix=''):array
    {
        IdentityAccessDefinitionSchemaMigration::assertPrefix($prefix);
        if((int)$db->query('SELECT @@in_transaction')->fetch_column()!==0)throw new \RuntimeException('PASSWORD_SCHEMA_TRANSACTION_ACTIVE');
        if(!IdentityAccessSchemaMigration::isCompleteCompatible($db,$prefix))return self::conflict($prefix,['identity']);
        $state=self::activationState($db,$prefix.'fm2_pilot_users');
        if(!in_array($state,["enum('invited','active','blocked')","enum('pending_invitation','invited','active','blocked')",self::SUCCESSOR],true))return self::conflict($prefix,['identity-state']);
        $collation=IdentityAccessDefinitionSchemaMigration::databaseCollation($db);$definitions=self::definitions($prefix,$collation);$missing=[];$conflicts=[];
        foreach(self::TABLES as$name){$table=$prefix.$name;if(!MariaDbSchemaInspector::tableExists($db,$table))$missing[]=$name;elseif(!self::matches($db,$table,$definitions[$name]['manifest']))$conflicts[]=$table;}
        if($conflicts!==[]||!self::identityDataCompatible($db,$prefix,$state))return self::conflict($prefix,$conflicts===[]?['identity-data']:$conflicts);
        $users=$prefix.'fm2_pilot_users';$credentials=$prefix.'fm2_pilot_auth_credentials';$applied=false;
        if($state!==self::SUCCESSOR){$db->query("ALTER TABLE `{$users}` MODIFY activation_state ENUM('pending_invitation','invited','pending_password','active','blocked') NOT NULL");$applied=true;}
        $db->query("INSERT INTO `{$credentials}`(user_id,email_normalized,password_hash,password_set_at,updated_at) SELECT user_id,LOWER(TRIM(email)),NULL,NULL,source_updated_at FROM `{$users}` WHERE activation_state='pending_invitation' AND NOT EXISTS(SELECT 1 FROM `{$credentials}` c WHERE c.user_id=`{$users}`.user_id)");$inserted=$db->affected_rows;
        $db->query("UPDATE `{$users}` SET activation_state='pending_password' WHERE activation_state IN('pending_invitation','invited')");$normalized=$db->affected_rows;if($inserted>0||$normalized>0)$applied=true;$created=[];
        foreach($missing as$name){$db->query($definitions[$name]['ddl']);$created[]=$prefix.$name;$applied=true;}
        return['applied'=>$applied,'schemaVersion'=>40,'tablesCreated'=>$created];
    }

    public static function isReady(\mysqli$db,string$prefix=''):bool
    {
        try{IdentityAccessDefinitionSchemaMigration::assertPrefix($prefix);if(self::activationState($db,$prefix.'fm2_pilot_users')!==self::SUCCESSOR)return false;$collation=IdentityAccessDefinitionSchemaMigration::databaseCollation($db);foreach(self::definitions($prefix,$collation)as$name=>$definition){$table=$prefix.$name;if(!MariaDbSchemaInspector::tableExists($db,$table)||!self::matches($db,$table,$definition['manifest']))return false;}return true;}catch(\Throwable){return false;}
    }

    private static function identityDataCompatible(\mysqli$db,string$prefix,string$state):bool
    {
        $users=$prefix.'fm2_pilot_users';$credentials=$prefix.'fm2_pilot_auth_credentials';
        if((int)$db->query("SELECT COUNT(*) FROM `{$users}` u LEFT JOIN `{$credentials}` c ON c.user_id=u.user_id WHERE u.activation_state='active' AND(c.user_id IS NULL OR c.password_hash IS NULL)")->fetch_column()!==0)return false;
        $waiting=str_contains($state,"'pending_password'")?"'pending_invitation','invited','pending_password'":"'pending_invitation','invited'";
        if((int)$db->query("SELECT COUNT(*) FROM `{$users}` u LEFT JOIN `{$credentials}` c ON c.user_id=u.user_id WHERE u.activation_state IN({$waiting}) AND c.password_hash IS NOT NULL")->fetch_column()!==0)return false;
        return(int)$db->query("SELECT COUNT(*) FROM `{$users}` u LEFT JOIN `{$credentials}` c ON c.user_id=u.user_id WHERE u.activation_state='invited' AND c.user_id IS NULL")->fetch_column()===0;
    }

    private static function definitions(string$prefix,string$collation):array
    {
        $tail=" ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE `{$collation}`";$fk='fk_'.$prefix.'password_recovery_user';
        $c=static fn(string$n,string$t,string$null='NO',?string$default=null,string$extra='',?string$character=null):array=>[$n,$t,$null,$default,$extra,$character===null?null:'utf8mb4',$character];
        return[
            'fm2_password_events'=>['ddl'=>"CREATE TABLE `{$prefix}fm2_password_events`(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,action VARCHAR(40) NOT NULL,actor_user_id BIGINT UNSIGNED NULL DEFAULT NULL,occurred_at_utc DATETIME(6) NOT NULL,PRIMARY KEY(id),KEY ix_password_events_user_time(user_id,occurred_at_utc)){$tail}",'manifest'=>['engine'=>'InnoDB','collation'=>$collation,'columns'=>[$c('id','bigint unsigned','NO',null,'auto_increment'),$c('user_id','bigint unsigned'),$c('action','varchar(40)','NO',null,'',$collation),$c('actor_user_id','bigint unsigned','YES'),$c('occurred_at_utc','datetime(6)')],'indexes'=>[['PRIMARY',0,'id','BTREE','NULL'],['ix_password_events_user_time',1,'user_id,occurred_at_utc','BTREE','NULL,NULL']],'fks'=>[]]],
            'fm2_password_recovery_requests'=>['ddl'=>"CREATE TABLE `{$prefix}fm2_password_recovery_requests`(request_id CHAR(36) NOT NULL,user_id BIGINT UNSIGNED NOT NULL,email_normalized VARCHAR(254) NOT NULL,session_version INT UNSIGNED NOT NULL,token_hash BINARY(32) NOT NULL,encrypted_token VARBINARY(512) NULL DEFAULT NULL,key_id VARCHAR(80) NOT NULL,created_at_utc DATETIME(6) NOT NULL,expires_at_utc DATETIME(6) NOT NULL,used_at_utc DATETIME(6) NULL DEFAULT NULL,revoked_at_utc DATETIME(6) NULL DEFAULT NULL,delivery_state VARCHAR(32) NOT NULL DEFAULT 'pending',PRIMARY KEY(request_id),UNIQUE KEY uq_password_recovery_token(token_hash),KEY ix_password_recovery_user_generation(user_id,session_version),KEY ix_password_recovery_email_created(email_normalized,created_at_utc),KEY ix_password_recovery_delivery(delivery_state,expires_at_utc),CONSTRAINT `{$fk}` FOREIGN KEY(user_id) REFERENCES `{$prefix}fm2_pilot_users`(user_id) ON DELETE CASCADE ON UPDATE RESTRICT){$tail}",'manifest'=>['engine'=>'InnoDB','collation'=>$collation,'columns'=>[$c('request_id','char(36)','NO',null,'',$collation),$c('user_id','bigint unsigned'),$c('email_normalized','varchar(254)','NO',null,'',$collation),$c('session_version','int unsigned'),$c('token_hash','binary(32)'),$c('encrypted_token','varbinary(512)','YES'),$c('key_id','varchar(80)','NO',null,'',$collation),$c('created_at_utc','datetime(6)'),$c('expires_at_utc','datetime(6)'),$c('used_at_utc','datetime(6)','YES'),$c('revoked_at_utc','datetime(6)','YES'),$c('delivery_state','varchar(32)','NO',"'pending'",'',$collation)],'indexes'=>[['PRIMARY',0,'request_id','BTREE','NULL'],['ix_password_recovery_delivery',1,'delivery_state,expires_at_utc','BTREE','NULL,NULL'],['ix_password_recovery_email_created',1,'email_normalized,created_at_utc','BTREE','NULL,NULL'],['ix_password_recovery_user_generation',1,'user_id,session_version','BTREE','NULL,NULL'],['uq_password_recovery_token',0,'token_hash','BTREE','NULL']],'fks'=>[[$fk,'user_id',$prefix.'fm2_pilot_users','user_id','CASCADE','RESTRICT']]]],
            'fm2_password_recovery_attempts'=>['ddl'=>"CREATE TABLE `{$prefix}fm2_password_recovery_attempts`(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,request_id CHAR(36) NOT NULL,email_normalized VARCHAR(254) NOT NULL,client_address VARCHAR(45) NOT NULL,attempted_at_utc DATETIME(6) NOT NULL,accepted TINYINT(1) NOT NULL,PRIMARY KEY(id),UNIQUE KEY uq_password_recovery_attempt_request(request_id),KEY ix_password_recovery_attempt_email(email_normalized,attempted_at_utc),KEY ix_password_recovery_attempt_client(client_address,attempted_at_utc)){$tail}",'manifest'=>['engine'=>'InnoDB','collation'=>$collation,'columns'=>[$c('id','bigint unsigned','NO',null,'auto_increment'),$c('request_id','char(36)','NO',null,'',$collation),$c('email_normalized','varchar(254)','NO',null,'',$collation),$c('client_address','varchar(45)','NO',null,'',$collation),$c('attempted_at_utc','datetime(6)'),$c('accepted','tinyint')],'indexes'=>[['PRIMARY',0,'id','BTREE','NULL'],['ix_password_recovery_attempt_client',1,'client_address,attempted_at_utc','BTREE','NULL,NULL'],['ix_password_recovery_attempt_email',1,'email_normalized,attempted_at_utc','BTREE','NULL,NULL'],['uq_password_recovery_attempt_request',0,'request_id','BTREE','NULL']],'fks'=>[]]],
        ];
    }

    private static function matches(\mysqli$db,string$table,array$expected):bool
    {
        $s=$db->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);$meta=$s->get_result()->fetch_assoc();if($meta===null||$meta['ENGINE']!==$expected['engine']||$meta['TABLE_COLLATION']!==$expected['collation'])return false;
        $s=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$s->execute([$table]);$columns=array_map(static fn(array$r):array=>[$r['COLUMN_NAME'],preg_replace('/^(bigint|int|smallint|tinyint)\(\d+\)/','$1',strtolower($r['COLUMN_TYPE'])),$r['IS_NULLABLE'],$r['COLUMN_DEFAULT']==='NULL'?null:$r['COLUMN_DEFAULT'],$r['EXTRA'],$r['CHARACTER_SET_NAME'],$r['COLLATION_NAME']],$s->get_result()->fetch_all(MYSQLI_ASSOC));if($columns!==$expected['columns'])return false;
        $s=$db->prepare("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols,INDEX_TYPE,GROUP_CONCAT(COALESCE(SUB_PART,'NULL') ORDER BY SEQ_IN_INDEX) subparts FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE,INDEX_TYPE ORDER BY BINARY INDEX_NAME");$s->execute([$table]);$indexes=array_map(static fn(array$r):array=>[$r['INDEX_NAME'],(int)$r['NON_UNIQUE'],$r['cols'],$r['INDEX_TYPE'],$r['subparts']],$s->get_result()->fetch_all(MYSQLI_ASSOC));if($indexes!==$expected['indexes'])return false;
        $s=$db->prepare('SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? ORDER BY BINARY k.CONSTRAINT_NAME,k.ORDINAL_POSITION');$s->execute([$table]);return array_map('array_values',$s->get_result()->fetch_all(MYSQLI_ASSOC))===$expected['fks'];
    }

    private static function activationState(\mysqli$db,string$table):string{$s=$db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='activation_state'");$s->execute([$table]);return(string)($s->get_result()->fetch_column()??'');}
    private static function conflict(string$prefix,array$conflicts):array{return['applied'=>false,'schemaVersion'=>40,'reason'=>'SCHEMA_MIGRATION_CONFLICT','conflictingTables'=>array_map(static fn(string$t):string=>str_contains($t,'-')?$t:$prefix.$t,$conflicts),'tablesCreated'=>[]];}
}
