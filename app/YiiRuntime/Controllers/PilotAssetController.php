<?php

declare(strict_types=1);

namespace FMonitor2\YiiRuntime\Controllers;

use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use FMonitor2\YiiRuntime\Assets\AssetVersion;

final class PilotAssetController extends PilotController
{
    public function actionFile(string$path): Response
    {
        $root = dirname(__DIR__) . '/Assets';
        $shellAssets=null;$shellGeneration=null;
        if(in_array($path,['field-shell.json','checklist-sw.js'],true)){
            $shellFiles=['shlz.css','pilot.css','preopening.css','field-offline.css','sidebar-bootstrap.js','navigation.js','shlz-behaviors.js','preloader.js','preopening.js','template-offer.js','inspection-schedule.js','field-offline-store.js','field-offline-sync.js','field-offline-photo.js','field-offline.js','fuse.min.js','field-offline-address-search.js','field-offline-preparation.js','field-offline-lifecycle.js','checklist.js','control-queue.js'];
            $shellAssets=array_map(static fn(string$file):string=>'/pilot/assets/'.AssetVersion::file($file),$shellFiles);
            $shellGeneration=hash('sha256',json_encode($shellAssets,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        }
        if($path==='field-shell.json'){
            if(!is_array($shellAssets)||!is_string($shellGeneration))throw new \RuntimeException('Shell manifest unavailable.');
            $response=Yii::$app->response;$response->format=Response::FORMAT_JSON;$response->data=['generation'=>$shellGeneration,'assets'=>$shellAssets];$response->headers->set('Cache-Control','no-store');return$response;
        }
        $map = [];
        foreach (['sidebar-bootstrap.js','navigation.js','calendar.js','preloader.js','users.js','object-queue.js','inspection-schedule.js','preopening.js','template-offer.js','field-offline-store.js','field-offline-sync.js','field-offline-photo.js','field-offline.js','field-offline-address-search.js','field-offline-preparation.js','field-offline-lifecycle.js','checklist.js','control-queue.js','otiz.js','address-register.js'] as $file) {
            $map[$file] = [$root . '/' . $file,'text/javascript; charset=UTF-8',3600];
        }$map['fuse.min.js']=[dirname(__DIR__,3).'/public/third-party/fuse/fuse.min.js','text/javascript; charset=UTF-8',31536000];$shlzRoot=(string)(getenv('FMONITOR_SHLZ_UI_ROOT')?:dirname(__DIR__,4).'/shlz-ui');
        $map['shlz-behaviors.js']=[$shlzRoot.'/packages/behaviors/dist/browser.js','text/javascript; charset=UTF-8',3600];
        foreach (['file-pdf-default','file-doc','file-docx','file-xls','file-xlsx','file-img','file-generic'] as $file) {
            $map['shlz-file-types/'.$file.'.svg']=[$shlzRoot.'/packages/icons/dist/file-types/'.$file.'.svg','image/svg+xml; charset=UTF-8',3600];
        }
        foreach(['download','eye','close','arrow-left-md','arrow-right-md']as$icon)$map['shlz-icons/'.$icon.'.svg']=[$shlzRoot.'/packages/icons/dist/icons/'.$icon.'.svg','image/svg+xml; charset=UTF-8',3600];
        $map['address-register.css']=[$root.'/address-register.css','text/css; charset=UTF-8',3600];$map['completion-register.css']=[$root.'/completion-register.css','text/css; charset=UTF-8',3600];$map['field-offline.css']=[$root.'/field-offline.css','text/css; charset=UTF-8',3600];$map['checklist-sw.js']=[$root.'/checklist-sw.js','text/javascript; charset=UTF-8',0];$map['manifest.webmanifest']=[$root.'/manifest.webmanifest','application/manifest+json; charset=UTF-8',3600];$map['pwa-icon-192.png']=[$root.'/pwa-icon-192.png','image/png',31536000];$map['pwa-icon-512.png']=[$root.'/pwa-icon-512.png','image/png',31536000];foreach (['shlz.css','pilot.css','preopening.css'] as $file) {
            $map[$file] = [$root . '/' . $file,'text/css; charset=UTF-8',3600];
        }foreach (['calendar-sidebar.svg','calendar-interface.svg','logout.svg','search.svg','chevron-left-duo.svg','chevron-right-duo.svg','cloud-upload.svg','plus-alt-2.svg','delivery-box.svg','folder-file-open.svg'] as $file) {
            $map['shlz-icons/'.$file]=[$root.'/shlz-icons/'.$file,'image/svg+xml; charset=UTF-8',31536000];
        }$map['favicon.svg'] = [$root . '/favicon.svg','image/svg+xml; charset=UTF-8',31536000];
        if (preg_match('/^fonts\/(golos-text-(?:cyrillic|latin)-(?:400|500|600)-normal\.woff2)$/D', $path, $m) === 1) {
            $entry = [$root . '/fonts/' . $m[1],'font/woff2',31536000];
        } else {
            $entry = $map[$path] ?? null;
        }if (!is_array($entry) || !is_file($entry[0]) || is_link($entry[0])) {
            throw new NotFoundHttpException();
        }$bytes = file_get_contents($entry[0]);
        if (!is_string($bytes)) {
            throw new \RuntimeException();
        }if($path==='checklist-sw.js'){if(!is_string($shellGeneration))throw new \RuntimeException('Shell generation unavailable.');$bytes=str_replace('__FMONITOR_WORKER_GENERATION__',$shellGeneration,$bytes);}if(str_ends_with($path,'.js'))$bytes=preg_replace_callback("~((?:from\\s*|import\\s*)['\"])/pilot/assets/([A-Za-z0-9.-]+\\.js)(['\"])~",static fn(array$m):string=>$m[1].'/pilot/assets/'.AssetVersion::file($m[2]).$m[3],$bytes)??$bytes;
        $requested=Yii::$app->request->get('v');$expected=null;if(preg_match('/\\.(?:css|js)$/D',$path)===1){$versioned=AssetVersion::file($path);$expected=substr($versioned,strpos($versioned,'?v=')+3);}
        $immutable=($expected!==null&&is_string($requested)&&hash_equals($expected,$requested))||($expected===null&&$entry[2]===31536000);
        $r = Yii::$app->response;
        $r->format = Response::FORMAT_RAW;
        $r->headers->set('Content-Type', $entry[1]);
        $r->headers->set('Cache-Control',$immutable?'public, max-age=31536000, immutable':'public, max-age='.$entry[2]);
        $r->headers->set('X-Content-Type-Options', 'nosniff');
        $r->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        if($path==='checklist-sw.js')$r->headers->set('Service-Worker-Allowed','/pilot/');
        $r->content = $bytes;
        return$r;
    }
}
