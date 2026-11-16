<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\AddressRegister\ColumnPreferences;
use FMonitor2\AddressRegister\PreferenceConflict;
// ADDRESS-REGISTER-001 A4,A6,A7: public seam; independently authored expected values.
assertSameValue(true,class_exists(ColumnPreferences::class),'INTENDED_RED missing personal column preference owner');
$root=sys_get_temp_dir().'/address-preferences-'.bin2hex(random_bytes(8));
$auth=static fn(int $id):bool=>in_array($id,[101,202],true);
function rejects(string $class,callable $fn,string $why):void {try{$fn();}catch(Throwable $e){assertSameValue(true,$e instanceof $class,$why.' class '.get_class($e));return;}throw new TestFailure($why.' was accepted');}
function snapshot(string $root):array{$r=[];if(!is_dir($root))return$r;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as$f)if($f->isFile())$r[substr($f->getPathname(),strlen($root))]=hash_file('sha256',$f->getPathname());ksort($r);return$r;}
try {
 $store=new ColumnPreferences($root,$auth);$initial=$store->read(101);
 assertSameValue(0,$initial['revision'],'initial revision');
 assertSameValue(['registrationNumber','address','entrance','factoryNumber','status','completionProgress','plannedStartDate','plannedFinishDate','actualStartDate','actualFinishDate','controlEngineer','installers','floors','weight','speed','lift_type','pittype','pitmaterial','paired','ptoDate','declarationDate','comment'],$initial['visible'],'useful confirmed default');
 assertSameValue(['revision','visible','layoutVersion'],array_keys($initial),'width-free public state');assertSameValue(2,$initial['layoutVersion'],'adaptive layout version');
 rejects(DomainException::class,fn()=>$store->read(303),'denied read');
 $visible=['registrationNumber','address','factoryNumber','comment'];
 $saved=$store->save(101,0,$visible);
 assertSameValue(['revision'=>1,'visible'=>$visible,'layoutVersion'=>2],$saved,'exact first snapshot');
 assertSameValue($saved,(new ColumnPreferences($root,$auth))->read(101),'new instance same account');
 assertSameValue($initial,$store->read(202),'account isolation');$before=snapshot($root);
 assertSameValue($saved,$store->save(101,1,$visible),'no-op replay does not advance');
 rejects(PreferenceConflict::class,fn()=>$store->save(101,0,$visible),'stale tab');
 rejects(DomainException::class,fn()=>$store->save(303,0,$visible),'denied write');
 foreach([[],['comment'],['address','registrationNumber'],['registrationNumber','registrationNumber'],['registrationNumber','secret'],['registrationNumber',17]]as$bad)rejects(InvalidArgumentException::class,fn()=>$store->save(101,1,$bad),'invalid columns');
 rejects(InvalidArgumentException::class,fn()=>$store->save(101,-1,$visible),'negative revision');
 assertSameValue($saved,$store->read(101),'all rejections preserve view');assertSameValue($before,snapshot($root),'all rejections/no-op preserve file bytes');
 $next=$store->save(101,1,['registrationNumber','comment']);assertSameValue(2,$next['revision'],'second revision');
 $after=snapshot($root);foreach($before as$path=>$hash)assertSameValue($hash,$after[$path]??null,'prior immutable artifact '.$path);
 $revoked=new ColumnPreferences($root,static fn(int$id):bool=>false);rejects(DomainException::class,fn()=>$revoked->read(101),'revoked read');rejects(DomainException::class,fn()=>$revoked->save(101,2,$visible),'revoked save');
 // A competing writer carrying the same revision must lose without changing the winner.
 $other=new ColumnPreferences($root,$auth);rejects(PreferenceConflict::class,fn()=>$other->save(101,1,$visible),'second writer loses CAS');assertSameValue($next,$store->read(101),'winner retained');
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as$f)if($f->isFile())assertSameValue(0600,fileperms($f->getPathname())&0777,'private file mode');
 $link=$root.'-link';symlink($root,$link);rejects(RuntimeException::class,fn()=>(new ColumnPreferences($link,$auth))->read(101),'symlink root rejected');unlink($link);

 // Two separate OS processes race the first save of a fresh account.
 $children=[];$gate=$root.'/race-go';$autoload=dirname(__DIR__,2).'/app/autoload.php';
 foreach(['comment','factoryNumber']as$column){$code='require '.var_export($autoload,true).';while(!file_exists('.var_export($gate,true).'))usleep(1000);$s=new FMonitor2\\AddressRegister\\ColumnPreferences('.var_export($root,true).',fn(int$id)=>true);try{$v=$s->save(202,0,["registrationNumber",'.var_export($column,true).']);echo "saved";}catch(FMonitor2\\AddressRegister\\PreferenceConflict){echo "conflict";}';$proc=proc_open([PHP_BINARY,'-r',$code],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);assertSameValue(true,is_resource($proc),'race child spawn');$children[]=[$proc,$pipes];}
 file_put_contents($gate,'go');chmod($gate,0600);$results=[];foreach($children as[$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);assertSameValue(0,$exit,'race child '.$err);$results[]=$out;}sort($results);assertSameValue(['conflict','saved'],$results,'concurrent first saves exactly one winner');unlink($gate);assertSameValue(1,$store->read(202)['revision'],'one published revision');
 // Corrupt the latest accepted JSON with valid JSON but wrong field types.
 $latest=null;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as$f)if($f->isFile()&&str_ends_with($f->getFilename(),'.json')){$v=json_decode(file_get_contents($f->getPathname()),true);if(is_array($v)&&($v['revision']??null)===2)$latest=$f->getPathname();}
 assertSameValue(true,is_string($latest),'accepted history retained');$bytes=file_get_contents($latest);file_put_contents($latest,'{"revision":2,"visible":"bad","layoutVersion":2}');rejects(RuntimeException::class,fn()=>$store->read(101),'typed corruption fails closed');file_put_contents($latest,$bytes);assertSameValue($next,$store->read(101),'restored durable snapshot readable');

 // A previous deployment's immutable pixel layout remains intact on read.
 $legacyRoot=$root.'/legacy';mkdir($legacyRoot,0700);mkdir($legacyRoot.'/user-101',0700);
 $oldKeys=['address','entrance','registrationNumber','factoryNumber','status','completionProgress','plannedStartDate','plannedFinishDate','actualStartDate','actualFinishDate','controlEngineer','installers','floors','weight','speed','lift_type','pittype','pitmaterial','paired','ptoDate','declarationDate','comment'];
 $old=['revision'=>7,'visible'=>['address','comment'],'widths'=>array_fill_keys($oldKeys,200)];$oldFile=$legacyRoot.'/user-101/revision-0000000007.json';file_put_contents($oldFile,json_encode($old));chmod($oldFile,0600);$oldHash=hash_file('sha256',$oldFile);
 $upgrade=new ColumnPreferences($legacyRoot,$auth);$projected=$upgrade->read(101);assertSameValue(['revision'=>7,'visible'=>$initial['visible'],'layoutVersion'=>2],$projected,'old users see complete adaptive layout');assertSameValue($oldHash,hash_file('sha256',$oldFile),'compatibility read never rewrites history');assertSameValue(1,count(glob($legacyRoot.'/user-101/*.json')),'no read-time upgrade write');
 $confirmed=$upgrade->save(101,7,$projected['visible']);assertSameValue(8,$confirmed['revision'],'explicit save appends adaptive version');assertSameValue($confirmed,$upgrade->save(101,8,$confirmed['visible']),'v2 same save is no-op');assertSameValue($oldHash,hash_file('sha256',$oldFile),'old pixel snapshot retained');assertSameValue(false,array_key_exists('widths',$confirmed),'new state has no pixel map');
 $personal=$upgrade->save(101,8,['registrationNumber','installers','address']);assertSameValue($personal,(new ColumnPreferences($legacyRoot,$auth))->read(101),'new personal selection survives reload');
 // An invalid legacy record must not silently become a reset/default layout.
 $badRoot=$root.'/bad-legacy';mkdir($badRoot,0700);mkdir($badRoot.'/user-101',0700);$old['widths']['address']=9000;file_put_contents($badRoot.'/user-101/revision-0000000007.json',json_encode($old));rejects(RuntimeException::class,fn()=>(new ColumnPreferences($badRoot,$auth))->read(101),'corrupt legacy fail closed');
 // Closed-format and filesystem corruption matrix: reads AND writes must fail without changing artifacts.
 $v2=['revision'=>7,'visible'=>['registrationNumber','comment'],'layoutVersion'=>2];
 $v1=['revision'=>7,'visible'=>['address','comment'],'widths'=>array_fill_keys($oldKeys,200)];
 $cases=[
  'v2-extra'=>$v2+['widths'=>[]], 'v2-missing'=>array_diff_key($v2,['layoutVersion'=>true]),
  'v2-version'=>array_replace($v2,['layoutVersion'=>3]), 'v2-revision'=>array_replace($v2,['revision'=>6]),
  'v2-string-revision'=>array_replace($v2,['revision'=>'7']),
  'v2-order'=>array_replace($v2,['visible'=>['address','registrationNumber']]),
  'v2-duplicate'=>array_replace($v2,['visible'=>['registrationNumber','comment','comment']]),
  'v2-unknown'=>array_replace($v2,['visible'=>['registrationNumber','secret']]),
  'v1-missing-width'=>array_replace($v1,['widths'=>array_diff_key($v1['widths'],['address'=>true])]),
  'v1-extra-width'=>array_replace($v1,['widths'=>$v1['widths']+['secret'=>200]]),
  'v1-string-width'=>array_replace($v1,['widths'=>array_replace($v1['widths'],['address'=>'200'])]),
  'v1-order'=>array_replace($v1,['visible'=>['comment','address']]),
  'v1-duplicate'=>array_replace($v1,['visible'=>['address','address']]),
  'v1-unknown'=>array_replace($v1,['visible'=>['address','secret']]),
 ];
 foreach($cases as$name=>$value){
  $dir=$root.'/matrix-'.$name;mkdir($dir,0700);mkdir($dir.'/user-101',0700);
  file_put_contents($dir.'/user-101/.lock','');file_put_contents($dir.'/user-101/revision-0000000007.json',json_encode($value));
  $bad=new ColumnPreferences($dir,$auth);$unchanged=snapshot($dir);
  rejects(RuntimeException::class,fn()=>$bad->read(101),$name.' read');
  rejects(RuntimeException::class,fn()=>$bad->save(101,7,['registrationNumber']),$name.' save');
  assertSameValue($unchanged,snapshot($dir),$name.' rejected without writes');
 }
 foreach(['oversize','snapshot-directory','snapshot-symlink','actor-file','actor-symlink','lock-directory','lock-symlink']as$name){
  $dir=$root.'/fs-'.$name;mkdir($dir,0700);$actor=$dir.'/user-101';$target=$dir.'/target';file_put_contents($target,'untouched');
  if($name==='actor-file')file_put_contents($actor,'invalid');
  elseif($name==='actor-symlink')symlink($legacyRoot.'/user-101',$actor);
  else{mkdir($actor,0700);$file=$actor.'/revision-0000000007.json';
   if($name==='oversize')file_put_contents($file,str_repeat(' ',65537));
   elseif($name==='snapshot-directory')mkdir($file,0700);
   elseif($name==='snapshot-symlink')symlink($target,$file);
   if($name==='lock-directory')mkdir($actor.'/.lock',0700);
   elseif($name==='lock-symlink')symlink($target,$actor.'/.lock');
   else file_put_contents($actor.'/.lock','');
  }
  $bad=new ColumnPreferences($dir,$auth);$unchanged=snapshot($dir);
  rejects(RuntimeException::class,fn()=>$bad->read(101),$name.' read');
  rejects(RuntimeException::class,fn()=>$bad->save(101,7,['registrationNumber']),$name.' save');
  assertSameValue($unchanged,snapshot($dir),$name.' preserves bytes');
  assertSameValue('untouched',file_get_contents($target),$name.' external target intact');
 }
 echo "PASS: ADDRESS-REGISTER-001 preferences isolation, CAS, validation, persistence, history\n";
}finally{if(is_dir($root)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);}}
