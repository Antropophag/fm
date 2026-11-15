<?php
declare(strict_types=1);
namespace FMonitor2\YiiRuntime\Assets;
final class CalendarAssetBundle extends \yii\web\AssetBundle
{
    public $baseUrl='/pilot/assets';
    public $js=['calendar.js'];
    public $jsOptions=['position'=>\yii\web\View::POS_END,'type'=>'module'];
    public $depends=[ShellAssetBundle::class];
    public function init():void{parent::init();$this->js=[AssetVersion::file('calendar.js')];}
}
