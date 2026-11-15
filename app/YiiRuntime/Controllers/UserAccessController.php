<?php
declare(strict_types=1);
namespace FMonitor2\YiiRuntime\Controllers;
use DomainException;
use FMonitor2\IdentityAccess\YiiUserAccess;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use FMonitor2\IdentityAccess\LegacyIdentityLinkCommand;
use FMonitor2\IdentityAccess\ProductionLegacyIdentityLinkFactory;
final class UserAccessController extends PilotController
{
 public $layout=false;
 public function beforeAction($action):bool{if($action->id==='legacy-link'&&Yii::$app->request->isPost){$this->enableCsrfValidation=false;if(max((int)($_SERVER['CONTENT_LENGTH']??0),strlen(Yii::$app->request->rawBody))>8192){Yii::$app->response->statusCode=413;return false;}if(!str_starts_with((string)Yii::$app->request->contentType,'application/x-www-form-urlencoded')){Yii::$app->response->statusCode=415;return false;}}return parent::beforeAction($action);}
 public function behaviors():array{return['verbs'=>['class'=>VerbFilter::class,'actions'=>['invite'=>['POST'],'reissue'=>['POST'],'role'=>['POST'],'status'=>['POST'],'legacy-link'=>['POST'],'activate'=>['GET','POST']]],'access'=>['class'=>AccessControl::class,'except'=>['activate'],'rules'=>[['allow'=>true,'roles'=>['access.administer']]],'denyCallback'=>function():void{if(Yii::$app->user->isGuest){Yii::$app->user->setReturnUrl(Yii::$app->request->url);
Yii::$app->response->statusCode=303;
Yii::$app->response->headers->set('Location','/pilot/login');
}else throw new ForbiddenHttpException();
}]];
}
 public function actionIndex():string{try{$actor=(int)Yii::$app->user->id;$d=$this->owner()->directory($actor);$query=Yii::$app->request->queryParams;if(array_diff(array_keys($query),['q','status','role','page'])!==[])throw new BadRequestHttpException();$q=$this->queryScalar('q');$status=$this->queryScalar('status','all');$role=$this->queryScalar('role');$pageRaw=$this->queryScalar('page','1');if(!in_array($status,['all','active','invited','blocked'],true)||preg_match('/^[1-9][0-9]*$/D',$pageRaw)!==1||($role!==''&&preg_match('/^[1-9][0-9]*$/D',$role)!==1))throw new BadRequestHttpException();$all=$d['users'];$needle=mb_strtolower(trim($q));$filtered=array_values(array_filter($all,static function(array$user)use($needle,$status,$role):bool{$state=$user['active']?'active':($user['invited']?'invited':'blocked');$text=mb_strtolower($user['name'].' '.$user['email'].' '.$user['phone']);$roles=array_map('strval',array_column($user['roles'],'id'));return($needle===''||str_contains($text,$needle))&&($status==='all'||$status===$state)&&($role===''||in_array($role,$roles,true));}));$page=(int)$pageRaw;$pages=max(1,(int)ceil(count($filtered)/25));if($page>$pages)$page=$pages;$d['users']=array_slice($filtered,($page-1)*25,25);$d['allUsers']=$all;$d['total']=count($filtered);$d['page']=$page;$d['pages']=$pages;$d['query']=['q'=>$q,'status'=>$status,'role'=>$role];
}catch(DomainException){throw new ForbiddenHttpException();
}catch(\Throwable$e){if($e instanceof \yii\web\HttpException)throw$e;throw new \RuntimeException('User directory unavailable.',0,$e);
}return$this->render('@app/app/YiiRuntime/Views/users',['identity'=>Yii::$app->user->identity,'directory'=>$d,'notice'=>Yii::$app->session->getFlash('notice'),'error'=>Yii::$app->session->getFlash('error'),'invitation'=>Yii::$app->session->getFlash('invitation'),'inviteForm'=>Yii::$app->session->getFlash('inviteForm')]);
}
 public function actionEditor(int$id):string{Yii::$app->response->headers->set('Cache-Control','no-store');try{$actor=(int)Yii::$app->user->id;$d=$this->owner()->directory($actor);$user=current(array_filter($d['users'],static fn(array$u):bool=>(int)$u['id']===$id));if(!is_array($user))throw new NotFoundHttpException();$actorUser=current(array_filter($d['users'],static fn(array$u):bool=>(int)$u['id']===$actor));$canSuper=is_array($actorUser)&&in_array('access.superadminister',$actorUser['permissions'],true);$db=$this->legacyDb();$process=(string)getenv('FMONITOR_PROCESS_TABLE_PREFIX');$legacy=(string)getenv('FMONITOR_LEGACY_TABLE_PREFIX');if($legacy==='')$legacy=$process;try{$links=ProductionLegacyIdentityLinkFactory::create($db,$process,$legacy)->directory($actor);}finally{$db->close();}}catch(DomainException){throw new ForbiddenHttpException();}return$this->render('@app/app/YiiRuntime/Views/user-editor',['identity'=>Yii::$app->user->identity,'user'=>$user,'roles'=>$d['roles'],'legacyLink'=>$links[$id]??null,'canSuper'=>$canSuper,'actorId'=>$actor]);}
 public function actionLegacyLink(int$id):Response{
  if(strlen(http_build_query(Yii::$app->request->post()))>8192){Yii::$app->response->statusCode=413;return Yii::$app->response;}if(!Yii::$app->request->validateCsrfToken())throw new BadRequestHttpException();
  if((int)($_SERVER['CONTENT_LENGTH']??0)>8192){Yii::$app->response->statusCode=413;return Yii::$app->response;}if(!str_starts_with((string)Yii::$app->request->contentType,'application/x-www-form-urlencoded')){Yii::$app->response->statusCode=415;return Yii::$app->response;}
  $request=$this->scalar('requestId');$legacy=$this->scalar('legacyUserId');$reason=Yii::$app->request->post('correctionReason');if(!is_string($reason)&&$reason!==null)throw new BadRequestHttpException();
  try{$command=new LegacyIdentityLinkCommand($request,$id,filter_var($legacy,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0,(int)Yii::$app->user->id,$reason);}catch(\InvalidArgumentException){if(preg_match('/^[0-9a-f-]{36}$/D',$request)!==1)throw new BadRequestHttpException();Yii::$app->response->statusCode=422;return Yii::$app->response;}
  $db=$this->legacyDb();$process=(string)getenv('FMONITOR_PROCESS_TABLE_PREFIX');$legacy=(string)getenv('FMONITOR_LEGACY_TABLE_PREFIX');if($legacy==='')$legacy=$process;try{$r=ProductionLegacyIdentityLinkFactory::create($db,$process,$legacy)->link($command);}finally{$db->close();}
  if($r['status']==='failed'){Yii::$app->response->statusCode=503;return Yii::$app->response;}if($r['status']==='conflict'){Yii::$app->response->statusCode=409;return Yii::$app->response;}if($r['status']==='rejected'){Yii::$app->response->statusCode=$r['reasonCode']==='authorization_denied'?403:422;return Yii::$app->response;}return$this->redirectUsers(true);
 }
 public function actionInvite():Response{$origin=$this->invitationOrigin();
if($origin===null)return$this->unavailable();
$email=$this->scalar('email');
$name=$this->scalar('fullName');
try{$r=$this->owner()->invite((int)Yii::$app->user->id,$email,$name);
}catch(\Throwable$e){throw new \RuntimeException('Invitation unavailable.',0,$e);
}if($r['status']==='access_denied')throw new ForbiddenHttpException();
if($r['status']==='issued'){$this->success('Пользователь приглашён.',$r,$origin);
}else{$invalid=$this->invalidInviteField($email,$name);Yii::$app->session->setFlash('inviteForm',['email'=>$email,'fullName'=>$name,'invalid'=>$invalid]);Yii::$app->session->setFlash('error','Проверьте рабочий email, имя и отсутствие дубликата.');}
return$this->redirectUsers(true);
}
 public function actionReissue(int$id):Response{$origin=$this->invitationOrigin();
if($origin===null)return$this->unavailable();
try{$r=$this->owner()->reissue((int)Yii::$app->user->id,$id);
}catch(\Throwable$e){throw new \RuntimeException('Invitation unavailable.',0,$e);
}if($r['status']==='access_denied')throw new ForbiddenHttpException();
if($r['status']==='issued')$this->success('Новая ссылка приглашения создана.',$r,$origin);
else Yii::$app->session->setFlash('error','Новую ссылку можно выдать только приглашённому пользователю.');
return$this->redirectUsers(true);
}
 public function actionRole(int$id,?int$roleId=null):Response{$action=$this->scalar('action');
if($roleId===null){$raw=$this->scalar('roleId');
if(filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)throw new BadRequestHttpException();
$roleId=(int)$raw;
}try{$r=$this->owner()->changeRole((int)Yii::$app->user->id,$id,$roleId,$action);
}catch(\Throwable$e){throw new \RuntimeException('Role change unavailable.',0,$e);
}if($r['status']==='access_denied')throw new ForbiddenHttpException();
if($r['status']==='invalid')throw new BadRequestHttpException();
if($r['status']==='changed')Yii::$app->session->setFlash('notice','Роль изменена.');
return$this->redirectUsers($r['status']==='changed');
}
 public function actionStatus(int$id):Response{$action=$this->scalar('action');
try{$r=$this->owner()->changeStatus((int)Yii::$app->user->id,$id,$action);
}catch(\Throwable$e){throw new \RuntimeException('Status change unavailable.',0,$e);
}if($r['status']==='access_denied')throw new ForbiddenHttpException();
if($r['status']!=='changed')throw new BadRequestHttpException();
Yii::$app->session->setFlash('notice',$action==='block'?'Доступ заблокирован.':'Доступ восстановлен.');
return$this->redirectUsers(true);
}
 public function actionActivate():string
 { $request=Yii::$app->request;
$token=$request->isPost?$this->scalar('token'):$request->get('token','');
if(!is_string($token))throw new BadRequestHttpException();
$password=$request->isPost?$this->scalar('password'):'';
$confirmation=$request->isPost?$this->scalar('passwordConfirmation'):'';
$entry=null;
$done=false;
$error='';
try{$entry=$this->owner()->invitation($token);
if($request->isPost){$r=$this->owner()->activate($token,$password,$confirmation);
if($r['status']==='activated')$done=true;
elseif($r['status']==='invalid_password'){$error=(string)$r['reason'];
$entry=$this->owner()->invitation($token);
}else{$entry=null;
$error='Ссылка активации недействительна или истекла.';
}}elseif($entry===null)$error='Ссылка активации недействительна или истекла.';
}catch(\Throwable$e){throw new \RuntimeException('Activation unavailable.',0,$e);
}return$this->render('@app/app/YiiRuntime/Views/activate',['token'=>$token,'entry'=>$entry,'done'=>$done,'error'=>$error]);
 }
 private function owner():YiiUserAccess{return Yii::$app->userAccess;
}
 private function legacyDb():\mysqli{$db=new \mysqli((string)getenv('FMONITOR_DB_HOST'),(string)getenv('FMONITOR_DB_USER'),(string)getenv('FMONITOR_DB_PASSWORD'),(string)getenv('FMONITOR_DB_NAME'),(int)getenv('FMONITOR_DB_PORT'));$db->set_charset('utf8mb4');return$db;}
 private function scalar(string$name):string{$v=Yii::$app->request->post($name,'');
if(!is_string($v))throw new BadRequestHttpException();
return$v;
}
 private function queryScalar(string$name,string$default=''):string{$v=Yii::$app->request->get($name,$default);if(!is_string($v)||mb_strlen($v)>300)throw new BadRequestHttpException();return$v;}
 private function success(string$message,array$r,string$origin):void{Yii::$app->session->setFlash('notice',$message);
Yii::$app->session->setFlash('invitation',$origin.'/pilot/activate?token='.$r['token']);
}
 private function invitationOrigin():?string{$scheme=getenv('FMONITOR_TRUSTED_REQUEST_SCHEME');$host=getenv('FMONITOR_TRUSTED_REQUEST_HOST');
if(!is_string($scheme)||!in_array($scheme,['http','https'],true)||!is_string($host)||strlen($host)>253||preg_match('/^[A-Za-z0-9.-]+(?::[1-9][0-9]{0,4})?$/D',$host)!==1)return null;
$parts=explode(':',$host);if(isset($parts[1])&&(int)$parts[1]>65535)return null;$name=$parts[0];$valid=preg_match('/^[0-9.]+$/D',$name)===1?filter_var($name,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4):filter_var($name,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME);return$valid===false?null:$scheme.'://'.$host;
}
 private function unavailable():Response{$response=Yii::$app->response;$response->statusCode=503;$response->format=Response::FORMAT_JSON;$response->data=['ok'=>false,'reason'=>'SERVICE_UNAVAILABLE'];return$response;
}
 private function invalidInviteField(string$email,string$name):string{if(trim($name)===''||mb_strlen(trim($name))>300)return'fullName';return'email';
}
 private function redirectUsers(bool$rotate):Response{if($rotate)Yii::$app->request->getCsrfToken(true);
$response=Yii::$app->response;
$response->statusCode=303;
$response->headers->set('Location','/pilot/admin/users');
return$response;
}
}
