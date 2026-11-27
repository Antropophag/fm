<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__,2).'/vendor/autoload.php';
use FMonitor2\AddressRegister\Search;
// FLEXIBLE-ADDRESS-SEARCH-001 A1-A5/A7/A8: public search seam, independent corpus.
assertSameValue(true,class_exists(Search::class),'INTENDED_RED common address search absent');
$corpus=json_decode(file_get_contents(__DIR__.'/corpus.json'),true,flags:JSON_THROW_ON_ERROR);
foreach($corpus['cases']as$case)assertSameValue($case['ids'],Search::ids($corpus['records'],$case['query']),'INTENDED_RED address corpus '.$case['query']);
$before=$corpus['records'];Search::ids($corpus['records'],'д12 к2 п3');assertSameValue($before,$corpus['records'],'search does not mutate caller data');
$many=[];for($i=1;$i<=10000;$i++)$many[]=['id'=>$i,'address'=>'ул. Берёзовая, д. '.$i.', корп. 2','entrance'=>'3','extra'=>['REG-'.$i]];
$started=microtime(true);$ids=Search::ids($many,'березовя д9999 к2 п3');$elapsed=microtime(true)-$started;
assertSameValue([9999],$ids,'no silent candidate truncation');
echo 'BENCHMARK10000 seconds='.round($elapsed,3).' peakMiB='.round(memory_get_peak_usage(true)/1048576,2)."\n";
assertSameValue(true,$elapsed<5,'bounded light projection search below five seconds on local/CI runtime');
$command=['node',__DIR__.'/offline_search.cjs'];$pipes=[];$p=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);assertSameValue([0,''],[proc_close($p),$err],'offline parity '.$out);echo$out;
echo "PASS: FLEXIBLE-ADDRESS-SEARCH-001 corpus, parity, bounded search\n";
