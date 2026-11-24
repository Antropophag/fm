<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__,2).'/app/autoload.php';require dirname(__DIR__).'/Yii2/Yii2AuthFixture.php';
use FMonitor2\InstallationProcess\OtizPaymentControlSchemaMigration;
$f=new FMonitor2\Tests\Yii2\Yii2AuthFixture(dirname(__DIR__,2));
try{
 $p=$f->prefix;assertSameValue(false,OtizPaymentControlSchemaMigration::apply($f->db,$p)['applied'],'exact schema replay');
 $f->db->query("ALTER TABLE {$p}fm2_otiz_object_payment_hold_events DROP INDEX object_revision");
 $before=$f->db->query("SHOW CREATE TABLE {$p}fm2_otiz_object_payment_hold_events")->fetch_array(MYSQLI_NUM)[1];$denied=false;
 try{OtizPaymentControlSchemaMigration::apply($f->db,$p);}catch(RuntimeException){$denied=true;}
 assertSameValue(true,$denied,'INTENDED_RED: payment-control migration rejects incompatible existing uniqueness');
 assertSameValue($before,$f->db->query("SHOW CREATE TABLE {$p}fm2_otiz_object_payment_hold_events")->fetch_array(MYSQLI_NUM)[1],'schema drift is rejected without destructive repair');
 echo "PASS MIKRYUKOVA-FEEDBACK-001 schema replay and drift\n";
}finally{$f->close();}
