<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\Tests\Support\IdentityRegistryTestDatabase as Database;
use FMonitor2\InstallationProcess\CanonicalMigrationApplication as Runner;
use FMonitor2\InstallationProcess\ProductionPilotMigrationCatalogue as Catalogue;
use FMonitor2\InstallationProcess\AssignmentOrderOriginalStartSchemaMigration as Start;
use FMonitor2\InstallationProcess\AssignmentOrderOriginalExternalNumberSchemaMigration as Number;
use FMonitor2\InstallationProcess\EquipmentReclamationSchemaMigration as Claims;
use FMonitor2\RuntimeRestore\RuntimeRecoverySchemaV41 as V41;
use FMonitor2\RuntimeRestore\RuntimeRecoverySchemaV42 as V42;
use FMonitor2\RuntimeRestore\RuntimeRecoverySchemaV43 as V43;
// ORDER-LOCAL-PREVIEW-001 / RECLAMATION-DELAY-001: public migration/catalogue and recovery contracts.
$tests = [
 'additive number and canonical replay' => static function(Database $f): void {
    $db=$f->connection;$p=$f->prefix;
    assertSameValue(0,Runner::run($db,$p,array_slice(Catalogue::migrations(),0,41,true))['exitCode'],'predecessor41 setup');
    $db->query("INSERT INTO {$p}fm2_assignment_order_original_starts VALUES ('11111111-1111-4111-8111-111111111111',1,1,1,1,'initial','2026-11-01','2026-11-02','".str_repeat('a',64)."',5,'".str_repeat('b',64)."','2026-11-01 00:00:00')");
    $old=$db->query("SELECT * FROM {$p}fm2_assignment_order_original_starts")->fetch_assoc();
    assertSameValue(true,Number::apply($db,$p)['applied'],'42 applies');
    $new=$db->query("SELECT * FROM {$p}fm2_assignment_order_original_starts")->fetch_assoc();
    assertSameValue($old,array_intersect_key($new,$old),'old values preserved');
    assertSameValue(null,$new['external_document_number'],'historical number stays unknown');
    assertSameValue(true,Start::isCompleteCompatible($db,$p),'v39 accepts valid additive42');
    $result=Runner::run($db,$p,Catalogue::migrations());
    assertSameValue([0,44],[$result['exitCode'],$result['result']['schemaVersion']??null],'canonical upgrade reaches44');
    $before=$f->allState();$again=Runner::run($db,$p,Catalogue::migrations());
    assertSameValue([0,44,[]],[$again['exitCode'],$again['result']['schemaVersion']??null,$again['result']['appliedVersions']??null],'current44 replay');
    assertSameValue($before,$f->allState(),'replay changes no facts or schema');
 },
 'deep readiness requires external number' => static function(Database $f): void {
    $db=$f->connection;$p=$f->prefix;
    assertSameValue(0,Runner::run($db,$p,Catalogue::migrations())['exitCode'],'current schema setup');
    $db->query('DROP TABLE other_prefix_marker');
    $env=['FMONITOR_DB_HOST'=>getenv('FMONITOR_TEST_DB_HOST')?:'127.0.0.1','FMONITOR_DB_PORT'=>getenv('FMONITOR_TEST_DB_PORT')?:'23306','FMONITOR_DB_NAME'=>$f->name,'FMONITOR_DB_USER'=>getenv('FMONITOR_TEST_DB_ADMIN_USER')?:'root','FMONITOR_DB_PASSWORD'=>getenv('FMONITOR_TEST_DB_ADMIN_PASSWORD')?:'fmonitor2_test_root_local','FMONITOR_PROCESS_TABLE_PREFIX'=>$p,'FMONITOR_LEGACY_TABLE_PREFIX'=>$p,'FMONITOR_SESSION_STATE_ROOT'=>'/tmp/claims-readiness/state','FMONITOR_SESSION_INSTANCE'=>'claims','FMONITOR_ARTIFACT_STORAGE_ROOT'=>'/tmp/claims-readiness/artifacts','FMONITOR_ORIGINAL_DB_PASSWORD_FILE'=>'/tmp/claims-readiness/password','FMONITOR_ORIGINAL_SAFE_LOG_FILE'=>'/tmp/claims-readiness/log','FMONITOR_TRUSTED_REQUEST_HOST'=>'claims.example.test','FMONITOR_TRUSTED_REQUEST_SCHEME'=>'https'];
    $config=FMonitor2\Runtime\RuntimeConfiguration::fromEnvironment($env);
    FMonitor2\Runtime\MariaDbRuntimeReadiness::assertDeepReady($config);
    $db->query("ALTER TABLE {$p}fm2_assignment_order_original_starts DROP COLUMN external_document_number");
    $reason=null;try{FMonitor2\Runtime\MariaDbRuntimeReadiness::assertDeepReady($config);}catch(RuntimeException $e){$reason=$e->getMessage();}
    assertSameValue('SCHEMA_NOT_READY',$reason,'missing42 rejected even with valid43');
    Number::apply($db,$p);FMonitor2\Runtime\MariaDbRuntimeReadiness::assertDeepReady($config);
    $db->query("DROP TABLE {$p}fm2_equipment_reclamations");
    $reason=null;try{FMonitor2\Runtime\MariaDbRuntimeReadiness::assertDeepReady($config);}catch(RuntimeException $e){$reason=$e->getMessage();}
    assertSameValue('SCHEMA_NOT_READY',$reason,'missing43 rejected even with valid42');
    Claims::apply($db,$p);FMonitor2\Runtime\MariaDbRuntimeReadiness::assertDeepReady($config);
    $db->query("ALTER TABLE {$p}fm2_equipment_reclamations DROP INDEX uq_equipment_reclamation_number");
    $reason=null;try{FMonitor2\Runtime\MariaDbRuntimeReadiness::assertDeepReady($config);}catch(RuntimeException $e){$reason=$e->getMessage();}
    assertSameValue('SCHEMA_NOT_READY',$reason,'malformed43 unique guarantee fails readiness');
 },
 'number conflict is fail closed' => static function(Database $f): void {
    $db=$f->connection;$p=$f->prefix;Start::apply($db,$p);Number::apply($db,$p);
    $db->query("ALTER TABLE {$p}fm2_assignment_order_original_starts MODIFY external_document_number VARCHAR(254) NULL");
    assertSameValue(false,Number::isCompleteCompatible($db,$p),'wrong width rejected');
    $before=$f->allState();$reason=null;try{Number::apply($db,$p);}catch(RuntimeException $e){$reason=$e->getMessage();}
    assertSameValue('SCHEMA_MIGRATION_CONFLICT',$reason,'incompatible number refuses migration');
    assertSameValue($before,$f->allState(),'conflict has no writes');
 },
 'recovery inventories include new facts' => static function(Database $f): void {
    assertSameValue(true,class_exists(V42::class)&&class_exists(V43::class),'INTENDED_RED: recovery42/43 profiles');
    assertSameValue([42,43],[V42::VERSION,V43::VERSION],'exact versions');
    assertSameValue(V41::tables('x_'),V42::tables('x_'),'column-only42 keeps tables');
    assertSameValue(V41::autoIncrement('x_'),V42::autoIncrement('x_'),'column-only42 keeps counters');
    $tables=[...V42::tables('x_'),'x_fm2_equipment_reclamations'];sort($tables,SORT_STRING);
    $counters=[...V42::autoIncrement('x_'),'x_fm2_equipment_reclamations'];sort($counters,SORT_STRING);
    assertSameValue($tables,V43::tables('x_'),'43 backs up claims');assertSameValue($counters,V43::autoIncrement('x_'),'43 preserves claims identity allocation');
 },

];
foreach (['case'=>['installation_case_id','fm2_installation_cases','id'], 'actor'=>['actor_user_id','fm2_pilot_users','user_id']] as $relationship=>$target) {
 foreach (['UPDATE','DELETE'] as $action) {
  $tests["reclamation rejects $relationship ON $action CASCADE"]=static function(Database $f) use($relationship,$target,$action): void {
    $db=$f->connection;$p=$f->prefix;
    assertSameValue(0,Runner::run($db,$p,array_slice(Catalogue::migrations(),0,41,true))['exitCode'],'predecessor setup');
    Claims::apply($db,$p);assertSameValue(true,Claims::isCompleteCompatible($db,$p),'valid claims schema');
    $before=$f->allState();assertSameValue(false,Claims::apply($db,$p)['applied'],'claims replay');assertSameValue($before,$f->allState(),'claims replay no writes');
    [$column,$table,$key]=$target;$constraint=$p.'eqr_'.$relationship.'_fk';
    $update=$action==='UPDATE'?'CASCADE':'RESTRICT';$delete=$action==='DELETE'?'CASCADE':'RESTRICT';
    $db->query("ALTER TABLE {$p}fm2_equipment_reclamations DROP FOREIGN KEY {$constraint}");
    $db->query("ALTER TABLE {$p}fm2_equipment_reclamations ADD CONSTRAINT {$constraint} FOREIGN KEY({$column}) REFERENCES {$p}{$table}({$key}) ON UPDATE {$update} ON DELETE {$delete}");
    assertSameValue(false,Claims::isCompleteCompatible($db,$p),'INTENDED_RED: cascade violates immutable claims');
    $before=$f->allState();$reason=null;try{Claims::apply($db,$p);}catch(RuntimeException $e){$reason=$e->getMessage();}
    assertSameValue('SCHEMA_MIGRATION_CONFLICT',$reason,'cascade refuses migration');assertSameValue($before,$f->allState(),'no repair on conflict');
  };
 }
}
$failed=0;foreach($tests as $name=>$test){$f=null;try{$f=new Database('claims_');$test($f);echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}finally{if($f!==null)$f->close();}}exit($failed?1:0);
