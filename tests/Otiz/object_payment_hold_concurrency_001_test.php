<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\Tests\Support\PaymentHoldFixture;
$f=null;$workers=[];$dir=sys_get_temp_dir().'/hold-race-'.bin2hex(random_bytes(6));mkdir($dir,0700);
try {
 $f=new PaymentHoldFixture();$s=$f->owner;assertSameValue(true,method_exists($s,'holdObjectPayments'),'INTENDED_RED: partial payment concurrency owner');
 $a=$f->accepted();$s->holdObjectPayments(9101,4513,0,'ФКР',$f->op());
 $tx=$f->db->beginTransaction();$f->db->createCommand("SELECT id FROM {$f->p}fm2_otiz_calculation_revisions WHERE id=:id FOR UPDATE",[':id'=>$a['calculationId']])->queryScalar();
 for($i=0;$i<2;$i++){
  $c=['dsn'=>$f->db->dsn,'prefix'=>$f->p,'actor'=>9101+$i,'id'=>$a['calculationId'],'revision'=>$a['revision'],'operation'=>$f->op(),'ready'=>$dir.'/ready'.$i,'go'=>$dir.'/go'];$file=$dir.'/worker'.$i.'.json';file_put_contents($file,json_encode($c));chmod($file,0600);$pipes=[];$proc=proc_open([PHP_BINARY,dirname(__DIR__).'/Support/object_payment_hold_worker.php',$file],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));$workers[]=[$proc,$pipes];
 }
 $end=microtime(true)+15;while(!is_file($dir.'/ready0')||!is_file($dir.'/ready1')){if(microtime(true)>$end)throw new TestFailure('workers readiness timeout');usleep(10000);}touch($dir.'/go');usleep(150000);$tx->commit();
 $results=[];foreach($workers as[$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);assertSameValue(0,proc_close($proc),'worker completed '.$err);$results[]=json_decode($out,true,flags:JSON_THROW_ON_ERROR);}$workers=[];
 assertSameValue(1,$f->count('fm2_otiz_payment_facts'),'concurrent commands create one partial payment');
 assertSameValue(600000,(int)$f->db->createCommand("SELECT SUM(amount_cents) FROM {$f->p}fm2_otiz_payment_allocations")->queryScalar(),'concurrency cannot overpay');
 assertSameValue(1,count(array_filter($results,fn($r)=>($r['paidCents']??0)===600000)),'only one worker reports real batch');
 assertSameValue(400000,$s->paymentPlan(9101,$a['calculationId'])['heldCents'],'held amount untouched');
 $s->resumeObjectPayments(9101,4513,1,'ФКР: снято',$f->op());
 $tx=$f->db->beginTransaction();$s->holdObjectPayments(9101,4513,2,'ФКР: повторная проверка',$f->op());
 $c=['dsn'=>$f->db->dsn,'prefix'=>$f->p,'actor'=>9102,'id'=>$a['calculationId'],'revision'=>$a['revision'],'operation'=>$f->op(),'ready'=>$dir.'/hold-ready','go'=>$dir.'/hold-go'];$file=$dir.'/hold-worker.json';file_put_contents($file,json_encode($c));chmod($file,0600);$pipes=[];$proc=proc_open([PHP_BINARY,dirname(__DIR__).'/Support/object_payment_hold_worker.php',$file],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));$workers[]=[$proc,$pipes];
 $end=microtime(true)+15;while(!is_file($c['ready'])){if(microtime(true)>$end)throw new TestFailure('hold/pay worker timeout');usleep(10000);}touch($c['go']);usleep(150000);
 assertSameValue(true,proc_get_status($proc)['running'],'other actor payment waits for in-flight object hold');$tx->commit();
 $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);assertSameValue(0,proc_close($proc),'hold/pay worker exits '.$err);$workers=[];
 assertSameValue('PAYMENT_HELD',json_decode($out,true,flags:JSON_THROW_ON_ERROR)['error']??null,'payment observes committed hold under shared lock');assertSameValue(1,$f->count('fm2_otiz_payment_facts'),'hold race adds no payment');
 echo "PASS MIKRYUKOVA-FEEDBACK-001 concurrent partial payment\n";
}finally{foreach($workers as[$proc,$pipes]){proc_terminate($proc);foreach($pipes as$pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}if($f!==null){if($f->db->getTransaction()?->isActive)$f->db->getTransaction()->rollBack();$f->close();}foreach(glob($dir.'/*')?:[]as$file)unlink($file);rmdir($dir);}
