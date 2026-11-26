<?php
declare(strict_types=1);
namespace FMonitor2\YiiRuntime\Controllers;

use FMonitor2\EquipmentReclamation\EquipmentReclamations;
use FMonitor2\DeadlineTransferCertificate\MariaDbDeadlineCertificatePageQuery;
use FMonitor2\YiiRuntime\PreopeningResources;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;

final class EquipmentReclamationController extends PreopeningController
{
    public function behaviors():array{return array_merge(parent::behaviors(),['verbs'=>['class'=>VerbFilter::class,'actions'=>['add'=>['POST']]]]);}
    public function actionAdd(string$id):string|Response
    {
        $objectId=$this->canonicalId($id);if($objectId===null)return$this->status(404);
        if(!$this->cap('objects.read')||!$this->cap('deadline_certificate.write'))return$this->status(403);
        if(preg_match('~^application/x-www-form-urlencoded(?:\s*;\s*charset=utf-8)?$~iD',(string)Yii::$app->request->contentType)!==1)return$this->status(415);
        $body=Yii::$app->request->rawBody;if(strlen($body)>8192)return$this->status(413);$names=[];foreach(explode('&',$body)as$pair){$name=rawurldecode(str_replace('+',' ',explode('=',$pair,2)[0]));if($name===''||str_contains($name,'[')||isset($names[$name]))return$this->status(400);$names[$name]=true;}parse_str($body,$fields);
        if(array_keys($fields)!==['_csrf','requestId','number','delayDays']||!Yii::$app->request->validateCsrfToken((string)$fields['_csrf']))return$this->status(400);
        if(!is_string($fields['delayDays'])||preg_match('/^[1-9][0-9]*$/D',$fields['delayDays'])!==1)return$this->formError($objectId,$fields,['_form'=>null,'delayDays'=>'Введите целое число от 1 до 65 535.'],422);
        $days=filter_var($fields['delayDays'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);if($days===false)return$this->formError($objectId,$fields,['_form'=>null,'delayDays'=>'Введите целое число от 1 до 65 535.'],422);
        $resources=new PreopeningResources(Yii::$app->db);
        try{
            $object=(new MariaDbDeadlineCertificatePageQuery(Yii::$app->db,$resources->db,$resources->prefix,(string)getenv('FMONITOR_LEGACY_TABLE_PREFIX')))->object($objectId);if($object===null)return$this->status(404);
            $service=new EquipmentReclamations($resources->db,$resources->prefix,static fn():string=>(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'));
            $service->add(['requestId'=>(string)$fields['requestId'],'installationCaseId'=>(int)$object['caseId'],'actorId'=>$this->actor(),'number'=>$fields['number'],'delayDays'=>(int)$days]);
            return$this->redirect303('/pilot/objects/'.$objectId.'?tab=readiness#equipment-reclamations');
        }catch(\InvalidArgumentException){return$this->formError($objectId,$fields,['_form'=>'Проверьте номер рекламации и дни задержки.'],422);}catch(\DomainException$e){return match($e->getMessage()){'FORBIDDEN'=>$this->status(403),'CASE_NOT_FOUND'=>$this->status(404),'NUMBER_CONFLICT'=>$this->formError($objectId,$fields,['_form'=>'Рекламация с таким номером уже добавлена.'],409),'OPERATION_CONFLICT'=>$this->formError($objectId,$fields,['_form'=>'Запрос уже использован. Проверьте данные и повторите добавление.'],409),'STORAGE_UNAVAILABLE'=>$this->status(503,true),default=>$this->formError($objectId,$fields,['_form'=>'Не удалось добавить рекламацию. Проверьте данные.'],422)};}catch(\Throwable){return$this->status(503,true);}finally{$resources->close();}
    }
    public function actionMethod(string$id):Response{return$this->canonicalId($id)===null?$this->status(404):$this->methodNotAllowed('POST');}
    private function formError(int$objectId,array$values,array$errors,int$status):string
    {
        Yii::$app->response->statusCode=$status;
        return$this->render('@app/app/YiiRuntime/Views/equipment-reclamation-error',['identity'=>Yii::$app->user->identity,'objectId'=>$objectId,'values'=>['number'=>is_string($values['number']??null)?$values['number']:'','delayDays'=>is_string($values['delayDays']??null)?$values['delayDays']:''],'errors'=>$errors,'csrf'=>Yii::$app->request->csrfToken]);
    }
}
