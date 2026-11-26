<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
$root=dirname(__DIR__,2);$cli=$root.'/bin/fmonitor2-address-register.php';
assertSameValue(true,is_file($cli),'INTENDED_RED ADDRESS-REGISTER-IMPORT-001 A01 CLI exists');
foreach([[],['unknown'],['preview','--file=x','--year=2027','--contract=T','--target=dev','--actor=18'],['apply','--package=invalid'],['preview','--file=x','--year=2027','--year=2028','--contract=T','--target=dev']]as$args){
 $env=getenv();foreach(array_keys($env)as$key)if(str_starts_with($key,'FMONITOR_'))unset($env[$key]);
 $p=proc_open([PHP_BINARY,$cli,...$args],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$env);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
 assertSameValue(64,$code,'invalid CLI exit');$r=json_decode($out,true,flags:JSON_THROW_ON_ERROR);assertSameValue('CONFIGURATION_INVALID',$r['reasonCode'],'stable error');assertSameValue('',trim($err),'no stack trace');
}
echo "PASS ADDRESS-REGISTER-IMPORT-001 A01 CLI\n";
