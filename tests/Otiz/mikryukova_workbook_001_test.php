<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__,2).'/app/autoload.php';
use FMonitor2\Otiz\OtizSettlementV2Workbook;
$period=['contractStartDate'=>'2026-09-01','contractFinishDate'=>'2026-09-20','normDays'=>20,'actualStartDate'=>'2026-09-10','allowanceFinishDate'=>'2026-09-29'];
$source=['calculation'=>['id'=>1,'status'=>'draft','reportDate'=>'2026-10-03','revision'=>1,'rulesVersion'=>'otiz-v2','contentHash'=>str_repeat('a',64),'totalCents'=>100000],
 'objects'=>[['objectId'=>4512,'regnumber'=>'TEST-4512','address'=>'Тест','fundCents'=>100000,'plannedPremiumCents'=>100000,'grossCents'=>100000,'deadlineCents'=>0,'disciplineCents'=>0,'payableCents'=>100000,'shaftBp'=>10000,'confirmedBp'=>10000,'daysLate'=>0,'kssBp'=>10000,'sourceEvidence'=>['contractualPeriod'=>$period,'certificate'=>['newDeadline'=>'2026-10-02','sourceLocator'=>'fixture://fkr/certificate-4512']]]],
 'recipients'=>[['employeeId'=>'7001','objectId'=>4512,'tabNumber'=>'007001','name'=>'Тестовый монтажник','originalWeight'=>1,'amountCents'=>100000,'employment'=>'employed']], 'deductions'=>[],'decisions'=>[],'issues'=>[],'mode'=>'draft'];
$book=OtizSettlementV2Workbook::build($source);$file=tempnam(sys_get_temp_dir(),'mik-xlsx-');file_put_contents($file,$book['bytes']);
try{$zip=new ZipArchive();assertSameValue(true,$zip->open($file)===true,'valid workbook');$xml='';for($i=0;$i<$zip->numFiles;$i++)if(str_starts_with($zip->getNameIndex($i),'xl/worksheets/'))$xml.=$zip->getFromIndex($i);$zip->close();
 foreach(['Нормативный период','Начало по договору','Окончание по договору','Фактическое начало','Срок по нормативу','Срок по справке','Основание срока','Справка ФКР']as$label)assertSameValue(true,str_contains($xml,$label),'INTENDED_RED: output workbook explains '.$label);
 foreach([...array_values(array_filter($period,'is_string')),'2026-10-02']as$date){$serial=(new DateTimeImmutable('1899-12-30'))->diff(new DateTimeImmutable($date))->days;assertSameValue(true,str_contains($xml,$date)||str_contains($xml,'>'.$serial.'<'),'export contains exact date '.$date);}
 assertSameValue(true,str_contains($xml,'>20<'),'export retains normative days');
 echo "PASS MIKRYUKOVA-FEEDBACK-001 workbook period provenance\n";
}finally{unlink($file);}
