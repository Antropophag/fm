<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__).'/Support/PasswordSchemaContract.php';require dirname(__DIR__).'/Yii2/PasswordAccessFixture.php';
use FMonitor2\InstallationProcess\{CanonicalMigrationApplication,ProductionPilotMigrationCatalogue,EmailFirstPasswordRecoverySchemaMigration};
$f=new PasswordAccessFixture();$db=$f->web->db;$p=$f->web->p;$failures=[];
$check=static function(bool $ok,string $message)use(&$failures):void{if(!$ok)$failures[]=$message;};
try {
    assertSameValue(true,EmailFirstPasswordRecoverySchemaMigration::isReady($db,$p),'fixture reaches current public schema readiness');
    $enum=$db->query("SHOW COLUMNS FROM {$p}fm2_pilot_users LIKE 'activation_state'")->fetch_assoc()['Type'];
    $check($enum==="enum('pending_invitation','invited','pending_password','active','blocked')",'additive successor retains historical state representations');
    foreach(PasswordSchemaContract::manifests($p)as$name=>$manifest)assertSameValue($manifest,PasswordSchemaContract::actual($db,$p.$name),'independent complete literal manifest '.$name);
    $mutations=[
        ['fm2_password_recovery_requests','token-width',"MODIFY token_hash BINARY(16) NOT NULL"],
        ['fm2_password_events','audit-time-type',"MODIFY occurred_at_utc VARCHAR(40) NOT NULL"],
        ['fm2_password_events','audit-nullability',"MODIFY user_id BIGINT UNSIGNED NULL"],
        ['fm2_password_events','audit-sequence',"MODIFY id BIGINT UNSIGNED NOT NULL"],
        ['fm2_password_recovery_requests','delivery-default',"MODIFY delivery_state VARCHAR(32) NOT NULL DEFAULT 'delivered'"],
        ['fm2_password_events','engine',"ENGINE=MyISAM"],
        ['fm2_password_events','table-collation',"DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"],
        ['fm2_password_events','column-collation',"MODIFY action VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL"],
        ['fm2_password_recovery_requests','token-index-prefix',"DROP INDEX uq_password_recovery_token, ADD UNIQUE KEY uq_password_recovery_token(token_hash(16))"],
        ['fm2_password_recovery_requests','token-uniqueness',"DROP INDEX uq_password_recovery_token, ADD KEY uq_password_recovery_token(token_hash)"],
    ];
    $fk=$db->query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='{$p}fm2_password_recovery_requests'")->fetch_column();
    $mutations[]=['fm2_password_recovery_requests','foreign-key',"DROP FOREIGN KEY `{$fk}`"];
    foreach($mutations as[$name,$label,$alter]){
        $table=$p.$name;$ddl=PasswordSchemaContract::ddl($p)[$name];
        try {
            $db->query("ALTER TABLE `$table` $alter");$broken=$db->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
            $check(!EmailFirstPasswordRecoverySchemaMigration::isReady($db,$p),'reject '.$label.' at readiness');
            $outcome=EmailFirstPasswordRecoverySchemaMigration::apply($db,$p);
            $check(($outcome['reason']??null)==='SCHEMA_MIGRATION_CONFLICT','reject '.$label.' at migration');
            $check($broken===$db->query("SHOW CREATE TABLE `$table`")->fetch_row()[1],'preserve incompatible '.$label.' without repair');
        }finally{$db->query("DROP TABLE `$table`");$db->query($ddl);}
        assertSameValue(true,EmailFirstPasswordRecoverySchemaMigration::isReady($db,$p),'restored isolated fixture '.$label);
    }
    // A known interrupted additive upgrade is a recoverable predecessor, not operator-only repair.
    $catalog=ProductionPilotMigrationCatalogue::migrations();$prior=array_filter($catalog,fn($v)=>$v<=39,ARRAY_FILTER_USE_KEY);
    foreach([0,1,2,3]as$createdCount){
        $q='partial'.$createdCount.'_';assertSameValue(0,CanonicalMigrationApplication::run($db,$q,$prior)['exitCode'],'pre-v40 fixture');
        $db->query("ALTER TABLE {$q}fm2_pilot_users MODIFY activation_state ENUM('pending_invitation','invited','pending_password','active','blocked') NOT NULL");
        foreach([[601,'invited'],[602,'pending_password']]as[$id,$state]){$db->query("INSERT INTO {$q}fm2_pilot_users VALUES($id,'Interrupted','partial.$id@shlz.ru','',1,'$state',1,'2026-11-24')");$db->query("INSERT INTO {$q}fm2_pilot_auth_credentials VALUES($id,'partial.$id@shlz.ru',NULL,NULL,'2026-11-24')");}
        foreach(array_slice(PasswordSchemaContract::ddl($q),0,$createdCount,true)as$ddl)$db->query($ddl);
        $hash=$db->real_escape_string(password_hash('Partial preserved credential 2026!',PASSWORD_ARGON2ID));$db->query("INSERT INTO {$q}fm2_pilot_users VALUES(603,'Active Preserved','partial.active@shlz.ru','+70000000000',1,'active',7,'2026-11-01')");$db->query("INSERT INTO {$q}fm2_pilot_auth_credentials VALUES(603,'partial.active@shlz.ru','$hash','2026-11-01','2026-11-01')");
        $request='aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';$nonce=str_repeat('N',24);$key=str_repeat('K',32);$token=str_repeat('t',43);$ad=json_encode([$request,603,'partial.active@shlz.ru',7,'fixture-v1'],JSON_THROW_ON_ERROR);$envelope=$db->real_escape_string(base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($token,$ad,$nonce,$key)));$digest=hash('sha256',$token);
        if($createdCount>=1){$db->query("INSERT INTO {$q}fm2_password_events(id,user_id,action,actor_user_id,occurred_at_utc) VALUES(23,603,'password_reset_requested',NULL,'2026-11-24 12:00:00.000000')");$db->query("ALTER TABLE {$q}fm2_password_events AUTO_INCREMENT=800");}
        if($createdCount>=2)$db->query("INSERT INTO {$q}fm2_password_recovery_requests(request_id,user_id,email_normalized,session_version,token_hash,encrypted_token,key_id,created_at_utc,expires_at_utc,delivery_state) VALUES('$request',603,'partial.active@shlz.ru',7,UNHEX('$digest'),'$envelope','fixture-v1','2026-11-24 12:00:00.000000','2026-11-24 12:30:00.000000','transient')");
        if($createdCount>=3){$db->query("INSERT INTO {$q}fm2_password_recovery_attempts(id,request_id,email_normalized,client_address,attempted_at_utc,accepted) VALUES(31,'$request','partial.active@shlz.ru','192.0.2.91','2026-11-24 12:00:00.000000',1)");$db->query("ALTER TABLE {$q}fm2_password_recovery_attempts AUTO_INCREMENT=900");}
        $identityBefore=$db->query("SELECT * FROM {$q}fm2_pilot_users ORDER BY user_id")->fetch_all(MYSQLI_ASSOC);$identityExpected=$identityBefore;foreach($identityExpected as &$identity)if($identity['activation_state']==='invited')$identity['activation_state']='pending_password';unset($identity);
        $credentialsBefore=$db->query("SELECT * FROM {$q}fm2_pilot_auth_credentials ORDER BY user_id")->fetch_all(MYSQLI_ASSOC);$factsBefore=[];$autoBefore=[];
        foreach(array_slice(array_keys(PasswordSchemaContract::ddl($q)),0,$createdCount)as$name){$factsBefore[$name]=$db->query("SELECT * FROM {$q}{$name} ORDER BY 1,2")->fetch_all(MYSQLI_ASSOC);$autoBefore[$name]=$db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$q}{$name}'")->fetch_column();}
        $outcome=CanonicalMigrationApplication::run($db,$q,$catalog);$check($outcome['exitCode']===0&&($outcome['result']['schemaVersion']??null)===43,'resume known prefix '.$createdCount.' to current frontier');
        if($outcome['exitCode']===0){
            $check($identityExpected===$db->query("SELECT * FROM {$q}fm2_pilot_users ORDER BY user_id")->fetch_all(MYSQLI_ASSOC),'resume preserves full identities except waiting projection '.$createdCount);$check($credentialsBefore===$db->query("SELECT * FROM {$q}fm2_pilot_auth_credentials ORDER BY user_id")->fetch_all(MYSQLI_ASSOC),'resume preserves exact credentials '.$createdCount);foreach($factsBefore as$name=>$rows){$check($rows===$db->query("SELECT * FROM {$q}{$name} ORDER BY 1,2")->fetch_all(MYSQLI_ASSOC),'resume preserves populated recovery facts '.$createdCount.' '.$name);$check($autoBefore[$name]===$db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$q}{$name}'")->fetch_column(),'resume preserves sequence gap '.$createdCount.' '.$name);}
            foreach(PasswordSchemaContract::manifests($q)as$name=>$manifest)$check($manifest===PasswordSchemaContract::actual($db,$q.$name),'complete literal resumed manifest prefix'.$createdCount.' '.$name);
            $before=[];foreach(array_keys(PasswordSchemaContract::manifests($q))as$name)$before[$name]=$db->query("SELECT * FROM {$q}{$name} ORDER BY 1,2")->fetch_all(MYSQLI_ASSOC);
            $repeat=CanonicalMigrationApplication::run($db,$q,$catalog);$check($repeat['exitCode']===0&&($repeat['result']['appliedVersions']??null)===[],'partial upgrade replay stable '.$createdCount);$check($identityExpected===$db->query("SELECT * FROM {$q}fm2_pilot_users ORDER BY user_id")->fetch_all(MYSQLI_ASSOC),'replay full identity stable '.$createdCount);$check($credentialsBefore===$db->query("SELECT * FROM {$q}fm2_pilot_auth_credentials ORDER BY user_id")->fetch_all(MYSQLI_ASSOC),'replay credentials stable '.$createdCount);foreach($autoBefore as$name=>$next)$check($next===$db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$q}{$name}'")->fetch_column(),'replay sequence stable '.$createdCount.' '.$name);
            foreach($before as$name=>$rows)$check($rows===$db->query("SELECT * FROM {$q}{$name} ORDER BY 1,2")->fetch_all(MYSQLI_ASSOC),'partial replay unchanged '.$createdCount.' '.$name);
        }
    }
    assertSameValue([],$failures,'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 strict fingerprint and partial migration: '.implode('; ',$failures));
    echo "PASS: EMAIL-FIRST-PASSWORD-RECOVERY-001 strict schema fingerprint and interrupted upgrade\n";
}finally{$f->close();}
