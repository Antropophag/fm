<?php
declare(strict_types=1);
// Process-local external adapter stand-in; executes unchanged public controller code.
namespace yii\console { class Controller {} }
namespace FMonitor2\Jobs {
    final class JobsRuntimeConfiguration {
        public static function fromEnvironment():self { return new self; }
        public function erpEquipmentFacts():\FMonitor2\InstallationProcess\ErpEquipmentFactsDeliveryConfig {
            return new \FMonitor2\InstallationProcess\ErpEquipmentFactsDeliveryConfig('fixture-host','fixture-db','reader','synthetic-private-value',500,7,100);
        }
    }
}
namespace FMonitor2\InstallationProcess {
    final class MariaDbSqlServerEquipmentFactsTransport {
        public function __construct(ErpEquipmentFactsDeliveryConfig $config) {
            file_put_contents($GLOBALS['probeTrace'],json_encode(['host'=>$config->host,'database'=>$config->database,'user'=>$config->user,'timeout'=>$config->timeoutSeconds])."\n",FILE_APPEND);
            if($GLOBALS['probeMode']==='connect-failure')throw new \RuntimeException('synthetic-private-value connection DSN');
        }
        public function __invoke(string$sql,array$params,array$options):array {
            file_put_contents($GLOBALS['probeTrace'],json_encode([$sql,$params,$options])."\n",FILE_APPEND);
            if($GLOBALS['probeMode']==='query-failure')throw new \RuntimeException('synthetic-private-value query SQL');
            return $GLOBALS['probeMode']==='malformed'?[['available'=>0]]:[['available'=>1]];
        }
    }
}
namespace {
    [$script,$root,$controller,$probeMode,$probeTrace]=$argv;
    require $root.'/app/autoload.php';
    require $controller;
    exit((new \FMonitor2\YiiRuntime\Commands\ErpEquipmentFactsSyncController())->actionProbe());
}
