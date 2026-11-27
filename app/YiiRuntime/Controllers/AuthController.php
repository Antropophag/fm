<?php
declare(strict_types=1);
namespace FMonitor2\YiiRuntime\Controllers;
use FMonitor2\IdentityAccess\MariaDbYiiLocalIdentityStore;use Yii;use yii\filters\VerbFilter;use yii\web\BadRequestHttpException;use yii\web\Response;
final class AuthController extends PilotController
{
 public $layout=false;
 public function behaviors():array{return['verbs'=>['class'=>VerbFilter::class,'actions'=>['logout'=>['POST'],'first-password'=>['POST'],'forgot-password'=>['GET','HEAD','POST'],'reset-password'=>['GET','HEAD','POST']]]];}
 public function actionLogin():string|Response
 {
  Yii::$app->session->open();$r=Yii::$app->request;$reauth=$r->get('reauth');$explicitReauth=is_string($reauth)&&$reauth==='1';if(!Yii::$app->user->isGuest&&!$r->isPost&&!$explicitReauth)return$this->localRedirect('/pilot/objects');
  $email=$this->normalize((string)$r->post('email',''));$password=$r->post('password');$stage='email';$error='';
  if($r->isPost&&$this->validEmail($email))try{$step=Yii::$app->userAccess->loginStep($email);if($step==='initial')$stage='initial';elseif($step==='password'){$stage='password';if(is_string($password)){$destination=$this->authenticate($email,$password);if($destination===null)$error=$this->neutral();else return$this->localRedirect($destination);}}else$error='Для предоставления доступа создайте заявку в ИТСМ.';}catch(\Throwable$e){throw new \RuntimeException('Authentication unavailable.',0,$e);}elseif($r->isPost)$error='Введите рабочий email в домене @shlz.ru.';
  $path=$r->pathInfo==='pilot/otiz/login'?'/pilot/otiz/login':'/pilot/login';return$this->render('@app/app/YiiRuntime/Views/login',compact('stage','email','error','path'));
 }
 public function actionFirstPassword():string
 {
  $email=$this->normalize($this->scalar('email'));$error='';$done=false;try{$o=Yii::$app->userAccess->setInitialPassword($email,$this->scalar('password'),$this->scalar('passwordConfirmation'));if($o['status']==='activated')$done=true;elseif($o['status']==='invalid_password')$error=$o['reason'];else$error='Пароль уже задан или доступ недоступен. Войдите либо восстановите пароль.';}catch(\Throwable$e){throw new \RuntimeException('Password setup unavailable.',0,$e);}return$this->render('@app/app/YiiRuntime/Views/password-form',['mode'=>'initial','email'=>$email,'token'=>'','error'=>$error,'done'=>$done]);
 }
 public function actionForgotPassword():string
 {
  Yii::$app->response->headers->set('Cache-Control','no-store');Yii::$app->response->headers->set('Referrer-Policy','no-referrer');
  $r=Yii::$app->request;$s=Yii::$app->session;$s->open();$done=false;$email='';
  if(!$r->isPost){$requestId=$this->uuid();$s->set('passwordRecoveryRequestId',$requestId);return$this->render('@app/app/YiiRuntime/Views/forgot-password',compact('requestId','email','done'));}
  $requestId=$this->scalar('requestId');$current=$s->get('passwordRecoveryRequestId');$accepted=$s->get('passwordRecoveryLastAcceptedId');
  if(is_string($accepted)&&hash_equals($accepted,$requestId)){$done=true;return$this->render('@app/app/YiiRuntime/Views/forgot-password',compact('requestId','email','done'));}
  if(!is_string($current)||!hash_equals($current,$requestId))throw new BadRequestHttpException();
  $email=$this->normalize($this->scalar('email'));try{Yii::$app->userAccess->requestPasswordReset($email,$requestId,(string)($_SERVER['REMOTE_ADDR']??''));$s->set('passwordRecoveryLastAcceptedId',$requestId);$s->remove('passwordRecoveryRequestId');$done=true;}catch(\Throwable$e){throw new \RuntimeException('Password recovery unavailable.',0,$e);}
  return$this->render('@app/app/YiiRuntime/Views/forgot-password',compact('requestId','email','done'));
 }
 public function actionResetPassword():string
 {
  Yii::$app->response->headers->set('Cache-Control','no-store');Yii::$app->response->headers->set('Referrer-Policy','no-referrer');$r=Yii::$app->request;$token=$r->isPost?$this->scalar('token'):(string)$r->get('token','');$entry=null;$error='';$done=false;try{$entry=Yii::$app->userAccess->passwordReset($token);if($r->isPost){$o=Yii::$app->userAccess->resetPassword($token,$this->scalar('password'),$this->scalar('passwordConfirmation'));if($o['status']==='changed')$done=true;elseif($o['status']==='invalid_password')$error=$o['reason'];else{$entry=null;$error='Ссылка недействительна или истекла.';}}elseif($entry===null)$error='Ссылка недействительна или истекла.';}catch(\Throwable$e){throw new \RuntimeException('Password recovery unavailable.',0,$e);}return$this->render('@app/app/YiiRuntime/Views/password-form',['mode'=>'reset','email'=>$entry['email']??'','token'=>$token,'error'=>$error,'done'=>$done]);
 }
 public function actionLogout():Response{Yii::$app->user->logout();return$this->localRedirect('/pilot/login');}
 private function authenticate(string$e,string$p):?string{$s=$this->store();if($p===''||!mb_check_encoding($p,'UTF-8')||mb_strlen($p,'UTF-8')>200||$s->rateLimited($e)){$s->fail($e);return null;}$f=$s->findForLogin($e);if($f===null||!password_verify($p,$f['passwordHash'])){$s->fail($e);return null;}Yii::$app->user->login($f['identity'],0);$return=$this->safeReturn((string)Yii::$app->user->getReturnUrl('/pilot/objects'));Yii::$app->user->setReturnUrl('/pilot/objects');Yii::$app->session->close();$s->succeed($e);return$return;}
 private function store():MariaDbYiiLocalIdentityStore{return Yii::$app->localIdentity;}private function scalar(string$n):string{$v=Yii::$app->request->post($n,'');if(!is_string($v)||strlen($v)>1000)throw new BadRequestHttpException();return$v;}private function normalize(string$v):string{return mb_strtolower(trim($v));}private function validEmail(string$v):bool{return filter_var($v,FILTER_VALIDATE_EMAIL)!==false&&preg_match('/^[^@]+@shlz\.ru$/Di',$v)===1;}private function neutral():string{return'Не удалось войти. Проверьте email и данные доступа.';}private function safeReturn(string$u):string{return preg_match('#^/pilot/(?!assets(?:/|$)|login(?:/|$)|logout(?:/|$))[A-Za-z0-9/_?&=.%~-]*$#D',$u)===1?$u:'/pilot/objects';}private function localRedirect(string$u):Response{$x=Yii::$app->response;$x->statusCode=303;$x->headers->set('Location',$u);return$x;}private function uuid():string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12);}
}
