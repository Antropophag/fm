<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/Yii2/PreopeningFixture.php';
require_once __DIR__.'/AddressRegisterWorkbookFixture.php';

final class AddressRegisterImportFixture
{
 public PreopeningFixture $base;public mysqli $db;public string $p;public string $root;public string $path;public object $provider;public object $owner;public string $now='2026-11-18T18:00:00Z';public string $secret;
 public array $paid=['allowPaidCalls'=>true,'maxPaidRequests'=>20];
 public function __construct(string$target='dev'){
  $this->base=new PreopeningFixture(dirname(__DIR__,2));$this->db=$this->base->db;$this->p=$this->base->p;$this->root=sys_get_temp_dir().'/address-fixture-'.bin2hex(random_bytes(6));mkdir($this->root,0700);$this->path=$this->root.'/input.xlsx';$this->secret=str_repeat('fixture-evidence-secret-',3);
  $this->provider=new class implements FMonitor2\InstallationProcess\AddressRegisterAddressProvider {
   public int $calls=0;public array $patches=[];public array $failures=[];
   public function clean(string$address):array{
    $this->calls++;if($this->failures!==[]){$failure=array_shift($this->failures);if($failure!==null)throw new FMonitor2\InstallationProcess\AddressRegisterProviderFailure(...$failure);}
    preg_match('/д\s+(\d+)/u',$address,$m);$house=$m[1]??'10';return array_replace(AddressRegisterWorkbookFixture::address($house),$this->patches[$house]??[]);
   }
  };
  $this->owner=$this->make($target);
 }
 public function make(string$target='dev',int$actor=18,?mysqli$db=null,?string$secret=null):object{return FMonitor2\InstallationProcess\ProductionAddressRegisterImportFactory::create($db??$this->db,$this->p,$this->p,$target,$actor,$this->provider,fn():string=>$this->now,['evidenceSecret'=>$secret??$this->secret]);}
 public function write(array$rows):void{AddressRegisterWorkbookFixture::write($this->path,$rows);}
 public function preview(array$rows,bool$overwrite=false):array{$this->write($rows);return $this->owner->preview($this->path,2027,'TEST-CONTRACT',$overwrite,$overwrite?'Уточнение исходных данных':'',$this->paid);}
 public function apply(array$p,string$op='66666666-6666-4666-8666-000000000001'):array{return $this->owner->apply($p['packageId'],$p['previewSha256'],$op);}
 public function id(string$reg):int{$s=$this->db->prepare("SELECT id FROM {$this->p}fm_maintable WHERE regnumber=?");$s->execute([$reg]);return(int)$s->get_result()->fetch_column();}
 /** Test observation only. All business families included; preparation journals explicitly excluded. */
 public function business(bool $withPreparationPayloads=false):array{
  $out=[];foreach($this->db->query('SHOW TABLES')->fetch_all(MYSQLI_NUM)as[$table]){
   if(!$withPreparationPayloads&&$table===$this->p.'fm2_address_import_payload_chunks')continue;
   if(in_array($table,[$this->p.'fm2_address_import_packages',$this->p.'fm2_address_import_cache',$this->p.'fm2_address_import_attempts',$this->p.'fm2_address_import_locks'],true))continue;
   $rows=$this->db->query("SELECT * FROM `$table`")->fetch_all(MYSQLI_ASSOC);usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$out[$table]=$rows;
  }ksort($out);return$out;
 }
 public function close():void{self::remove($this->root);$this->base->close();}
 private static function remove(string$p):void{if(is_link($p)||is_file($p)){unlink($p);return;}foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')self::remove($p.'/'.$n);rmdir($p);}
}
