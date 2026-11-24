<?php
declare(strict_types=1);

/** Independent EMAIL-FIRST-PASSWORD-RECOVERY-001 v40 literals; no production definitions imported. */
final class PasswordSchemaContract
{
    public static function manifests(string $prefix):array
    {
        $c=static function(string$n,string$t,bool$nullable=false,?string$default=null,string$extra=''):array{
            $text=str_starts_with($t,'varchar(')||str_starts_with($t,'char(');
            return[$n,$t,$nullable?'YES':'NO',$default,$extra,$text?'utf8mb4':null,$text?'utf8mb4_unicode_ci':null];
        };
        $manifests = [
            'fm2_password_events'=>[
                'engine'=>'InnoDB','collation'=>'utf8mb4_unicode_ci',
                'columns'=>[$c('id','bigint unsigned',false,null,'auto_increment'),$c('user_id','bigint unsigned'),$c('action','varchar(40)'),$c('actor_user_id','bigint unsigned',true),$c('occurred_at_utc','datetime(6)')],
                'indexes'=>[['PRIMARY',0,'id','BTREE'],['ix_password_events_user_time',1,'user_id,occurred_at_utc','BTREE']],
                'fks'=>[],
            ],
            'fm2_password_recovery_requests'=>[
                'engine'=>'InnoDB','collation'=>'utf8mb4_unicode_ci',
                'columns'=>[$c('request_id','char(36)'),$c('user_id','bigint unsigned'),$c('email_normalized','varchar(254)'),$c('session_version','int unsigned'),$c('token_hash','binary(32)'),$c('encrypted_token','varbinary(512)',true),$c('key_id','varchar(80)'),$c('created_at_utc','datetime(6)'),$c('expires_at_utc','datetime(6)'),$c('used_at_utc','datetime(6)',true),$c('revoked_at_utc','datetime(6)',true),$c('delivery_state','varchar(32)',false,"'pending'")],
                'indexes'=>[['PRIMARY',0,'request_id','BTREE'],['ix_password_recovery_delivery',1,'delivery_state,expires_at_utc','BTREE'],['ix_password_recovery_email_created',1,'email_normalized,created_at_utc','BTREE'],['ix_password_recovery_user_generation',1,'user_id,session_version','BTREE'],['uq_password_recovery_token',0,'token_hash','BTREE']],
                'fks'=>[['fk_'.$prefix.'password_recovery_user','user_id',$prefix.'fm2_pilot_users','user_id','CASCADE','RESTRICT']],
            ],
            'fm2_password_recovery_attempts'=>[
                'engine'=>'InnoDB','collation'=>'utf8mb4_unicode_ci',
                'columns'=>[$c('id','bigint unsigned',false,null,'auto_increment'),$c('request_id','char(36)'),$c('email_normalized','varchar(254)'),$c('client_address','varchar(45)'),$c('attempted_at_utc','datetime(6)'),$c('accepted','tinyint')],
                'indexes'=>[['PRIMARY',0,'id','BTREE'],['ix_password_recovery_attempt_client',1,'client_address,attempted_at_utc','BTREE'],['ix_password_recovery_attempt_email',1,'email_normalized,attempted_at_utc','BTREE'],['uq_password_recovery_attempt_request',0,'request_id','BTREE']],
                'fks'=>[],
            ],
        ];
        foreach($manifests as &$manifest)foreach($manifest['indexes'] as &$index)$index[]=implode(',',array_fill(0,count(explode(',',$index[2])),'NULL'));unset($manifest,$index);
        return $manifests;
    }
    public static function ddl(string $prefix):array
    {
        $tail=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci';
        return [
            'fm2_password_events'=>"CREATE TABLE `{$prefix}fm2_password_events`(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,action VARCHAR(40) NOT NULL,actor_user_id BIGINT UNSIGNED NULL DEFAULT NULL,occurred_at_utc DATETIME(6) NOT NULL,PRIMARY KEY(id),KEY ix_password_events_user_time(user_id,occurred_at_utc))".$tail,
            'fm2_password_recovery_requests'=>"CREATE TABLE `{$prefix}fm2_password_recovery_requests`(request_id CHAR(36) NOT NULL,user_id BIGINT UNSIGNED NOT NULL,email_normalized VARCHAR(254) NOT NULL,session_version INT UNSIGNED NOT NULL,token_hash BINARY(32) NOT NULL,encrypted_token VARBINARY(512) NULL DEFAULT NULL,key_id VARCHAR(80) NOT NULL,created_at_utc DATETIME(6) NOT NULL,expires_at_utc DATETIME(6) NOT NULL,used_at_utc DATETIME(6) NULL DEFAULT NULL,revoked_at_utc DATETIME(6) NULL DEFAULT NULL,delivery_state VARCHAR(32) NOT NULL DEFAULT 'pending',PRIMARY KEY(request_id),UNIQUE KEY uq_password_recovery_token(token_hash),KEY ix_password_recovery_user_generation(user_id,session_version),KEY ix_password_recovery_email_created(email_normalized,created_at_utc),KEY ix_password_recovery_delivery(delivery_state,expires_at_utc),CONSTRAINT `fk_{$prefix}password_recovery_user` FOREIGN KEY(user_id) REFERENCES `{$prefix}fm2_pilot_users`(user_id) ON DELETE CASCADE ON UPDATE RESTRICT)".$tail,
            'fm2_password_recovery_attempts'=>"CREATE TABLE `{$prefix}fm2_password_recovery_attempts`(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,request_id CHAR(36) NOT NULL,email_normalized VARCHAR(254) NOT NULL,client_address VARCHAR(45) NOT NULL,attempted_at_utc DATETIME(6) NOT NULL,accepted TINYINT(1) NOT NULL,PRIMARY KEY(id),UNIQUE KEY uq_password_recovery_attempt_request(request_id),KEY ix_password_recovery_attempt_email(email_normalized,attempted_at_utc),KEY ix_password_recovery_attempt_client(client_address,attempted_at_utc))".$tail,
        ];
    }
    public static function actual(mysqli$db,string$table):array
    {
        $s=$db->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);$meta=$s->get_result()->fetch_assoc();
        $s=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$s->execute([$table]);
        $columns=array_map(static fn(array$r)=>[$r['COLUMN_NAME'],preg_replace('/^(bigint|int|smallint|tinyint)\(\d+\)/','$1',strtolower($r['COLUMN_TYPE'])),$r['IS_NULLABLE'],$r['COLUMN_DEFAULT']==='NULL'?null:$r['COLUMN_DEFAULT'],$r['EXTRA'],$r['CHARACTER_SET_NAME'],$r['COLLATION_NAME']],$s->get_result()->fetch_all(MYSQLI_ASSOC));
        $s=$db->prepare('SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols,INDEX_TYPE,GROUP_CONCAT(COALESCE(SUB_PART,\'NULL\') ORDER BY SEQ_IN_INDEX) subparts FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE,INDEX_TYPE ORDER BY BINARY INDEX_NAME');$s->execute([$table]);$indexes=array_map(static fn(array$r)=>[$r['INDEX_NAME'],(int)$r['NON_UNIQUE'],$r['cols'],$r['INDEX_TYPE'],$r['subparts']],$s->get_result()->fetch_all(MYSQLI_ASSOC));
        $s=$db->prepare('SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? ORDER BY BINARY k.CONSTRAINT_NAME,k.ORDINAL_POSITION');$s->execute([$table]);$fks=array_map('array_values',$s->get_result()->fetch_all(MYSQLI_ASSOC));
        return['engine'=>$meta['ENGINE'],'collation'=>$meta['TABLE_COLLATION'],'columns'=>$columns,'indexes'=>$indexes,'fks'=>$fks];
    }
}
