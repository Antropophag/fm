<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Support/AddressRegisterImportFixture.php';
use FMonitor2\InstallationProcess\ProductionObjectDetailsEditFactory;
use FMonitor2\InstallationProcess\ObjectDetailsEditCommand;
$f=null;
try{
 $f=new AddressRegisterImportFixture();
 assertSameValue(true,method_exists($f->owner,'confirmAddresses'),'A17 public confirmation seam');
 $f->db->query("UPDATE {$f->p}fm_maintable SET ordadr_address='г Москва, ул Тестовая, д 9' WHERE id=4512");
 $a=AddressRegisterWorkbookFixture::row('TEST-4512','10');$a['H']='2';
 $b=AddressRegisterWorkbookFixture::row('MAN-NEW-11','11');$b['D']='81011';
 $c=AddressRegisterWorkbookFixture::row('MAN-NEW-12','12');$c['D']='81012';
 $f->provider->patches['10']=['qc'=>3];$f->provider->patches['12']=['qc'=>1,'house'=>'99','house_fias_id'=>null,'fias_level'=>'7','unparsed_parts'=>'WRONG STREET'];
 $preview=$f->preview([$a,$b,$c]);assertSameValue('rejected',$preview['status'],'A17 default remains strict');assertSameValue(3,$f->provider->calls,'A17 exactly3 cached calls');
 $cache=$f->db->query("SELECT * FROM {$f->p}fm2_address_import_cache ORDER BY entry_id")->fetch_all(MYSQLI_ASSOC);$decisions=[];$ordinal=0;
 foreach($cache as$r){$q=json_decode($r['request_json'],true);if(!preg_match('/д (10|12)$/u',$q['request']['address'],$m))continue;$canonical=AddressRegisterWorkbookFixture::address($m[1]);$address=[];foreach(['result','house','block','block_type','house_fias_id','region_iso_code','fias_level','fias_actuality_state']as$k)$address[$k]=$canonical[$k];$decisions[]=['decisionId'=>sprintf('17171717-1717-4171-8171-%012d',++$ordinal),'requestHash'=>$r['request_hash'],'responseHash'=>$r['response_hash'],'evidenceSha256'=>str_repeat('a',64),'address'=>$address];}
 assertSameValue(2,count($decisions),'A17 two independent resolutions');$id=$preview['packageId'];$reason='Owner confirmed contract; verified address evidence';
 $before=$f->business();
 foreach(['hash','house','region','duplicate','reason']as$variant){$bad=$decisions;$why=$reason;if($variant==='hash')$bad[1]['responseHash']=str_repeat('f',64);if($variant==='house')$bad[1]['address']['house']='999';if($variant==='region')$bad[1]['address']['region_iso_code']='RU-MOS';if($variant==='duplicate')$bad[1]['decisionId']=$bad[0]['decisionId'];if($variant==='reason')$why='';$r=$f->owner->confirmAddresses($id,$bad,$why);assertSameValue('MANUAL_ADDRESS_CONFIRMATION_INVALID',$r['reasonCode'],'A17 all-or-none invalid '.$variant);assertSameValue([],$f->owner->report($id)['manualAddressConfirmations']??[],'A17 no partial decisions');}
 assertSameValue('AUTHORIZATION_DENIED',$f->make('dev',95)->confirmAddresses($id,$decisions,$reason)['reasonCode'],'A17 authorization');assertSameValue('rejected',$f->make('prod')->confirmAddresses($id,$decisions,$reason)['status'],'A17 target');
 $confirmed=$f->owner->confirmAddresses($id,$decisions,$reason);assertSameValue('confirmed',$confirmed['status'],'A17 explicit decision');$records=$f->owner->report($id)['manualAddressConfirmations'];assertSameValue(2,count($records),'A17 two durable decisions');foreach($records as$r){assertSameValue(18,$r['actorId'],'A17 trusted actor');assertSameValue($reason,$r['reason'],'A17 reason');assertSameValue(true,isset($r['confirmedAt']),'A17 timestamp');}
 assertSameValue('confirmed',$f->owner->confirmAddresses($id,$decisions,$reason)['status'],'A17 identical confirmation replay');assertSameValue($records,$f->owner->report($id)['manualAddressConfirmations'],'A17 no duplicate audit');
 $changed=$decisions;$changed[0]['evidenceSha256']=str_repeat('b',64);assertSameValue('rejected',$f->owner->confirmAddresses($id,$changed,$reason)['status'],'A17 replacement forbidden');assertSameValue($before,$f->business(),'A17 confirmation no domainwrites');
 $still=$f->owner->resume($id);assertSameValue('IDENTITY_CONFLICT',$still['reasonCode'],'A17 existing wrong address still blocks');assertSameValue(3,$f->provider->calls,'A17 manualresume no calls');
 $edit=ProductionObjectDetailsEditFactory::create($f->db,$f->p,fn():string=>$f->now);
 $result=$edit->edit(new ObjectDetailsEditCommand('17171717-1717-4171-8171-000000000099',4512,18,0,['address'=>'г Москва, ул Тестовая, д 10']));assertSameValue('applied',$result['status'],'A17 public address correction');
 assertSameValue('г Москва, ул Тестовая, д 9',$f->db->query("SELECT ordadr_address FROM {$f->p}fm_maintable WHERE id=4512")->fetch_column(),'A17 legacy raw unchanged');
 $ready=$f->owner->resume($id);assertSameValue('ready',$ready['status'],'A17 effective edited address resolves sameobject');assertSameValue(0,$ready['summary']['errors'],'A17 errors cleared after correction');assertSameValue([],AddressRegisterWorkbookFixture::items($ready['errors']),'A17 stale rejected errors removed');assertSameValue(2,$ready['summary']['created'],'A17 only two new objects');
 assertSameValue('rejected',$f->owner->confirmAddresses($id,$decisions,$reason)['status'],'A17 ready fingerprint cannot change');
 $objects=AddressRegisterWorkbookFixture::items($ready['objects']);$wrong=array_values(array_filter($objects,fn($x)=>$x['fields']['regnumber']==='MAN-NEW-12'))[0];assertSameValue(null,$wrong['evidence']['house_fias_id'],'A17 raw negative preserved');assertSameValue(1,$wrong['evidence']['qc'],'A17 QC not forged');assertSameValue(AddressRegisterWorkbookFixture::address('12')['house_fias_id'],$wrong['manualAddressConfirmation']['address']['house_fias_id'],'A17 separate canonical decision');
 $done=$f->owner->apply($id,$ready['previewSha256'],'17171717-1717-4171-8171-000000000100');assertSameValue('applied',$done['status'],'A17 whole package applied');assertSameValue(4512,$f->id('TEST-4512'),'A17 existing identity preserved');assertSameValue(3,(int)$f->db->query("SELECT COUNT(*) FROM {$f->p}fm2_address_import_objects")->fetch_column(),'A17 all three bound');
 $r=$f->db->query("SELECT house_fias_id,source_json FROM {$f->p}fm2_address_import_objects WHERE registration_number='MAN-NEW-12'")->fetch_assoc();assertSameValue(AddressRegisterWorkbookFixture::address('12')['house_fias_id'],$r['house_fias_id'],'A17 bind corrected FIASHOUSE not rejected raw');assertSameValue($wrong['manualAddressConfirmation'],json_decode($r['source_json'],true)['manualAddressConfirmation'],'A17 decision preserved in sourceaudit');
 assertSameValue($cache,$f->db->query("SELECT * FROM {$f->p}fm2_address_import_cache ORDER BY entry_id")->fetch_all(MYSQLI_ASSOC),'A17 every raw cache byte immutable');assertSameValue(3,$f->provider->calls,'A17 no new payments');
 $after=$f->business();$again=$f->owner->apply($id,$ready['previewSha256'],'17171717-1717-4171-8171-000000000100');assertSameValue('replayed',$again['status'],'A17 apply replay');assertSameValue($after,$f->business(),'A17 no duplicate objects/history');
 echo "PASS ADDRESS-REGISTER-IMPORT-001 A17 manual confirmation/effective identity/apply/replay\n";
}finally{if($f)$f->close();}
