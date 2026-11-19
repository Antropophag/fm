<?php
declare(strict_types=1);
namespace FMonitor2\YiiRuntime\Commands;

use yii\console\Controller;

final class AddressRegisterController extends Controller
{
    public function actionEstimate():int{return $this->forward('estimate');}
    public function actionPreview():int{return $this->forward('preview');}
    public function actionApply():int{return $this->forward('apply');}
    public function actionResume():int{return $this->forward('resume');}
    public function actionReport():int{return $this->forward('report');}
    public function actionExportEvidence():int{return $this->forward('export-evidence');}
    public function actionImportEvidence():int{return $this->forward('import-evidence');}

    private function forward(string $action):int
    {
        $raw=$GLOBALS['FMONITOR2_RAW_ARGV']??[];$arguments=array_slice($raw,1);
        if (($arguments[0] ?? null) !== 'address-register/' . $action) return 64;
        $forward=array_values(array_filter(array_slice($arguments,1),static fn(string $arg):bool=>$arg!=='--interactive=0'));
        $command=[PHP_BINARY,dirname(__DIR__,3).'/bin/fmonitor2-address-register.php',$action,...$forward];
        $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,3),getenv());
        if(!is_resource($process))return 69;
        stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
        $open=[1=>$pipes[1],2=>$pipes[2]];
        while($open!==[]){$read=array_values($open);$write=$except=[];$ready=stream_select($read,$write,$except,1);if($ready===false){proc_terminate($process);return69;}foreach($read as$stream){$chunk=fread($stream,65536);if($chunk===false){proc_terminate($process);return69;}if($chunk!==''){if($stream===$pipes[1])fwrite(STDOUT,$chunk);else fwrite(STDERR,$chunk);}if(feof($stream)){$key=$stream===$pipes[1]?1:2;fclose($stream);unset($open[$key]);}}}
        return proc_close($process);
    }
}
