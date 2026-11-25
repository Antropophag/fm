<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\Otiz\ContractualReplacementPeriod as Period;
use FMonitor2\InstallationProcess\ObjectDetailsFieldRegistry;

assertSameValue(true, class_exists(Period::class), 'INTENDED_RED: contractual inclusive period owner');
foreach ([['2026-12-01','2026-12-20',20],['2026-12-01','2026-12-01',1],['2028-02-28','2028-03-01',3],['2026-12-31','2027-01-01',2]] as [$start,$end,$days]) {
    assertSameValue($days, Period::fromContractDates($start,$end), 'inclusive contract '.$start);
}
foreach ([['2026-12-10',20,'2026-12-29'],['2028-02-28',3,'2028-03-01'],['2026-12-31',2,'2027-01-01'],['2026-12-10',1,'2026-12-10']] as [$start,$days,$end]) {
    assertSameValue($end, Period::allowanceFinish($start,$days), 'actual start grants full allowance');
}
foreach ([['2026-02-30','2026-03-20'],['2026-12-20','2026-12-01'],['','2026-12-01']] as [$start,$end]) {
    $denied=false; try { Period::fromContractDates($start,$end); } catch (InvalidArgumentException|DomainException) { $denied=true; }
    assertSameValue(true,$denied,'invalid contractual interval rejected');
}
foreach ([0,-1] as $days) {
    $denied=false;try { Period::allowanceFinish('2026-12-10',$days); } catch (InvalidArgumentException|DomainException) {$denied=true;}
    assertSameValue(true,$denied,'nonpositive allowance rejected');
}
$registry=new ObjectDetailsFieldRegistry();
assertSameValue(['stops'=>7,'floors'=>12],$registry->normalizePatch(['stops'=>'7','floors'=>'12']),'independent editable counts');
foreach (['','0','-1','1.5','seven'] as $bad) {
    $denied=false;try {$registry->normalizePatch(['stops'=>$bad]);} catch (InvalidArgumentException) {$denied=true;}
    assertSameValue(true,$denied,'invalid stops rejected');
}
echo "PASS MIKRYUKOVA-FEEDBACK-001 contractual period and stop validation\n";
