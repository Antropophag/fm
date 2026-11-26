<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__).'/Support/AddressRegisterWorkbookFixture.php';
use FMonitor2\InstallationProcess\AddressRegisterAddressValidation;
use FMonitor2\InstallationProcess\AddressRegisterDadataProvider;
assertSameValue(true,class_exists(AddressRegisterAddressValidation::class),'INTENDED_RED ADDRESS-REGISTER-IMPORT-001 A03 provider validation');
$source='г Москва, ул Тестовая, д 10';$valid=AddressRegisterWorkbookFixture::address();
$accepted=AddressRegisterAddressValidation::validate($source,$valid);assertSameValue(true,$accepted['accepted'],'specific house accepted');assertSameValue([$valid['result'],$valid['house_fias_id']],[$accepted['address']??null,$accepted['houseFiasId']??null],'confirmed canonical address and identity returned');
foreach([['qc'=>1],['qc'=>3],['qc_house'=>10],['fias_level'=>'7'],['house_fias_id'=>null],['fias_actuality_state'=>'1'],['unparsed_parts'=>'непонятное'],['house'=>'11'],['block'=>'2','block_type'=>'к'],['region_iso_code'=>'RU-MOS'],['flat'=>'1']]as$patch){$r=AddressRegisterAddressValidation::validate($source,array_replace($valid,$patch));assertSameValue(false,$r['accepted'],'address mismatch '.json_encode($patch));}
$calls=[];$transport=static function(string$url,array$headers,string$body,array$policy)use(&$calls,$valid):array{$calls[]=[$url,$headers,$body,$policy];return['status'=>200,'body'=>json_encode([$valid],JSON_UNESCAPED_UNICODE)];};
$p=new AddressRegisterDadataProvider('fake-key','fake-secret',$transport);assertSameValue($valid,$p->clean($source),'transport response');assertSameValue('https://cleaner.dadata.ru/api/v1/clean/address',$calls[0][0],'fixed HTTPS endpoint');assertSameValue([$source],json_decode($calls[0][2],true),'one address payload');assertSameValue(true,$calls[0][3]===['connectTimeout'=>5,'totalTimeout'=>15,'maxResponseBytes'=>1048576,'verifyTls'=>true,'followRedirects'=>false],'bounded timeout');
foreach([401,403,429,500,599,600]as$status){$n=0;$p=new AddressRegisterDadataProvider('private-key','private-secret',static function()use(&$n,$status):array{$n++;return['status'=>$status,'body'=>'sensitive-error-response'];});$failed=false;try{$p->clean($source);}catch(Throwable$e){$failed=true;assertSameValue(false,str_contains($e->getMessage(),'private-')||str_contains($e->getMessage(),'sensitive-error'),'redacted transport failure');assertSameValue(true,$e instanceof FMonitor2\InstallationProcess\AddressRegisterProviderFailure,'typed provider failure');assertSameValue(match($status){401,403=>'PROVIDER_AUTH_FAILED',429=>'PROVIDER_RATE_LIMIT',default=>'PROVIDER_UNAVAILABLE'},$e->reasonCode,'exact HTTP classification');assertSameValue($status===429||($status>=500&&$status<=599),$e->retryable,'only rate-limit/server errors retryable');assertSameValue(false,$e->outcomeUnknown,'known HTTP response');}assertSameValue(true,$failed,'HTTP failure propagated');assertSameValue(1,$n,'provider is single attempt; retry owned by budgeted application');if(in_array($status,[401,403],true))assertSameValue(1,$n,'auth failure no retry');}
foreach([['result'=>''],['room'=>'2'],['house_fias_id'=>'not-uuid']]as$patch)assertSameValue(false,AddressRegisterAddressValidation::validate($source,array_replace($valid,$patch))['accepted'],'missing canonical identity');
foreach([
 ['г Москва, ул Тестовая, д 10 к 2',array_replace($valid,['block'=>'2','block_type'=>'к']),true],
 ['г Москва, ул Тестовая, д 10 с 2',array_replace($valid,['block'=>'2','block_type'=>'к']),false],
 ['г Москва, ул Тестовая, д 10 к 2',array_replace($valid,['block'=>'3','block_type'=>'к']),false],
 ['г Москва, г Зеленоград, к.106',array_replace(AddressRegisterWorkbookFixture::address('106'),['result'=>'г Москва, г Зеленоград, к 106']),true],
 ['г Москва, ул Тестовая, д 10 (подъезды 1-4)',$valid,true],
 ['г Москва, ул Тестовая, д 10 или 11',$valid,false],
]as[$input,$response,$expected])assertSameValue($expected,AddressRegisterAddressValidation::validate($input,$response)['accepted'],'component identity '.$input);
foreach(['{}','[]','[{},{}]','invalid',str_repeat('x',1048577)]as$body){$p=new AddressRegisterDadataProvider('private-key','private-secret',static fn()=>['status'=>200,'body'=>$body]);$failed=false;try{$p->clean($source);}catch(Throwable$e){$failed=true;assertSameValue(false,str_contains($e->getMessage(),'private-'),'no key in response failure');assertSameValue(true,$e instanceof FMonitor2\InstallationProcess\AddressRegisterProviderFailure,'typed invalid response');assertSameValue('PROVIDER_RESPONSE_INVALID',$e->reasonCode,'invalid response classification');assertSameValue(false,$e->retryable,'invalid response not retried');}assertSameValue(true,$failed,'malformed or oversized response');}
$calls=0;$p=new AddressRegisterDadataProvider('private-key','private-secret',static function()use(&$calls){$calls++;throw new RuntimeException('transport might include private-key');});try{$p->clean($source);throw new TestFailure('timeout accepted');}catch(FMonitor2\InstallationProcess\AddressRegisterProviderFailure$e){assertSameValue('PROVIDER_OUTCOME_UNKNOWN',$e->reasonCode,'unknown transport classified');assertSameValue(true,$e->outcomeUnknown,'possibly paid');assertSameValue(false,str_contains($e->getMessage(),'private-key'),'transport exception redacted');}assertSameValue(1,$calls,'no invisible retry');
echo "PASS ADDRESS-REGISTER-IMPORT-001 A03 provider\n";

// A16: trusted Moscow context belongs on the wire, original input stays unchanged.
$wire=[];$moscow=new AddressRegisterDadataProvider('fake-key','fake-secret',static function($u,$h,$b,$p)use(&$wire,$valid){$wire[]=json_decode($b,true);return ['status'=>200,'body'=>json_encode([$valid])];});
foreach(['Тестовая ул. 10','Зеленоград г. к.110','Тестовый пос. 22','г Москва, ул Тестовая, д 10'] as $input){$original=$input;$moscow->clean($input);assertSameValue($original,$input,'A16 source immutable');}
assertSameValue([['г Москва, Тестовая ул. 10'],['г Москва, Зеленоград г. к.110'],['г Москва, Тестовый пос. 22'],['г Москва, ул Тестовая, д 10']],$wire,'A16 transmit Moscow once');
foreach([
 ['Тестовая ул. 10','10',null,null],['Тестовая ул. 10Б','10Б',null,null],
 ['Тестовая ул. 2/12','2/12',null,null],['Тестовая ул. 14-18','14-18',null,null],['Тестовая ул. 7/9-13','7/9-13',null,null],
 ['Тестовая ул. 10 к.2','10','2','к'],['Тестовая ул. 10 к.Б','10','Б','к'],['Тестовая ул. 10 с.2','10','2','с'],
 ['Зеленоград г. к.110','110',null,null],['Квартал 38-й Юго-Запада к.5','5',null,null],
 ['Микрорайон 2 (Тестовый), д.44','44',null,null],['Тестовая ул. 10 (подъезды 1-4)','10',null,null],
]as[$input,$house,$block,$type]){
 $response=array_replace($valid,['house'=>$house,'block'=>$block,'block_type'=>$type]);
 assertSameValue(true,AddressRegisterAddressValidation::validate($input,$response)['accepted'],'A16 exact grammar '.$input);
 assertSameValue(false,AddressRegisterAddressValidation::validate($input,array_replace($response,['house'=>'999']))['accepted'],'A16 no neighbor '.$input);
 foreach([['qc'=>1],['region_iso_code'=>'RU-MOS'],['flat'=>'1'],['room'=>'1'],['unparsed_parts'=>'?']]as$patch)assertSameValue(false,AddressRegisterAddressValidation::validate($input,array_replace($response,$patch))['accepted'],'A16 guard unchanged');
}
foreach(['Тестовая ул. 10 или 11','Тестовая ул. 10\\11','Тестовая ул. д 11 д 10','Тестовая ул. 10, кв. 2','Тестовая ул. 10 к.2 с.3']as$input)assertSameValue(false,AddressRegisterAddressValidation::validate($input,$valid)['accepted'],'A16 ambiguous rejected '.$input);
echo "PASS ADDRESS-REGISTER-IMPORT-001 A16 Moscow wire/grammar\n";
// A16 independent-review regression: a different suffix must not masquerade as house.
foreach([
 ['ул Тестовая, 10, кв 2','2'],['ул Тестовая, 10, 12','12'],
 ['Зеленоград, д 10, к 2','2'],['Квартал 38-й Юго-Запада д 10 к.5','5'],
 ['ул Тестовая 10 офис 2','2'],['ул Тестовая 10 помещение 2','2'],
]as[$input,$house])assertSameValue(false,AddressRegisterAddressValidation::validate($input,array_replace($valid,['house'=>$house]))['accepted'],'A16 no suffix reinterpretation '.$input);
echo "PASS ADDRESS-REGISTER-IMPORT-001 A16 ambiguity guard\n";
assertSameValue(false,AddressRegisterAddressValidation::validate('ул Тестовая 10, 12',array_replace($valid,['house'=>'12']))['accepted'],'A16 unmarked competing house without prior comma');
assertSameValue(false,AddressRegisterAddressValidation::validate('ул Тестовая 10 12',array_replace($valid,['house'=>'12']))['accepted'],'A16 whitespace competing houses');
assertSameValue(true,AddressRegisterAddressValidation::validate('Тестовая ул. 10 с.2',array_replace($valid,['block'=>'2','block_type'=>'стр']))['accepted'],'A16 official structure abbreviation');
assertSameValue(false,AddressRegisterAddressValidation::validate('Тестовая ул. 10 с.2',array_replace($valid,['block'=>'2','block_type'=>'соор']))['accepted'],'A16 structure is not any c-prefixed block type');
foreach(['г Москва ул Тестовая д 10','Москва ул Тестовая 10']as$input){$moscow->clean($input);assertSameValue([$input],$wire[array_key_last($wire)],'A16 qualified Moscow without comma unchanged');}
$moscow->clean('Московская ул. 10');assertSameValue(['г Москва, Московская ул. 10'],$wire[array_key_last($wire)],'A16 street name is not city qualifier');
