<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
$input=['assignmentOrderVersion'=>1,'assignmentOrderDate'=>'2026-09-01','organizationType'=>'individual','installationObjectSnapshot'=>['address'=>'Тестовая улица, 1','entrance'=>'2','objectRegistrationNumber'=>'TEST-4512','plannedStartDate'=>'2026-09-10','plannedFinishDate'=>'2099-01-01','contractStartDate'=>'2026-09-01','contractFinishDate'=>'2026-09-20','normativePeriodDays'=>20,'actualStartDate'=>'2026-09-10'],'installers'=>[['tabId'=>7001,'fullName'=>'Иванов Иван Иванович','position'=>'Монтажник']],'controlEngineer'=>['userId'=>73,'fullName'=>'Петров Пётр Петрович','position'=>'Инженер']];
$artifacts=(new \FMonitor2\InstallationProcess\ProductionPdfAssignmentOrderRenderer())->renderAssignmentOrder($input);
preg_match_all('/stream\R(.*?)\Rendstream/s',$artifacts[0]['bytes'],$matches);$bytes='';foreach($matches[1]as$s){$d=@gzuncompress($s);$bytes.=is_string($d)?$d:$s;}
foreach(['Нормативный период замены','20 календарных дней','01.09.2026','20.09.2026','10.09.2026','Договорный норматив']as$marker){$needle=mb_convert_encoding($marker,'UTF-16BE','UTF-8');assertSameValue(true,str_contains($bytes,$needle),'INTENDED_RED: PDF shows '.$marker);}
echo "PASS MIKRYUKOVA-FEEDBACK-001 PDF normative period\n";
