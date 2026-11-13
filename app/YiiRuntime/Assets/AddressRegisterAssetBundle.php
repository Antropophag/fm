<?php

declare(strict_types=1);

namespace FMonitor2\YiiRuntime\Assets;

final class AddressRegisterAssetBundle extends \yii\web\AssetBundle
{
    public $sourcePath = null;
    public $basePath = '@app/app/YiiRuntime/Assets';
    public $baseUrl = '/pilot/assets';
    public $css = ['shlz.css', 'pilot.css', 'address-register.css'];
    public $js = ['preloader.js', 'navigation.js', 'address-register.js'];
    public $jsOptions = ['position' => \yii\web\View::POS_END, 'type' => 'module'];

    public function init(): void
    {
        parent::init();
        $this->css = ['shlz.css', AssetVersion::file('pilot.css'), AssetVersion::file('address-register.css')];
        $this->js = ['preloader.js', 'navigation.js', AssetVersion::file('address-register.js')];
    }
}
