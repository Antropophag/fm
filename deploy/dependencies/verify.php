<?php
declare(strict_types=1);
if($argc!==3){fwrite(STDERR,"DEPENDENCY_FAILURE: internal verifier usage\n");exit(1);}
$vendor=$argv[1];$lockPath=$argv[2];
try{$lock=json_decode((string)file_get_contents($lockPath),true,64,JSON_THROW_ON_ERROR);require $vendor.'/autoload.php';}
catch(Throwable){fwrite(STDERR,"DEPENDENCY_FAILURE: generated autoload is unavailable\n");exit(1);}
foreach($lock['packages']??[] as $package){
    $name=$package['name']??null;$version=$package['version']??null;$reference=$package['dist']['reference']??$package['source']['reference']??null;
    if(!is_string($name)||!is_string($version)||!Composer\InstalledVersions::isInstalled($name)||Composer\InstalledVersions::getPrettyVersion($name)!==$version||Composer\InstalledVersions::getReference($name)!==$reference){fwrite(STDERR,"DEPENDENCY_FAILURE: installed package graph differs from lock\n");exit(1);}
    $path=Composer\InstalledVersions::getInstallPath($name);
    if(($package['type']??'')!=='metapackage'&&(!is_string($path)||!is_dir($path))){fwrite(STDERR,"DEPENDENCY_FAILURE: installed package path is unavailable\n");exit(1);}
}
$lockedNames=array_map(static fn(array $package):string=>$package['name'],$lock['packages']??[]);sort($lockedNames,SORT_STRING);
$installed=array_values(array_filter(Composer\InstalledVersions::getInstalledPackages(),static fn(string $name):bool=>$name!=='shlz/fmonitor-2'));sort($installed,SORT_STRING);
if($installed!==$lockedNames){fwrite(STDERR,"DEPENDENCY_FAILURE: installed package graph has unexpected entries\n");exit(1);}
$versions=[];foreach($lock['packages']??[] as $package)$versions[$package['name']]=$package['version'];
if(!isset($versions['yiisoft/yii2'],$versions['tecnickcom/tcpdf'])){fwrite(STDERR,"DEPENDENCY_FAILURE: required locked packages are missing\n");exit(1);}
if(!class_exists('Yii',false))require $vendor.'/yiisoft/yii2/Yii.php';
if(Yii::getVersion()!==$versions['yiisoft/yii2']||!class_exists('TCPDF')||TCPDF_STATIC::getTCPDFVersion()!==$versions['tecnickcom/tcpdf']){fwrite(STDERR,"DEPENDENCY_FAILURE: required runtime classes do not load\n");exit(1);}
