<?php
declare(strict_types=1);
namespace FMonitor2\Tests\Support;
require_once dirname(__DIR__,2).'/app/autoload.php';
require_once dirname(__DIR__).'/Yii2/Yii2AuthFixture.php';

final class PaymentHoldFixture
{
    public \FMonitor2\Tests\Yii2\Yii2AuthFixture $base;
    public \yii\db\Connection $db;
    public \FMonitor2\Otiz\OtizSettlementV2 $owner;
    public string $p;
    private int $sequence=1;
    public function __construct()
    {
        $this->base=new \FMonitor2\Tests\Yii2\Yii2AuthFixture(dirname(__DIR__,2));$this->base->setPermission('otiz.manage');
        $e=$this->base->environment();$this->p=$this->base->prefix;
        $this->db=new \yii\db\Connection(['dsn'=>'mysql:host='.$e['FMONITOR_DB_HOST'].';port='.$e['FMONITOR_DB_PORT'].';dbname='.$this->base->database,'username'=>$e['FMONITOR_DB_USER'],'password'=>$e['FMONITOR_DB_PASSWORD'],'charset'=>'utf8mb4']);$this->db->open();
        $p=$this->p;
        $this->db->createCommand()->insert($p.'fm2_pilot_users',['user_id'=>9102,'full_name'=>'Второй ОТиЗ','email'=>'second@example.test','status'=>1,'activation_state'=>'active','source_updated_at'=>'2026-11-24T09:00:00Z'])->execute();
        $this->db->createCommand("INSERT INTO {$p}fm2_pilot_user_roles(user_id,role_id,origin,assigned_at) SELECT 9102,role_id,'fixture','2026-11-24T09:00:00Z' FROM {$p}fm2_pilot_user_roles WHERE user_id=9101")->execute();
        $this->db->createCommand("INSERT INTO {$p}fm2_pilot_auth_credentials(user_id,email_normalized,password_hash,password_set_at,updated_at) SELECT 9102,'second@example.test',password_hash,password_set_at,updated_at FROM {$p}fm2_pilot_auth_credentials WHERE user_id=9101")->execute();
        $this->db->createCommand("CREATE TABLE IF NOT EXISTS {$p}fm_maintable(id BIGINT PRIMARY KEY,regnumber VARCHAR(80),ordadr_address VARCHAR(255),plan_finish_date DATE NULL) ENGINE=InnoDB")->execute();
        foreach([4512,4513]as$id) {
            $this->db->createCommand()->insert($p.'fm_maintable',['id'=>$id,'regnumber'=>'TEST-'.$id,'ordadr_address'=>'Тест '.$id])->execute();
            $this->db->createCommand()->insert($p.'fm2_installation_cases',['id'=>$id,'legacy_installation_object_id'=>$id,'process_state'=>'working','actual_start_date'=>'2026-11-01','created_at'=>'2026-11-01T09:00:00Z','updated_at'=>'2026-11-01T09:00:00Z','lock_version'=>1])->execute();
        }
        $this->owner=new \FMonitor2\Otiz\OtizSettlementV2($this->db,$p,static fn()=>'2026-11-24T19:00:00+03:00',static fn()=>'allow');
    }
    public function op():string{return sprintf('24242424-2424-4242-8242-%012d',$this->sequence++);}
    public function accepted(string $suffix='base'):array
    {
        $objects=[];
        foreach([4512=>600000,4513=>400000]as$id=>$amount)$objects[]=['objectId'=>$id,'regnumber'=>'TEST-'.$id,'fundCents'=>$amount,'kssBp'=>10000,'entitlements'=>[['sourceKind'=>'fixture','sourceId'=>$suffix.'-'.$id,'sourceRevision'=>'1','kind'=>'progress','recognitionDate'=>'2026-11-20','grossCents'=>$amount]],'recipients'=>[['employeeId'=>'A'.$id,'tabNumber'=>(string)$id,'name'=>'Монтажник '.$id,'weight'=>1,'employment'=>'employed']]];
        $d=$this->owner->createDraft(9101,['reportDate'=>'2026-11-24','recipientPolicyVersion'=>'reserved-shares-v1','objects'=>$objects],$this->op());
        return $this->owner->accept(9101,$d['calculationId'],$d['revision'],$this->op());
    }
    public function count(string $table):int{return(int)$this->db->createCommand('SELECT COUNT(*) FROM '.$this->p.$table)->queryScalar();}
    public function close():void{$this->db->close();$this->base->close();}
}
