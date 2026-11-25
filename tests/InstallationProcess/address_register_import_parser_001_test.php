<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require dirname(__DIR__).'/Support/AddressRegisterWorkbookFixture.php';
use FMonitor2\InstallationProcess\AddressRegisterWorkbook;
assertSameValue(true,class_exists(AddressRegisterWorkbook::class),'INTENDED_RED ADDRESS-REGISTER-IMPORT-001 A02 workbook seam');
$dir=sys_get_temp_dir().'/address-parser-'.bin2hex(random_bytes(6));mkdir($dir,0700);$path=$dir.'/input.xlsx';
try{
 $a=AddressRegisterWorkbookFixture::row('00012-А');$b=AddressRegisterWorkbookFixture::row('T-2');$b['B']='2028';$c=AddressRegisterWorkbookFixture::row('T-3');$c['H']='2';
 AddressRegisterWorkbookFixture::write($path,[$a,$b,$c]);$result=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');
 assertSameValue([],$result['errors'],'valid source');assertSameValue(2,count($result['objects']),'year selection ignores hidden/filter');assertSameValue(1,$result['excluded'],'other year excluded');
 $fields=$result['objects'][0]['fields'];foreach(['regnumber'=>'00012-А','unom'=>'70001','entrance'=>'1а','weight'=>400,'speed'=>'1','frequencyDoor'=>true,'frequencyMain'=>false,'plannedStartDate'=>null,'plannedFinishDate'=>null,'equipmentRequestDate'=>'2026-12-01']as$k=>$expected)assertSameValue($expected,$fields[$k]??null,'literal mapping '.$k);
 assertSameValue('0.70999999999999897',$result['objects'][0]['raw']['O'],'raw numerical precision preserved');
 $expectedFields=['year','regnumber','unom','sourceArea','sourceDistrict','address','entrance','floors','weight','pittype','pitmaterial','cabinDoor','shaftDoor','speed','frequencyDoor','frequencyMain','designPeriodRaw','installationDays','equipmentRequestDate','plannedStartDate','plannedFinishDate'];assertSameValue($expectedFields,array_keys($fields),'exact 21-field public mapping');
 foreach([
  [['C5'=>'Wrong heading'],[],'FORMAT_INVALID'],
  [['AH7'=>'unmapped'],[],'FORMAT_UNSUPPORTED'],
  [['Q7'=>'not a number'],[],'VALUE_INVALID'],
  [['AB7'=>'31.02.2027'],[],'VALUE_INVALID'],
  [['AB7'=>'2027-03-20','AC7'=>'2027-03-01'],[],'VALUE_INVALID'],
  [[],['Q7'=>'SUM(1,2)'],'FORMAT_UNSUPPORTED'],
  [['R7'=>'Unknown shaft'],[],'VALUE_INVALID'],
 ]as[$overrides,$formula,$code]){AddressRegisterWorkbookFixture::write($path,[$a],$overrides,$formula);$bad=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');assertSameValue(true,in_array($code,array_column($bad['errors'],'reasonCode'),true),'reject '.$code);}
 AddressRegisterWorkbookFixture::write($path,[$a],['L7'=>'','AF7'=>'0','AB7'=>'2027-02-15']);$good=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');assertSameValue([],$good['errors'],'optional old material missing allowed');assertSameValue(false,array_key_exists('discountedDesignCost',$good['objects'][0]['fields']),'excluded zero is not a requisite');
 assertSameValue(true,count(AddressRegisterWorkbook::read($path,2029,'TEST-CONTRACT')['errors'])>0,'empty selection rejected');
 assertSameValue(true,count(AddressRegisterWorkbook::read($path,2027,'OTHER')['errors'])>0,'contract mismatch');
 // Independent OOXML storage/epoch and value boundary matrix.
 foreach([false,true]as$epoch1904){AddressRegisterWorkbookFixture::write($path,[$a],['AB7'=>'dummy']);AddressRegisterWorkbookFixture::numericCell($path,'AB7',$epoch1904?'44971':'46433',true);if($epoch1904)AddressRegisterWorkbookFixture::replaceEntry($path,'xl/workbook.xml',static fn($xml)=>str_replace('<sheets>','<workbookPr date1904="1"/><sheets>',$xml));$r=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');assertSameValue([],$r['errors'],'supported Excel epoch');assertSameValue('2027-02-15',$r['objects'][0]['fields']['plannedStartDate'],'literal serial date');}
 AddressRegisterWorkbookFixture::write($path,[$a]);AddressRegisterWorkbookFixture::replaceEntry($path,'xl/sharedStrings.xml',static fn()=>'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>00012-А</t></si></sst>');AddressRegisterWorkbookFixture::replaceEntry($path,'xl/worksheets/sheet1.xml',static fn($xml)=>preg_replace('/<c r="C7"[^>]*>.*?<\/c>/s','<c r="C7" t="s"><v>0</v></c>',$xml));assertSameValue('00012-А',AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['objects'][0]['fields']['regnumber'],'shared string identity');
 foreach(['999999999999999','1000000000000000','12.5']as$num){AddressRegisterWorkbookFixture::write($path,[$a]);AddressRegisterWorkbookFixture::numericCell($path,'C7',$num);$r=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');if(strlen($num)===15)assertSameValue($num,$r['objects'][0]['fields']['regnumber'],'15 digit exact ID');else assertSameValue(true,count($r['errors'])>0,'unsafe numeric ID');}
 foreach([
  ['P7','0'],['P7','201'],['Q7','100001'],['V7','0'],['V7','20.01'],['Z7','3661'],['Z7','1.5'],['W7','maybe'],['H7',str_repeat('x',81)],['C7',str_repeat('x',121)],['B7','not-year'],['C7',"a\x01b"],['C7',''],
 ]as[$ref,$value]){AddressRegisterWorkbookFixture::write($path,[$a],[$ref=>$value]);$bad=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');assertSameValue(true,count($bad['errors'])>0,'boundary rejected '.$ref.' '.json_encode($value));if(!str_contains($value,"\x01"))assertSameValue(7,$bad['errors'][0]['row'],'addressable bad row');}
 AddressRegisterWorkbookFixture::write($path,[$a],['AD7'=>'999999999999.994','AF7'=>'0','V7'=>'1.005','W7'=>'НЕТ','AB7'=>'2028-02-29']);$r=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');assertSameValue([],$r['errors'],'exact valid decimals and leap date');assertSameValue('1.01',$r['objects'][0]['fields']['speed'],'decimal half up');assertSameValue(false,$r['objects'][0]['fields']['frequencyDoor'],'case insensitive false');
 // A13: the unchanged file keeps all excluded columns, but their domain values are ignored.
 $excludedColumns=['I','J','K','L','M','N','O','AD','AE','AF','AG'];
 foreach(['arbitrary text','-999999999999999999999','']as$ignored){$cells=[];foreach($excludedColumns as$column)$cells[$column.'7']=$ignored;AddressRegisterWorkbookFixture::write($path,[$a],$cells);$r=AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT');assertSameValue([],$r['errors'],'excluded values do not undergo domain validation');assertSameValue($expectedFields,array_keys($r['objects'][0]['fields']),'only retained columns become fields');assertSameValue(45,$r['objects'][0]['fields']['installationDays'],'Z duration retained');if($ignored!=='')foreach($excludedColumns as$column)assertSameValue($ignored,$r['objects'][0]['raw'][$column],'excluded raw retained '.$column);}
 foreach(['I5','AD5','AF5','O6','AG6']as$header){AddressRegisterWorkbookFixture::write($path,[$a],[$header=>'wrong']);assertSameValue('FORMAT_INVALID',AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'][0]['reasonCode'],'full original header still required '.$header);}
 foreach(['I7','AD7']as$cell){AddressRegisterWorkbookFixture::write($path,[$a],[],[$cell=>'1+1']);assertSameValue('FORMAT_UNSUPPORTED',AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'][0]['reasonCode'],'excluded formulas still rejected');}
 // A style number alone is not proof that a numeric cell is a date.
 AddressRegisterWorkbookFixture::write($path,[$a],['AB7'=>'dummy']);AddressRegisterWorkbookFixture::numericCell($path,'AB7','46433',true);AddressRegisterWorkbookFixture::replaceEntry($path,'xl/styles.xml',static fn($xml)=>str_replace('numFmtId="14"','numFmtId="2"',$xml));assertSameValue(true,count(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'])>0,'decimal style cannot fabricate a date');
 foreach([
  static fn($xml)=>str_replace('</sheetData>','<row r="8"><c r="B8">',$xml),
  static fn($xml)=>preg_replace('/(<row r="7">.*?<\/row>)/s','$1$1',$xml),
  static fn($xml)=>preg_replace('/(<c r="C7"[^>]*>.*?<\/c>)/s','$1$1',$xml),
 ]as$malformed){AddressRegisterWorkbookFixture::write($path,[$a]);AddressRegisterWorkbookFixture::replaceEntry($path,'xl/worksheets/sheet1.xml',$malformed);assertSameValue(true,count(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'])>0,'truncated or duplicate data rejected without silent row loss');}
 // Footer formulas are not data, an unlabelled data row must not disappear.
 AddressRegisterWorkbookFixture::write($path,[$a],['E12'=>'Итого по лоту:','G12'=>'42'],['G12'=>'SUBTOTAL(9,AD7:AE7)']);assertSameValue([] ,AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'footer ignored');
 AddressRegisterWorkbookFixture::write($path,[$a],['B7'=>'']);assertSameValue(true,count(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'])>0,'malformed row retained');
 foreach([
  ['xl/_rels/workbook.xml.rels',static fn($xml)=>str_replace('Target="worksheets/sheet1.xml"','Target="file:///etc/passwd" TargetMode="External"',$xml)],
  ['xl/worksheets/sheet1.xml',static fn($xml)=>str_replace('<worksheet','<!DOCTYPE worksheet [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><worksheet',$xml)],
  ['xl/vbaProject.bin',static fn()=>'macro'],
  ['xl/externalLinks/externalLink1.xml',static fn()=>'<externalLink/>'],
 ]as[$entry,$replace]){AddressRegisterWorkbookFixture::write($path,[$a]);AddressRegisterWorkbookFixture::replaceEntry($path,$entry,$replace);assertSameValue(true,count(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'])>0,'unsafe package rejected '.$entry);}
 AddressRegisterWorkbookFixture::write($path,[$a],['G7'=>str_repeat('x',32769)]);assertSameValue(true,in_array('INPUT_LIMIT_EXCEEDED',array_column(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'reasonCode'),true),'cell bound');
 AddressRegisterWorkbookFixture::write($path,[$a]);$zip=new ZipArchive();$zip->open($path);for($i=0;$i<2049;$i++)$zip->addFromString('extra/'.$i.'.xml','<a/>');$zip->close();assertSameValue(true,in_array('INPUT_LIMIT_EXCEEDED',array_column(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'reasonCode'),true),'entry bound');
 AddressRegisterWorkbookFixture::write($path,[$a]);AddressRegisterWorkbookFixture::replaceEntry($path,'xl/worksheets/sheet1.xml',static fn($xml)=>str_replace('</sheetData>','<row r="20007"><c r="B20007" t="inlineStr"><is><t>2027</t></is></c></row></sheetData>',$xml));assertSameValue(true,in_array('INPUT_LIMIT_EXCEEDED',array_column(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'reasonCode'),true),'row index bound');
 AddressRegisterWorkbookFixture::write($path,[$a],['BM7'=>'outside']);assertSameValue(true,in_array('INPUT_LIMIT_EXCEEDED',array_column(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'reasonCode'),true),'column bound');
 // Actual highly compressible member: exact expanded limit accepted, one byte over rejected.
 AddressRegisterWorkbookFixture::write($path,[$a]);$z=new ZipArchive();$z->open($path);$total=0;for($i=0;$i<$z->numFiles;$i++)$total+=$z->statIndex($i)['size'];$padding=$dir.'/padding.xml';$h=fopen($padding,'wb');$remaining=128*1024*1024-$total;while($remaining>0){$chunk=str_repeat(' ',min(65536,$remaining));fwrite($h,$chunk);$remaining-=strlen($chunk);}fclose($h);$z->addFile($padding,'xl/unused-padding.xml');$z->close();assertSameValue([] ,AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'expanded limit inclusive without materializing unused parts');file_put_contents($padding,' ',FILE_APPEND);$z=new ZipArchive();$z->open($path);$z->addFile($padding,'xl/unused-padding.xml');$z->close();assertSameValue(true,in_array('INPUT_LIMIT_EXCEEDED',array_column(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'reasonCode'),true),'expanded limit one byte over');unlink($padding);
 // A tiny compressed ZIP advertises an expanded member beyond the cap; inspect metadata before allocating.
 AddressRegisterWorkbookFixture::write($path,[$a]);$bytes=file_get_contents($path);$central=strpos($bytes,"PK\x01\x02");assertSameValue(true,$central!==false,'ZIP central directory fixture');$bytes=substr_replace($bytes,pack('V',128*1024*1024+1),$central+24,4);file_put_contents($path,$bytes);assertSameValue(true,in_array('INPUT_LIMIT_EXCEEDED',array_column(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'reasonCode'),true),'expanded size cap before inflate');
 file_put_contents($path,str_repeat('x',20*1024*1024+1));assertSameValue(true,in_array('INPUT_LIMIT_EXCEEDED',array_column(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'],'reasonCode'),true),'compressed size bound');
 file_put_contents($path,'not a workbook');assertSameValue(true,count(AddressRegisterWorkbook::read($path,2027,'TEST-CONTRACT')['errors'])>0,'malformed archive');
 echo "PASS ADDRESS-REGISTER-IMPORT-001 A02 parser\n";
}finally{if(is_file($dir.'/padding.xml'))unlink($dir.'/padding.xml');unlink($path);rmdir($dir);}
