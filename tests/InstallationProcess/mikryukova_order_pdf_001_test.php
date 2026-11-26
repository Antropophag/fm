<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
$input=['assignmentOrderId'=>10001,'appendixStartDate'=>'2026-09-11','assignmentOrderVersion'=>1,'assignmentOrderDate'=>'2026-09-01','organizationType'=>'individual','installationObjectSnapshot'=>['address'=>'Тестовая улица, 1','entrance'=>'2','objectRegistrationNumber'=>'TEST-4512','plannedStartDate'=>'2026-09-10','plannedFinishDate'=>'2099-01-01','contractStartDate'=>'2026-09-01','contractFinishDate'=>'2026-09-20','normativePeriodDays'=>20,'actualStartDate'=>'2026-09-12'],'installers'=>[['tabId'=>7001,'fullName'=>'Иванов Иван Иванович','position'=>'Монтажник']],'controlEngineer'=>['userId'=>73,'fullName'=>'Петров Пётр Петрович','position'=>'Инженер']];
$artifacts=(new \FMonitor2\InstallationProcess\ProductionPdfAssignmentOrderRenderer())->renderAssignmentOrder($input);
preg_match_all('/stream\R(.*?)\Rendstream/s',$artifacts[0]['bytes'],$matches);$bytes='';foreach($matches[1]as$s){$d=@gzuncompress($s);$bytes.=is_string($d)?$d:$s;}
// Owner correction25.11: normative calculation belongs to the card, never the order template.
foreach(['Нормативный период','20 календарных дней','Начало по договору','Договорный норматив','Фактическое начало']as$marker){$needle=mb_convert_encoding($marker,'UTF-16BE','UTF-8');assertSameValue(false,str_contains($bytes,$needle),'PDF omits unrequested normative annotation: '.$marker);}
foreach(['ФН10001','Фактическая','10.09.2026','11.09.2026','01.01.2099','Иванов Иван Иванович','TEST-4512']as$marker){$needle=mb_convert_encoding($marker,'UTF-16BE','UTF-8');assertSameValue(true,str_contains($bytes,$needle),'order and appendix retain '.$marker);}
assertSameValue(true,str_contains($bytes,mb_convert_encoding('Приложение 1 к распоряжению № ____________','UTF-16BE','UTF-8')),'appendix reserves handwritten external document number');
assertSameValue(false,str_contains($bytes,mb_convert_encoding('Приложение 1 к распоряжению № ФН','UTF-16BE','UTF-8')),'internal number is not the official appendix number');
assertSameValue(true,str_contains($bytes,mb_convert_encoding('FMonitor · ФН10001','UTF-16BE','UTF-8')),'internal number remains as unobtrusive service identification');
$dateOffsets=array_map(static fn(string$date):int|false=>strpos($bytes,mb_convert_encoding($date,'UTF-16BE','UTF-8')),['10.09.2026','11.09.2026','01.01.2099']);
assertSameValue(true,$dateOffsets[0]!==false&&$dateOffsets[1]!==false&&$dateOffsets[2]!==false&&$dateOffsets[0]<$dateOffsets[1]&&$dateOffsets[1]<$dateOffsets[2],'planned start, selected actual start, finish are separate columns in order');
assertSameValue(false,str_contains($bytes,mb_convert_encoding('12.09.2026','UTF-16BE','UTF-8')),'new column uses template choice, not other historical start');
$htmlInput=$input;$htmlInput['installers'][0]+=['status'=>'employed','source'=>'test'];
$html=(new \FMonitor2\InstallationProcess\ProductionHtmlAssignmentOrderRenderer())->renderAssignmentOrder($htmlInput)[1]['bytes'];
assertSameValue(true,str_contains($html,'<th>Дата начала работ</th><th>Фактическая дата начала работ</th><th>Дата завершения работ</th>'),'HTML dates use same column order');
assertSameValue(true,str_contains($html,'<td>10.09.2026</td><td>11.09.2026</td><td>01.01.2099</td>'),'HTML separates planned and selected dates');
if($preview=getenv('FMONITOR_PDF_PREVIEW'))file_put_contents($preview,$artifacts[0]['bytes']);
echo "PASS ORDER-LOCAL-PREVIEW-001 PDF separates planned and selected actual start\n";
