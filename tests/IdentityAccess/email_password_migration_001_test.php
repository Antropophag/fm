<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__).'/Yii2/PasswordAccessFixture.php';
use FMonitor2\InstallationProcess\CanonicalMigrationApplication;
use FMonitor2\InstallationProcess\ProductionPilotMigrationCatalogue;
$f=new PasswordAccessFixture();$db=$f->web->db;$p='upgrade_';
try {
    $catalog=ProductionPilotMigrationCatalogue::migrations();$prior=array_filter($catalog,fn($v)=>$v<=39,ARRAY_FILTER_USE_KEY);
    $old=CanonicalMigrationApplication::run($db,$p,$prior);assertSameValue(0,$old['exitCode'],'predecessor reachable');
    $hash=password_hash('Existing preserved password!',PASSWORD_ARGON2ID);$quoted=$db->real_escape_string($hash);
    foreach([[101,'active',1],[102,'invited',1],[103,'pending_invitation',1],[104,'blocked',0]]as[$id,$state,$enabled]){
        $db->query("INSERT INTO {$p}fm2_pilot_users VALUES($id,'Migration $id','migration.$id@shlz.ru','',$enabled,'$state',7,'2026-11-01T00:00:00Z')");
        if($id!==103){$value=$id===102?'NULL':"'$quoted'";$db->query("INSERT INTO {$p}fm2_pilot_auth_credentials VALUES($id,'migration.$id@shlz.ru',$value,NULL,'2026-11-01T00:00:00Z')");}
    }
    $db->query("INSERT INTO {$p}fm2_pilot_invitations(user_id,token_hash,expires_at,created_at) VALUES(102,UNHEX(REPEAT('ab',32)),'2026-12-01 00:00:00','2026-11-01 00:00:00')");
    $db->query("INSERT INTO {$p}fm2_pilot_roles(role_id,code,name,description,status,source_updated_at) VALUES(600,'migration_role','Preserved role','fixture',1,'2026-11-01')");
    $db->query("INSERT INTO {$p}fm2_pilot_role_permissions VALUES(600,'checklist.edit')");
    $db->query("INSERT INTO {$p}fm2_pilot_user_roles VALUES(102,600,'fixture','2026-11-01',101)");
    $db->query("INSERT INTO {$p}fm2_pilot_user_role_events(user_id,role_id,action,occurred_at,actor_user_id) VALUES(102,600,'role_attached','2026-11-01',101)");
    $db->query("INSERT INTO {$p}fm2_pilot_user_status_events(user_id,action,occurred_at,actor_user_id) VALUES(104,'user_blocked','2026-11-01',101)");
    $db->query("INSERT INTO {$p}fm2_legacy_identity_links(local_user_id,legacy_user_id,legacy_name_snapshot,legacy_email_snapshot,legacy_status_snapshot,legacy_role_id_snapshot,legacy_role_status_snapshot,linked_by_user_id,linked_at_utc,request_id,request_fingerprint) VALUES(102,77,'Legacy Name','legacy@shlz.ru',1,16,1,101,'2026-11-01 00:00:00','11111111-1111-4111-a111-111111111111',REPEAT('a',64))");
    $historyTables=['fm2_pilot_roles','fm2_pilot_role_permissions','fm2_pilot_user_roles','fm2_pilot_user_role_events','fm2_pilot_user_status_events','fm2_legacy_identity_links'];$history=[];foreach($historyTables as$t)$history[$t]=$db->query("SELECT * FROM {$p}{$t} ORDER BY 1,2")->fetch_all(MYSQLI_ASSOC);
    $oldInvite=$db->query("SELECT id,user_id,HEX(token_hash) token,created_at FROM {$p}fm2_pilot_invitations")->fetch_all(MYSQLI_ASSOC);
    $result=CanonicalMigrationApplication::run($db,$p,$catalog);
    assertSameValue([0,41],[$result['exitCode'],$result['result']['schemaVersion']??null],'INTENDED_RED EMAIL-FIRST-PASSWORD-RECOVERY-001 canonical upgrade v40');
    $rows=$db->query("SELECT user_id,activation_state,status,session_version FROM {$p}fm2_pilot_users ORDER BY user_id")->fetch_all(MYSQLI_ASSOC);
    assertSameValue(['active','pending_password','pending_password','blocked'],array_column($rows,'activation_state'),'only invited states migrate');assertSameValue([101,102,103,104],array_map('intval',array_column($rows,'user_id')),'identity IDs preserved');assertSameValue([1,1,1,0],array_map('intval',array_column($rows,'status')),'blocked remains blocked');
    assertSameValue($hash,$db->query("SELECT password_hash FROM {$p}fm2_pilot_auth_credentials WHERE user_id=101")->fetch_column(),'existing hash byte-identical');assertSameValue(null,$db->query("SELECT password_hash FROM {$p}fm2_pilot_auth_credentials WHERE user_id=103")->fetch_column(),'imported missing credential becomes null credential');assertSameValue(1,(int)$db->query("SELECT COUNT(*) FROM {$p}fm2_pilot_auth_credentials WHERE user_id=103")->fetch_column(),'imported credential present');
    assertSameValue($oldInvite,$db->query("SELECT id,user_id,HEX(token_hash) token,created_at FROM {$p}fm2_pilot_invitations")->fetch_all(MYSQLI_ASSOC),'historical invitation preserved');
    foreach(['fm2_password_events','fm2_password_recovery_requests','fm2_password_recovery_attempts']as$table)assertSameValue(1,(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$p$table'")->fetch_column(),'new canonical table '.$table);
    $before=$db->query("SELECT * FROM {$p}fm2_password_events ORDER BY 1")->fetch_all(MYSQLI_ASSOC);$repeat=CanonicalMigrationApplication::run($db,$p,$catalog);assertSameValue([0,[]],[$repeat['exitCode'],$repeat['result']['appliedVersions']??null],'migration replay');assertSameValue($before,$db->query("SELECT * FROM {$p}fm2_password_events ORDER BY 1")->fetch_all(MYSQLI_ASSOC),'replay no duplicate events');
    foreach($historyTables as$t)assertSameValue($history[$t],$db->query("SELECT * FROM {$p}{$t} ORDER BY 1,2")->fetch_all(MYSQLI_ASSOC),'upgrade/replay preserves complete history '.$t);
    $tables=FMonitor2\RuntimeRestore\RuntimeRecoverySchemaV40::tables('');$auto=FMonitor2\RuntimeRestore\RuntimeRecoverySchemaV40::autoIncrement('');assertSameValue(109,count($tables),'independent recovery table count');assertSameValue(61,count($auto),'independent recovery auto count');foreach(['fm2_password_events','fm2_password_recovery_requests','fm2_password_recovery_attempts']as$t)assertSameValue(true,in_array($t,$tables,true),'recovery includes '.$t);
    $bad='conflict_';assertSameValue(0,CanonicalMigrationApplication::run($db,$bad,$prior)['exitCode'],'conflict predecessor');$db->query("INSERT INTO {$bad}fm2_pilot_users VALUES(901,'Invalid Active','invalid.active@shlz.ru','',1,'active',1,'2026-11-01')");$db->query("INSERT INTO {$bad}fm2_pilot_auth_credentials VALUES(901,'invalid.active@shlz.ru',NULL,NULL,'2026-11-01')");$before=$db->query("SELECT * FROM {$bad}fm2_pilot_users")->fetch_all(MYSQLI_ASSOC);$invalid=CanonicalMigrationApplication::run($db,$bad,$catalog);assertSameValue(false,$invalid['exitCode']===0,'inconsistent data rejected');assertSameValue($before,$db->query("SELECT * FROM {$bad}fm2_pilot_users")->fetch_all(MYSQLI_ASSOC),'conflict preserves users');
    echo "PASS: EMAIL-FIRST-PASSWORD-RECOVERY-001 upgrade, replay, history and recovery inventory\n";
} finally {$f->close();}
