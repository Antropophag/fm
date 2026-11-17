<?php
declare(strict_types=1);
// PRODUCTION-HANDOFF-001 H05: public real transport boundary, zero domain DB dependency.
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__,2).'/app/autoload.php';
use FMonitor2\InstallationProcess\{NativeErpEquipmentFactsDelivery,ErpEquipmentFactsDeliveryConfig};
$config=new ErpEquipmentFactsDeliveryConfig('fixture','fixture','reader','synthetic-private-value',500,7,100);
assertSameValue(true,method_exists(NativeErpEquipmentFactsDelivery::class,'probe'),'INTENDED_RED: public ERP connectivity probe required');
$calls=[];
$transport=static function(string$sql,array$params,array$options)use(&$calls):array{
    $calls[]=[$sql,$params,$options];return [['available'=>1]];
};
$result=(new NativeErpEquipmentFactsDelivery($config,$transport))->probe();
assertSameValue('completed',$result['status']??null,'read-only connectivity succeeds without local objects');
assertSameValue(1,count($calls),'one bounded probe even when object catalogue absent');
assertSameValue('SELECT 1 AS available',$calls[0][0],'probe never scans source catalogue');
assertSameValue([],$calls[0][1],'probe takes no object identifiers');
assertSameValue(['maxRows'=>1,'timeoutSeconds'=>7,'readOnly'=>true],$calls[0][2],'bounded read-only transport');
foreach([1,'1']as$value){
    $r=(new NativeErpEquipmentFactsDelivery($config,static fn():array=>[['available'=>$value]]))->probe();
    assertSameValue('completed',$r['status']??null,'PDO SQL Server integer or string one qualifies connectivity');
}
foreach([0,'0',2,'2',true,false,1.0,null,'','01','1.0','1e0',' 1','1 ',[],[1]]as$value){
    $r=(new NativeErpEquipmentFactsDelivery($config,static fn():array=>[['available'=>$value]]))->probe();
    assertSameValue('failed',$r['status']??null,'probe rejects values other than exact integer or string one');
}
foreach([[],[1],['invalid'],[[]],[['available'=>0]],[['unexpected'=>1]],[['available'=>1,'extra'=>1]],[['available'=>'1','extra'=>1]],[['available'=>1],['available'=>1]],[['available'=>'1'],['available'=>'1']]]as$rows){
    $r=(new NativeErpEquipmentFactsDelivery($config,static fn():array=>$rows))->probe();
    assertSameValue('failed',$r['status']??null,'invalid probe response cannot qualify connection');
}
$r=(new NativeErpEquipmentFactsDelivery($config,static function():array{throw new RuntimeException('synthetic-private-value DSN SQL');}))->probe();
assertSameValue('failed',$r['status']??null,'transport errors fail closed');
assertSameValue(false,str_contains(json_encode($r),'synthetic-private-value'),'no exception details in receipt');
$controller=file_get_contents(dirname(__DIR__,2).'/app/YiiRuntime/Commands/ErpEquipmentFactsSyncController.php');
assertSameValue(true,str_contains($controller,'actionProbe'),'probe reachable through public Yii console command');
assertSameValue(true,str_contains($controller,'->probe()'),'real command invokes tested transport probe');
$transportSource=file_get_contents(dirname(__DIR__,2).'/app/InstallationProcess/MariaDbSqlServerEquipmentFactsTransport.php');
assertSameValue(true,str_contains($transportSource,'LoginTimeout'),'SQL connection timeout is bounded as well as query timeout');
$root=dirname(__DIR__,2);$temporary=sys_get_temp_dir().'/fm2-probe-'.bin2hex(random_bytes(8));
mkdir($temporary.'/app/YiiRuntime/Commands',0700,true);mkdir($temporary.'/vendor',0700);
file_put_contents($temporary.'/vendor/autoload.php',"<?php\n");
$copied=$temporary.'/app/YiiRuntime/Commands/ErpEquipmentFactsSyncController.php';
copy($root.'/app/YiiRuntime/Commands/ErpEquipmentFactsSyncController.php',$copied);
try{
    foreach(['success','string-success','malformed','connect-failure','query-failure']as$mode){
        $trace=$temporary.'/trace';@unlink($trace);
        $process=proc_open([PHP_BINARY,$root.'/tests/Support/erp_probe_controller.php',$root,$copied,$mode,$trace],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
        $success=in_array($mode,['success','string-success'],true);
        assertSameValue($success, $exit===0,'public controller exit matches actual probe outcome '.$mode);
        assertSameValue(false,str_contains($stdout.$stderr,'synthetic-private-value'),'public controller suppresses private errors '.$mode);
        $out=json_decode($stdout,true,8,JSON_THROW_ON_ERROR);
        assertSameValue($success?'completed':'failed',$out['status']??null,'public command produces safe structured status '.$mode);
        assertSameValue('', $stderr, 'public command emits no raw adapter diagnostic '.$mode);
        $observed=array_map(static fn(string$line):array=>json_decode($line,true,8,JSON_THROW_ON_ERROR),file($trace,FILE_IGNORE_NEW_LINES));
        assertSameValue(['host'=>'fixture-host','database'=>'fixture-db','user'=>'reader','timeout'=>7],$observed[0],'controller composes configured real transport boundary');
        assertSameValue($mode==='connect-failure'?1:2,count($observed),'no accidental local DB dependency or repeated requests');
        if($mode!=='connect-failure')assertSameValue(['SELECT 1 AS available',[],['maxRows'=>1,'timeoutSeconds'=>7,'readOnly'=>true]],$observed[1],'controller reaches bounded tested query');
    }
}finally{
    @unlink($temporary.'/trace');unlink($copied);unlink($temporary.'/vendor/autoload.php');
    rmdir($temporary.'/app/YiiRuntime/Commands');rmdir($temporary.'/app/YiiRuntime');rmdir($temporary.'/app');rmdir($temporary.'/vendor');rmdir($temporary);
}
echo "PASS PRODUCTION-HANDOFF-001 ERP connectivity probe\n";
