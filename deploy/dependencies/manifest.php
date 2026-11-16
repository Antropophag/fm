<?php
declare(strict_types=1);

function stop(string $message): never { fwrite(STDERR, "DEPENDENCY_FAILURE: $message\n"); exit(1); }
function readJson(string $path): array {
    if (is_link($path) || !is_file($path)) stop('required JSON file is unavailable');
    try { $value=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR); }
    catch (Throwable) { stop('invalid JSON'); }
    if (!is_array($value)) stop('invalid JSON root');
    return $value;
}
function digest(string $path): string { $value=hash_file('sha256',$path); if(!is_string($value))stop('cannot hash input'); return $value; }
function packageReference(array $package): ?string {
    $value=$package['dist']['reference']??$package['source']['reference']??null;
    return is_string($value)?$value:null;
}
function inputs(string $project,string $manifestPath): array {
    $jsonPath=$project.'/composer.json';$lockPath=$project.'/composer.lock';
    $manifest=readJson($manifestPath);$root=readJson($jsonPath);$lock=readJson($lockPath);
    if (($manifest['schemaVersion']??null)!==1 || array_keys($manifest)!==['schemaVersion','composerJsonSha256','composerLockSha256','packages']) stop('invalid manifest schema');
    if (($manifest['composerJsonSha256']??null)!==digest($jsonPath) || ($manifest['composerLockSha256']??null)!==digest($lockPath)) stop('manifest does not bind Composer inputs');
    if (($lock['packages-dev']??[])!==[]) stop('development lock packages are not supported by production manifest');
    $locked=[];
    foreach($lock['packages']??[] as $package){
        if(!is_array($package)||!is_string($package['name']??null)||isset($locked[$package['name']]))stop('invalid locked package set');
        $locked[$package['name']]=$package;
    }
    $approved=[];
    if(!is_array($manifest['packages']))stop('invalid package manifest');
    foreach($manifest['packages'] as $item){
        if(!is_array($item)||array_keys($item)!==['name','version','reference','type','url','sha256','byteSize'])stop('invalid package entry');
        $name=$item['name']??null;
        if(!is_string($name)||isset($approved[$name])||!isset($locked[$name]))stop('manifest package set differs from lock');
        $package=$locked[$name];$dist=$package['dist']??null;
        if(!is_array($dist)||($dist['type']??null)!=='zip')stop('locked package has unsupported dist');
        $api=$dist['url']??null;$reference=packageReference($package);
        if(!is_string($api)||!preg_match('#^https://api\.github\.com/repos/([^/]+)/([^/]+)/zipball/([0-9a-f]{40})$#D',$api,$match))stop('locked package URL is unsupported');
        $expectedUrl='https://codeload.github.com/'.$match[1].'/'.$match[2].'/legacy.zip/'.$match[3];
        if(($item['version']??null)!==($package['version']??null)||($item['reference']??null)!==$reference||$reference!==$match[3]||($item['type']??null)!=='zip'||($item['url']??null)!==$expectedUrl)stop('manifest package identity differs from lock');
        if(!is_string($item['sha256']??null)||!preg_match('/^[0-9a-f]{64}$/D',$item['sha256'])||!is_int($item['byteSize']??null)||$item['byteSize']<1||$item['byteSize']>100000000)stop('invalid package digest or size');
        $approved[$name]=$item;
    }
    if(count($approved)!==count($locked))stop('manifest package set differs from lock');
    ksort($approved,SORT_STRING);
    return [$manifest,$lock,$approved];
}

if($argc<4)stop('internal helper usage');
[$script,$action,$project,$manifestPath]=$argv;
[$manifest,$lock,$approved]=inputs($project,$manifestPath);
if($action==='check')exit(0);
if($action==='list'){
    foreach($approved as $item)echo $item['sha256']."\t".$item['byteSize']."\t".$item['url']."\n";
    exit(0);
}
if($action==='lock'){
    if($argc!==6)stop('internal helper usage');
    $archiveDir=$argv[4];$output=$argv[5];
    foreach($lock['packages'] as &$package){$item=$approved[$package['name']];$package['dist']['url']='file://'.$archiveDir.'/'.$item['sha256'].'.zip';}
    unset($package);
    $encoded=json_encode($lock,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(file_put_contents($output,$encoded."\n")===false)stop('cannot write private lock');
    exit(0);
}
stop('unknown internal helper action');
