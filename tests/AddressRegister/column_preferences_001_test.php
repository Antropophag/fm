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
 assertSameValue(['address','entrance','registrationNumber','status','completionProgress','plannedStartDate','plannedFinishDate','actualStartDate','actualFinishDate','controlEngineer','installers'],$initial['visible'],'useful confirmed default');
 assertSameValue(300,$initial['widths']['address'],'default address width');
 rejects(DomainException::class,fn()=>$store->read(303),'denied read');
 $visible=['address','registrationNumber','factoryNumber','comment'];$widths=$initial['widths'];$widths['address']=480;$widths['comment']=640;
 $saved=$store->save(101,0,$visible,$widths);
 assertSameValue(['revision'=>1,'visible'=>$visible,'widths'=>$widths],$saved,'exact first snapshot');
 assertSameValue($saved,(new ColumnPreferences($root,$auth))->read(101),'new instance same account');
 assertSameValue($initial,$store->read(202),'account isolation');$before=snapshot($root);
 assertSameValue($saved,$store->save(101,1,$visible,$widths),'no-op replay does not advance');
 rejects(PreferenceConflict::class,fn()=>$store->save(101,0,$visible,$widths),'stale tab');
 rejects(DomainException::class,fn()=>$store->save(303,0,$visible,$widths),'denied write');
 foreach([[],['comment'],['comment','address'],['address','address'],['address','secret'],['address',17]]as$bad)rejects(InvalidArgumentException::class,fn()=>$store->save(101,1,$bad,$widths),'invalid columns');
 foreach([79,641,100.5,'120',null]as$bad){$badWidths=$widths;$badWidths['address']=$bad;rejects(InvalidArgumentException::class,fn()=>$store->save(101,1,$visible,$badWidths),'invalid width');}
 $badWidths=$widths;$badWidths['secret']=120;rejects(InvalidArgumentException::class,fn()=>$store->save(101,1,$visible,$badWidths),'unknown width key');
 rejects(InvalidArgumentException::class,fn()=>$store->save(101,-1,$visible,$widths),'negative revision');
 assertSameValue($saved,$store->read(101),'all rejections preserve view');assertSameValue($before,snapshot($root),'all rejections/no-op preserve file bytes');
 $next=$store->save(101,1,['address','comment'],$widths);assertSameValue(2,$next['revision'],'second revision');
 $after=snapshot($root);foreach($before as$path=>$hash)assertSameValue($hash,$after[$path]??null,'prior immutable artifact '.$path);
 $revoked=new ColumnPreferences($root,static fn(int$id):bool=>false);rejects(DomainException::class,fn()=>$revoked->read(101),'revoked read');rejects(DomainException::class,fn()=>$revoked->save(101,2,$visible,$widths),'revoked save');
 // A competing writer carrying the same revision must lose without changing the winner.
 $other=new ColumnPreferences($root,$auth);rejects(PreferenceConflict::class,fn()=>$other->save(101,1,$visible,$widths),'second writer loses CAS');assertSameValue($next,$store->read(101),'winner retained');
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as$f)if($f->isFile())assertSameValue(0600,fileperms($f->getPathname())&0777,'private file mode');
 $link=$root.'-link';symlink($root,$link);rejects(RuntimeException::class,fn()=>(new ColumnPreferences($link,$auth))->read(101),'symlink root rejected');unlink($link);

 // Two separate OS processes race the first save of a fresh account.
 $children=[];$gate=$root.'/race-go';$autoload=dirname(__DIR__,2).'/app/autoload.php';
 foreach(['comment','factoryNumber']as$column){$code='require '.var_export($autoload,true).';while(!file_exists('.var_export($gate,true).'))usleep(1000);$s=new FMonitor2\\AddressRegister\\ColumnPreferences('.var_export($root,true).',fn(int$id)=>true);try{$v=$s->save(202,0,["address",'.var_export($column,true).'],FMonitor2\\AddressRegister\\Columns::defaults()["widths"]);echo "saved";}catch(FMonitor2\\AddressRegister\\PreferenceConflict){echo "conflict";}';$proc=proc_open([PHP_BINARY,'-r',$code],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);assertSameValue(true,is_resource($proc),'race child spawn');$children[]=[$proc,$pipes];}
 file_put_contents($gate,'go');chmod($gate,0600);$results=[];foreach($children as[$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);assertSameValue(0,$exit,'race child '.$err);$results[]=$out;}sort($results);assertSameValue(['conflict','saved'],$results,'concurrent first saves exactly one winner');unlink($gate);assertSameValue(1,$store->read(202)['revision'],'one published revision');
 // Corrupt the latest accepted JSON with valid JSON but wrong field types.
 $latest=null;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS))as$f)if($f->isFile()&&str_ends_with($f->getFilename(),'.json')){$v=json_decode(file_get_contents($f->getPathname()),true);if(is_array($v)&&($v['revision']??null)===2)$latest=$f->getPathname();}
 assertSameValue(true,is_string($latest),'accepted history retained');$bytes=file_get_contents($latest);file_put_contents($latest,'{"revision":2,"visible":"bad","widths":[]}');rejects(RuntimeException::class,fn()=>$store->read(101),'typed corruption fails closed');file_put_contents($latest,$bytes);assertSameValue($next,$store->read(101),'restored durable snapshot readable');
 echo "PASS: ADDRESS-REGISTER-001 preferences isolation, CAS, validation, persistence, history\n";
}finally{if(is_dir($root)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);}}
