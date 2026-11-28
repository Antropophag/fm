<?php
/** OFFLINE-INDICATOR-001: real Yii view/response and browser presentation contract; no database. */
declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
require dirname(__DIR__,2).'/vendor/yiisoft/yii2/Yii.php';
$root=dirname(__DIR__,2);
$artifacts=sys_get_temp_dir().'/offline-indicator-'.bin2hex(random_bytes(6));
mkdir($artifacts,0700);
$app=new yii\web\Application(['id'=>'offline-indicator-test','basePath'=>$root,'runtimePath'=>$artifacts,'components'=>[
    'request'=>['cookieValidationKey'=>'fixture-only','scriptFile'=>__FILE__,'scriptUrl'=>'/index.php','enableCsrfValidation'=>false],
    'canonicalAccess'=>new class extends yii\base\Component { public function checkAccess(int $id,string $permission):bool{return true;} },
]]);
$app->request->setPathInfo('pilot/construction-control');
$app->request->setUrl('/pilot/construction-control');
$identity=new class {public int $id=73;public string $displayName='Тестовый инженер';public string $email='fixture@example.test';public function getId():int{return $this->id;}};
$html=$app->view->renderFile($root.'/app/YiiRuntime/Views/construction-control.php',['identity'=>$identity,'objects'=>[],'filters'=>['query'=>'','ownership'=>'mine','completed'=>''],'canSchedule'=>false]);
$checklistParameters=[
 'identity'=>$identity,'id'=>4512,'csrf'=>'fixture','fromControl'=>true,'progressCap'=>85,
 'projection'=>['revision'=>1,'items'=>[],'photos'=>[],'completedSections'=>[],'crew'=>[['tabId'=>18,'fullName'=>'Тестовый монтажник','employmentStatus'=>'employed']]],
 'access'=>['opened'=>true,'roleAccess'=>true,'itemComplete'=>true,'assigned'=>true,'photoRevoke'=>true,'address'=>'Москва, ул. Лесная, д. 12','entrance'=>'1','registrationNumber'=>'400120','orderNumber'=>'204310','technicalDocuments'=>['status'=>'unavailable','links'=>[]]],
];
$checklistHtml=$app->view->renderFile($root.'/app/YiiRuntime/Views/checklist.php',$checklistParameters);
$substitutionParameters=$checklistParameters;$substitutionParameters['id']=7000;$substitutionParameters['access']['address']='Подменная улица, дом 7';$substitutionParameters['access']['registrationNumber']='SUB-7000';
$substitutionHtml=$app->view->renderFile($root.'/app/YiiRuntime/Views/checklist.php',$substitutionParameters);
$readOnlyParameters=$checklistParameters;foreach(['roleAccess','itemComplete','assigned','photoRevoke'] as $key)$readOnlyParameters['access'][$key]=false;
$readOnlyHtml=$app->view->renderFile($root.'/app/YiiRuntime/Views/checklist.php',$readOnlyParameters);
$completedParameters=$readOnlyParameters;$completedParameters['id']=5051;$completedParameters['progressCap']=100;$completedParameters['access']['address']='Лесная улица, дом 52';$completedParameters['access']['registrationNumber']='FIELD-5051';
$completedHtml=$app->view->renderFile($root.'/app/YiiRuntime/Views/checklist.php',$completedParameters);
$assetController=new FMonitor2\YiiRuntime\Controllers\PilotAssetController('pilot-asset',$app);$shellAssets=$assetController->actionFile('field-shell.json')->data['assets'];
$offlineHtml=$app->view->renderFile($root.'/app/YiiRuntime/Views/field-offline.php');
$policies=[];
foreach(['pilot/construction-control','pilot/objects/4512/checklist','pilot/construction-control/objects/4512/checklist','pilot/dashboard'] as $path){
    $app->request->setPathInfo($path);$response=new yii\web\Response();
    FMonitor2\YiiRuntime\WebResponse::secure(new yii\base\Event(['sender'=>$response]));
    $policies[$path]=$response->headers->get('Content-Security-Policy');
}
file_put_contents($artifacts.'/fixture.json',json_encode(['shellAssets'=>$shellAssets,'substitutionHtml'=>$substitutionHtml,'readOnlyHtml'=>$readOnlyHtml,'completedHtml'=>$completedHtml,'offlineHtml'=>$offlineHtml,'html'=>$html,'checklistHtml'=>$checklistHtml,'policies'=>$policies,'root'=>$root,'artifacts'=>$artifacts],JSON_THROW_ON_ERROR));
$exit=0;
foreach(['offline_indicator_browser.cjs','offline_construction_browser.cjs'] as $script){
    $log=$artifacts.'/'.$script.'.log';
    $process=proc_open([getenv('FMONITOR_TEST_NODE_BINARY')?:'node',$root.'/tests/Support/'.$script,$artifacts.'/fixture.json'],[0=>['file','/dev/null','r'],1=>['file',$log,'w'],2=>['file',$log,'a']],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Browser process unavailable');
    $exit=max($exit,proc_close($process));echo file_get_contents($log);
}
echo 'Evidence: '.$artifacts."\n";exit($exit);
