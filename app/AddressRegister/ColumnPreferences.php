<?php
declare(strict_types=1);
namespace FMonitor2\AddressRegister;

final class ColumnPreferences
{
    /** @var callable(int):bool */
    private $authorized;

    public function __construct(private readonly string $root,callable $authorized)
    {
        if($root===''||$root[0]!=='/'||str_contains($root,"\0"))throw new \InvalidArgumentException('Preference root must be absolute.');
        $this->authorized=$authorized;
    }

    /** @return array{revision:int,visible:list<string>,layoutVersion:int} */
    public function read(int $actor):array
    {
        $this->authorize($actor);$this->ensureRoot();
        $directory=$this->actorDirectory($actor,false);
        if($directory===null)return Columns::defaults();
        $lock=$this->lock($directory);
        try{if(!flock($lock,LOCK_SH))throw new \RuntimeException('Preference lock unavailable.');return $this->readCurrent($directory)['state'];}
        finally{@flock($lock,LOCK_UN);fclose($lock);}
    }

    /** @return array{revision:int,visible:list<string>,layoutVersion:int} */
    public function save(int $actor,int $expectedRevision,array $visible):array
    {
        $this->authorize($actor);$normalized=$this->validateV2($expectedRevision,$visible);$this->ensureRoot();
        $directory=$this->actorDirectory($actor,true);if($directory===null)throw new \RuntimeException('Preference directory unavailable.');
        $lock=$this->lock($directory);
        try{
            if(!flock($lock,LOCK_EX))throw new \RuntimeException('Preference lock unavailable.');
            $current=$this->readCurrent($directory);
            if($current['state']['revision']!==$expectedRevision)throw new PreferenceConflict('Preference revision is stale.');
            if(!$current['legacy']&&$current['state']['visible']===$normalized['visible'])return$current['state'];
            $next=['revision'=>$expectedRevision+1,'visible'=>$normalized['visible'],'layoutVersion'=>2];
            $bytes=$this->encode($next);$history=$directory.'/revision-'.sprintf('%010d',$next['revision']).'.json';
            $this->publishNew($history,$bytes);
            return$next;
        }finally{@flock($lock,LOCK_UN);fclose($lock);}
    }

    private function authorize(int $actor):void
    {if($actor<1||!(($this->authorized)($actor)))throw new \DomainException('Address register access denied.');}

    private function ensureRoot():void
    {
        if(is_link($this->root))throw new \RuntimeException('Preference root is a symlink.');
        if(!file_exists($this->root)){
            $parent=dirname($this->root);if(!is_dir($parent)||is_link($parent)||(!@mkdir($this->root,0700)&&(!is_dir($this->root)||is_link($this->root))))throw new \RuntimeException('Preference root unavailable.');
        }
        if(!is_dir($this->root)||is_link($this->root))throw new \RuntimeException('Invalid preference root.');
        if(!chmod($this->root,0700))throw new \RuntimeException('Preference root permissions unavailable.');
    }

    private function actorDirectory(int $actor,bool $create):?string
    {
        $directory=$this->root.'/user-'.$actor;
        if(is_link($directory))throw new \RuntimeException('Invalid preference user directory.');
        if(!file_exists($directory)){
            if(!$create)return null;
            if(!@mkdir($directory,0700)&&(!is_dir($directory)||is_link($directory)))throw new \RuntimeException('Preference user directory unavailable.');
        }
        if(!is_dir($directory)||is_link($directory)||!chmod($directory,0700))throw new \RuntimeException('Invalid preference user directory.');
        return$directory;
    }

    /** @return resource */
    private function lock(string $directory)
    {
        $path=$directory.'/.lock';if(is_link($path)||(file_exists($path)&&!is_file($path)))throw new \RuntimeException('Invalid preference lock.');
        $handle=@fopen($path,'c+b');if(!is_resource($handle)||!chmod($path,0600)){if(is_resource($handle))fclose($handle);throw new \RuntimeException('Preference lock unavailable.');}return$handle;
    }

    /** @return array{state:array{revision:int,visible:list<string>,layoutVersion:int},legacy:bool} */
    private function readCurrent(string $directory):array
    {
        $entries=scandir($directory);if(!is_array($entries))throw new \RuntimeException('Preference directory unavailable.');$versions=[];
        foreach($entries as$entry)if(preg_match('/^revision-([0-9]{10})\.json$/D',$entry,$match)===1)$versions[(int)$match[1]]=$directory.'/'.$entry;
        if($versions===[])return ['state'=>Columns::defaults(),'legacy'=>false];ksort($versions,SORT_NUMERIC);$revision=(int)array_key_last($versions);$path=$versions[$revision];
        if(is_link($path)||!is_file($path)||filesize($path)===false||filesize($path)>65536)throw new \RuntimeException('Preference snapshot is invalid.');
        $bytes=file_get_contents($path);if(!is_string($bytes))throw new \RuntimeException('Preference snapshot unavailable.');
        try{$value=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable $error){throw new \RuntimeException('Preference snapshot is corrupt.',0,$error);}
        if(!is_array($value)||!is_int($value['revision']??null)||$value['revision']!==$revision)throw new \RuntimeException('Preference snapshot is corrupt.');
        try{
            if(array_keys($value)===['revision','visible','layoutVersion']&&$value['layoutVersion']===2)return ['state'=>$this->validateV2($revision,$value['visible']),'legacy'=>false];
            if(array_keys($value)===['revision','visible','widths']){$this->validateV1($revision,$value['visible'],$value['widths']);$state=Columns::defaults();$state['revision']=$revision;return ['state'=>$state,'legacy'=>true];}
            throw new \InvalidArgumentException('Unknown preference schema.');
        }catch(\Throwable $error){throw new \RuntimeException('Preference snapshot is corrupt.',0,$error);}
    }

    /** @return array{revision:int,visible:list<string>,layoutVersion:int} */
    private function validateV2(int $revision,mixed $visible):array
    {
        if($revision<0||$revision>PHP_INT_MAX-1)throw new \InvalidArgumentException('Invalid preference revision.');
        $catalogue=Columns::catalogue();$keys=array_keys($catalogue);
        if(!is_array($visible)||$visible===[]||!array_is_list($visible)||count($visible)>count($keys)||$visible[0]!=='registrationNumber')throw new \InvalidArgumentException('Invalid visible columns.');
        $seen=[];foreach($visible as$key){if(!is_string($key)||!isset($catalogue[$key])||isset($seen[$key]))throw new \InvalidArgumentException('Invalid visible columns.');$seen[$key]=true;}
        return['revision'=>$revision,'visible'=>array_values($visible),'layoutVersion'=>2];
    }

    private function validateV1(int $revision,mixed $visible,mixed $widths):void
    {
        if($revision<0||!is_array($visible)||!array_is_list($visible)||$visible===[]||$visible[0]!=='address'||!is_array($widths)||array_is_list($widths))throw new \InvalidArgumentException('Invalid legacy preferences.');
        $legacyKeys=['address','entrance','registrationNumber','factoryNumber','status','completionProgress','plannedStartDate','plannedFinishDate','actualStartDate','actualFinishDate','controlEngineer','installers','floors','weight','speed','lift_type','pittype','pitmaterial','paired','ptoDate','declarationDate','comment'];
        $seen=[];foreach($visible as$key){if(!is_string($key)||!in_array($key,$legacyKeys,true)||isset($seen[$key]))throw new \InvalidArgumentException('Invalid legacy visible columns.');$seen[$key]=true;}
        $actual=array_keys($widths);sort($actual,SORT_STRING);$expected=$legacyKeys;sort($expected,SORT_STRING);if($actual!==$expected)throw new \InvalidArgumentException('Invalid legacy widths.');
        foreach($widths as$width)if(!is_int($width)||$width<80||$width>640)throw new \InvalidArgumentException('Invalid legacy width.');
    }

    private function encode(array $value):string
    {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}

    private function publishNew(string $path,string $bytes):void
    {
        if(is_link($path)||file_exists($path))throw new \RuntimeException('Preference revision already exists.');
        $temporary=dirname($path).'/.revision-'.bin2hex(random_bytes(12)).'.tmp';$handle=@fopen($temporary,'x+b');if(!is_resource($handle))throw new \RuntimeException('Preference revision unavailable.');
        try{$this->write($handle,$bytes);if(!chmod($temporary,0600))throw new \RuntimeException('Preference permissions unavailable.');fclose($handle);$handle=null;if(file_exists($path)||!rename($temporary,$path))throw new \RuntimeException('Preference publish failed.');}
        catch(\Throwable $error){if(is_resource($handle))fclose($handle);@unlink($temporary);throw$error;}
    }

    /** @param resource $handle */
    private function write($handle,string $bytes):void
    {
        for($offset=0,$length=strlen($bytes);$offset<$length;$offset+=$written){$written=fwrite($handle,substr($bytes,$offset));if(!is_int($written)||$written<1)throw new \RuntimeException('Preference write failed.');}
        if(!fflush($handle)||(function_exists('fsync')&&!fsync($handle)))throw new \RuntimeException('Preference sync failed.');
    }
}
