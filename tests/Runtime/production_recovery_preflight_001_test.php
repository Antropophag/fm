<?php
declare(strict_types=1);
/** PRODUCTION-MAKE-ENV-001 A09: canonical read-only verification before target effects. */
require dirname(__DIR__,2).'/app/autoload.php';
use FMonitor2\RuntimeRestore\RuntimeRecovery;
use FMonitor2\RuntimeRestore\RuntimeRecoverySchemaV43;
$root=realpath(sys_get_temp_dir()).'/fm2-preflight-'.bin2hex(random_bytes(6));
mkdir($root,0700);$bundle=$root.'/bundle';mkdir($bundle,0700);$prefix='pf_';
$env=['FMONITOR_DB_HOST'=>'127.0.0.1','FMONITOR_DB_PORT'=>'1','FMONITOR_DB_NAME'=>'unreachable','FMONITOR_DB_USER'=>'unreachable','FMONITOR_DB_PASSWORD'=>'synthetic','FMONITOR_PROCESS_TABLE_PREFIX'=>$prefix,'FMONITOR_LEGACY_TABLE_PREFIX'=>$prefix,'FMONITOR_SESSION_INSTANCE'=>'preflight','FMONITOR_SESSION_STATE_ROOT'=>$root.'/absent-state','FMONITOR_ARTIFACT_STORAGE_ROOT'=>$root.'/absent-state/artifacts','FMONITOR_ORIGINAL_DB_PASSWORD_FILE'=>$root.'/absent-secrets/password','FMONITOR_ORIGINAL_SAFE_LOG_FILE'=>$root.'/absent-state/log/original-safe.jsonl','FMONITOR_TRUSTED_REQUEST_HOST'=>'preflight.example.org','FMONITOR_TRUSTED_REQUEST_SCHEME'=>'https'];
$argv=['verify','--bundle',$bundle,'--source-commit',str_repeat('a',40),'--image-reference','fmonitor2-runtime:fixture','--image-id','sha256:'.str_repeat('b',64)];
$expect=static function(bool$ok,string$message):void{if(!$ok)throw new RuntimeException('INTENDED_RED: '.$message);};
$write=static function(array$m)use($bundle):void{file_put_contents($bundle.'/manifest.json',json_encode($m,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");chmod($bundle.'/manifest.json',0600);};
$verify=static fn(array$args):array=>RuntimeRecovery::run(['runtime-recovery',...$args],$env);
try{
 file_put_contents($bundle.'/database.sql',"-- synthetic read-only verification\n");chmod($bundle.'/database.sql',0600);
 file_put_contents($bundle.'/state.tar',str_repeat("\0",1024));chmod($bundle.'/state.tar',0600);
 $manifest=['formatVersion'=>'fmonitor-runtime-backup-v1','createdAtUtc'=>'2026-11-16T00:00:00Z','sourceCommit'=>str_repeat('a',40),'imageReference'=>'fmonitor2-runtime:fixture','imageId'=>'sha256:'.str_repeat('b',64),'database'=>['name'=>'source','schemaVersion'=>43,'tables'=>RuntimeRecoverySchemaV43::tables($prefix),'byteSize'=>filesize($bundle.'/database.sql'),'sha256'=>hash_file('sha256',$bundle.'/database.sql'),'autoIncrement'=>array_map(static fn(string$t):array=>['table'=>$t,'nextValue'=>1],RuntimeRecoverySchemaV43::autoIncrement($prefix))],'state'=>['byteSize'=>1024,'sha256'=>hash_file('sha256',$bundle.'/state.tar'),'members'=>[]],'deferred'=>[],'policyDecisions'=>['RETENTION_NEEDS_GRILL','RPO_NEEDS_GRILL','RTO_NEEDS_GRILL']];
 $write($manifest);$before=hash_file('sha256',$bundle.'/manifest.json');
 $result=$verify($argv);
 $expect($result===['exitCode'=>0,'result'=>['ok'=>true,'reason'=>'BUNDLE_VERIFIED']],'public verify validates canonical bundle without connecting to unreachable DB');
 $expect(!file_exists($root.'/absent-state')&&!file_exists($root.'/absent-secrets'),'verify creates no target storage');
 $expect($before===hash_file('sha256',$bundle.'/manifest.json'),'verify does not change source');
 foreach(['schema','tables','auto','size','deferred','keys']as$bad){
  $m=$manifest;
  match($bad){'schema'=>$m['database']['schemaVersion']=38,'tables'=>$m['database']['tables'][]='pf_unknown','auto'=>$m['database']['autoIncrement'][0]['nextValue']=0,'size'=>$m['state']['byteSize']=1,'deferred'=>$m['deferred']=['jobs'],'keys'=>$m['extra']='unknown'};
  $write($m);$r=$verify($argv);$expect($r['exitCode']===67&&$r['result']['reason']==='BUNDLE_INVALID','semantic manifest invalid before DB: '.$bad);
 }
 $write($manifest);$wrong=$argv;$wrong[4]=str_repeat('c',40);$expect($verify($wrong)['exitCode']===67,'source mismatch rejected');
 foreach(['traversal','symlink','hardlink','duplicate','unsafe-mode']as$mode){
  $cmd=[PHP_BINARY,__DIR__.'/runtime_recovery_tar_fixture.php',$mode,$bundle.'/state.tar'];
  $proc=proc_open($cmd,[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>STDERR],$pipes);$expect(is_resource($proc)&&proc_close($proc)===0,'tar fixture constructed');
  clearstatcache();$m=$manifest;$m['state']['byteSize']=filesize($bundle.'/state.tar');$m['state']['sha256']=hash_file('sha256',$bundle.'/state.tar');$write($m);
  $expect($verify($argv)['exitCode']===67,'semantically invalid tar rejected even with correct hash: '.$mode);
 }
 $expect(!file_exists($root.'/absent-state')&&!file_exists($root.'/absent-secrets'),'all verify failures have no target effects');
 echo "PASS PRODUCTION-MAKE-ENV-001 canonical verify read-only and malicious archive rejection\n";
}finally{foreach(glob($bundle.'/*')?:[]as$f)unlink($f);rmdir($bundle);rmdir($root);}
