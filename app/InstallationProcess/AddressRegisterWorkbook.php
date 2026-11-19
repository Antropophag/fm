<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class AddressRegisterWorkbook
{
    private const MAX_FILE=20971520, MAX_EXPANDED=134217728, MAX_ENTRIES=2048, MAX_ROWS=20000, MAX_COLUMNS=64, MAX_CELL=32768;
    private const FIELDS=['B'=>'year','C'=>'regnumber','D'=>'unom','E'=>'sourceArea','F'=>'sourceDistrict','G'=>'address','H'=>'entrance','I'=>'existingFloors','J'=>'existingWeight','K'=>'existingPitType','L'=>'existingPitMaterial','M'=>'existingCabinDoor','N'=>'existingShaftDoor','O'=>'existingSpeed','P'=>'floors','Q'=>'weight','R'=>'pittype','S'=>'pitmaterial','T'=>'cabinDoor','U'=>'shaftDoor','V'=>'speed','W'=>'frequencyDoor','X'=>'frequencyMain','Y'=>'designPeriodRaw','Z'=>'installationDays','AA'=>'equipmentRequestDate','AB'=>'plannedStartDate','AC'=>'plannedFinishDate','AD'=>'designCost','AE'=>'replacementCost','AF'=>'discountedDesignCost','AG'=>'discountedReplacementCost'];
    private const HEADER5=['A'=>'№ п/п','B'=>'Год замены','C'=>'Рег №','D'=>'UNOM','E'=>'Административный округ','F'=>'Район','G'=>'Адрес многоквартирного дома','H'=>'Подъезд','I'=>'Характеристики существующих лифтов','P'=>'Характеристики устанавливаемых лифтов*','Y'=>'Сроки начала и окончания работ по разработке проектной документации','Z'=>'Количество дней на установку лифта','AB'=>'Дата начала производства работ','AC'=>'Дата окончания производства работ','AD'=>'Стоимость, руб.','AF'=>'Стоимость со снижением, руб'];
    private const HEADER6=['I'=>'Этажность здания','J'=>'Грузоподъемность, кг','K'=>'Тип шахты','L'=>'Материл стен шахты','M'=>'Тип дверей кабины','N'=>'Тип дверей шахты','O'=>'Скорость','P'=>'Этажность здания','Q'=>'Грузоподъемность, кг','R'=>'Тип шахты','S'=>'Материл стен шахты','T'=>'Тип дверей кабины','U'=>'Тип дверей шахты','V'=>'Скорость','W'=>'Наличие частотного преобразователя привода дверей','X'=>'Наличие частотного преобразователя главного привода','AA'=>'дата подачи заявки на оборудование','AD'=>'стоимость проектной документации','AE'=>'стоимость работ по замене лифтового оборудования**','AF'=>'стоимость проектной документации','AG'=>'стоимость работ по замене лифтового оборудования'];

    /** @return array{objects:list<array<string,mixed>>,excluded:int,errors:list<array<string,mixed>>} */
    public static function read(string $path,int $year,string $contract):array
    {
        $result=['objects'=>[],'excluded'=>0,'errors'=>[]];
        $counts=self::scan(
            $path,$year,$contract,
            static function(array $object)use(&$result):void{$result['objects'][]=$object;},
            static function(array $error)use(&$result):void{$result['errors'][]=$error;}
        );
        $result['excluded']=$counts['excluded'];
        if($counts['errors']>0)$result['objects']=[];
        return $result;
    }

    /** @return array{selected:int,excluded:int,errors:int} */
    public static function scan(string $path,int $year,string $contract,callable $onObject,callable $onError):array
    {
        $counts=['selected'=>0,'excluded'=>0,'errors'=>0];
        try{self::scanInto($path,$year,$contract,$onObject,$onError,$counts);}
        catch(WorkbookFailure $e){$counts['errors']++;$onError(self::error($e));}
        catch(\Throwable){$counts['errors']++;$onError(['reasonCode'=>'FORMAT_INVALID','row'=>null,'message'=>'Некорректный файл']);}
        return$counts;
    }

    private static function scanInto(string $path,int $year,string $contract,callable $onObject,callable $onError,array &$counts):void
    {
        if (!is_file($path) || filesize($path)===false || filesize($path)>self::MAX_FILE) throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED');
        $zip=new \ZipArchive(); if($zip->open($path)!==true)throw new WorkbookFailure('FORMAT_INVALID');
        try {
            if($zip->numFiles>self::MAX_ENTRIES)throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED');
            $expanded=0;$advertised=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i); if(!is_array($stat))throw new WorkbookFailure('FORMAT_INVALID');
                $advertised+=(int)$stat['size'];if($advertised>self::MAX_EXPANDED)throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED');
                $name=(string)$stat['name'];
                if(str_contains($name,'..')||str_starts_with($name,'/')||preg_match('/(^|\/)(?:vbaProject\.bin|externalLinks)(?:\/|$)/i',$name))throw new WorkbookFailure('FORMAT_UNSUPPORTED');
                $stream=$zip->getStream($name);if($stream===false)throw new WorkbookFailure('FORMAT_INVALID');
                try{while(!feof($stream)){$chunk=fread($stream,65536);if($chunk===false)throw new WorkbookFailure('FORMAT_INVALID');$expanded+=strlen($chunk);if($expanded>self::MAX_EXPANDED)throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED');}}finally{fclose($stream);}
            }
            [$rid,$date1904]=self::workbookInfo($zip);$target=self::relationshipTarget($zip,$rid);if(!preg_match('#^(?:worksheets/)?[A-Za-z0-9_.-]+\.xml$#',$target))throw new WorkbookFailure('FORMAT_UNSUPPORTED');
            $sheetName=str_starts_with($target,'worksheets/')?'xl/'.$target:'xl/worksheets/'.$target;
            $shared=self::sharedStrings($zip);$dateStyles=self::dateStyles($zip);
            $headers=[];$headersChecked=false;
            foreach(self::sheetRows($zip,$sheetName,$shared)as$rowNo=>$cells){
                if($rowNo<7){$headers[$rowNo]=$cells;continue;}
                if(!$headersChecked){self::headers($headers);$header=(string)($headers[1]['AD']['value']??'');if(!preg_match('/договору\s+'.preg_quote($contract,'/').'(?:\s|$)/iu',$header))throw new WorkbookFailure('FORMAT_INVALID',1,'Contract mismatch');$headersChecked=true;}
                foreach($cells as$column=>$cell){$idx=self::columnNumber($column);if($idx>self::MAX_COLUMNS)throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED',$rowNo);if($idx>33 && trim($cell['value'])!=='')throw new WorkbookFailure('FORMAT_UNSUPPORTED',$rowNo);}
                if(self::footer($cells))continue;
                $candidate=trim($cells['B']['value']??'')!=='' || (trim($cells['A']['value']??'')!=='' && self::any($cells,['C','D','E','F','G','H']));
                if(!$candidate)continue;
                foreach($cells as$cell)if($cell['formula'])throw new WorkbookFailure('FORMAT_UNSUPPORTED',$rowNo,'Formula in data row');
                try{$fields=self::map($cells,$date1904,$dateStyles,$rowNo);}catch(WorkbookFailure $e){$counts['errors']++;$onError(self::error($e));continue;}
                if($fields['year']!==$year){$counts['excluded']++;continue;}
                $counts['selected']++;$onObject(['sourceRow'=>$rowNo,'fields'=>$fields,'raw'=>array_map(static fn($c)=>$c['value'],$cells)]);
            }
            if(!$headersChecked){self::headers($headers);$header=(string)($headers[1]['AD']['value']??'');if(!preg_match('/договору\s+'.preg_quote($contract,'/').'(?:\s|$)/iu',$header))throw new WorkbookFailure('FORMAT_INVALID',1,'Contract mismatch');}
            if($counts['errors']===0&&$counts['selected']===0)throw new WorkbookFailure('VALUE_INVALID',null,'No rows for selected year');
        } finally { $zip->close(); }
    }

    private static function error(WorkbookFailure $e):array{return['reasonCode'=>$e->reasonCode,'row'=>$e->row,'message'=>$e->getMessage()];}

    /** @return array{string,bool} */
    private static function workbookInfo(\ZipArchive $zip):array
    {
        $stream=$zip->getStream('xl/workbook.xml');if($stream===false)throw new WorkbookFailure('FORMAT_INVALID');$reader=null;$old=libxml_use_internal_errors(true);$rid=null;$date1904=false;
        try{$reader=\XMLReader::fromStream($stream,null,LIBXML_NONET|LIBXML_COMPACT|LIBXML_PARSEHUGE);while($reader->read()){self::rejectDoctype($reader);if($reader->nodeType!==\XMLReader::ELEMENT)continue;if($reader->localName==='workbookPr')$date1904=in_array($reader->getAttribute('date1904'),['1','true'],true);if($reader->localName==='sheet'&&$reader->getAttribute('name')==='Адресный перечень'){$rid=$reader->getAttributeNs('id','http://schemas.openxmlformats.org/officeDocument/2006/relationships');self::boundedMetadata($rid);}}if(libxml_get_errors()!==[]||!is_string($rid)||$rid==='')throw new WorkbookFailure('FORMAT_INVALID');return[$rid,$date1904];}
        finally{if($reader instanceof \XMLReader)$reader->close();fclose($stream);libxml_clear_errors();libxml_use_internal_errors($old);}
    }
    private static function relationshipTarget(\ZipArchive $zip,string $rid):string
    {
        $stream=$zip->getStream('xl/_rels/workbook.xml.rels');if($stream===false)throw new WorkbookFailure('FORMAT_INVALID');$reader=null;$old=libxml_use_internal_errors(true);$target=null;
        try{$reader=\XMLReader::fromStream($stream,null,LIBXML_NONET|LIBXML_COMPACT|LIBXML_PARSEHUGE);while($reader->read()){self::rejectDoctype($reader);if($reader->nodeType!==\XMLReader::ELEMENT||$reader->localName!=='Relationship')continue;$mode=$reader->getAttribute('TargetMode');$candidate=$reader->getAttribute('Target');self::boundedMetadata($mode);self::boundedMetadata($candidate);if(strcasecmp((string)$mode,'External')===0||preg_match('#^(?:[a-z]+:|/)#i',(string)$candidate))throw new WorkbookFailure('FORMAT_UNSUPPORTED');if($reader->getAttribute('Id')===$rid)$target=$candidate;}if(libxml_get_errors()!==[]||!is_string($target)||$target==='')throw new WorkbookFailure('FORMAT_INVALID');return$target;}
        finally{if($reader instanceof \XMLReader)$reader->close();fclose($stream);libxml_clear_errors();libxml_use_internal_errors($old);}
    }
    private static function rejectDoctype(\XMLReader $reader):void{if($reader->nodeType===\XMLReader::DOC_TYPE||$reader->nodeType===\XMLReader::ENTITY||$reader->nodeType===\XMLReader::ENTITY_REF)throw new WorkbookFailure('FORMAT_UNSUPPORTED');}
    private static function boundedMetadata(?string $value):void{if($value!==null&&strlen($value)>self::MAX_CELL)throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED');}
    private static function sharedStrings(\ZipArchive $zip):WorkbookSharedStrings
    {
        $out=new WorkbookSharedStrings();$stream=$zip->getStream('xl/sharedStrings.xml');if($stream===false)return$out;
        $reader=null;$old=libxml_use_internal_errors(true);
        try{$reader=\XMLReader::fromStream($stream,null,LIBXML_NONET|LIBXML_COMPACT|LIBXML_PARSEHUGE);$inSi=false;$siDepth=-1;$textDepth=-1;$value='';while($reader->read()){self::rejectDoctype($reader);if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='si'&&$reader->namespaceURI==='http://schemas.openxmlformats.org/spreadsheetml/2006/main'){$inSi=true;$siDepth=$reader->depth;$value='';continue;}if(!$inSi)continue;if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='t'){$textDepth=$reader->depth;continue;}if($textDepth>=0&&in_array($reader->nodeType,[\XMLReader::TEXT,\XMLReader::CDATA,\XMLReader::SIGNIFICANT_WHITESPACE,\XMLReader::WHITESPACE],true))self::appendBounded($value,$reader->value);if($reader->nodeType===\XMLReader::END_ELEMENT&&$reader->depth===$textDepth&&$reader->localName==='t')$textDepth=-1;if($reader->nodeType===\XMLReader::END_ELEMENT&&$reader->depth===$siDepth&&$reader->localName==='si'){$out->add($value);$inSi=false;$siDepth=-1;$textDepth=-1;}}if(libxml_get_errors()!==[]||$inSi)throw new WorkbookFailure('FORMAT_INVALID');return$out;}
        finally{if($reader instanceof \XMLReader)$reader->close();fclose($stream);libxml_clear_errors();libxml_use_internal_errors($old);}
    }
    private static function dateStyles(\ZipArchive $zip):WorkbookDateStyles
    {
        $out=new WorkbookDateStyles();$stream=$zip->getStream('xl/styles.xml');if($stream===false)return$out;$reader=null;$old=libxml_use_internal_errors(true);$custom=new WorkbookDateFormatIds();$inCellXfs=false;$cellXfsDepth=-1;
        try{$reader=\XMLReader::fromStream($stream,null,LIBXML_NONET|LIBXML_COMPACT|LIBXML_PARSEHUGE);while($reader->read()){self::rejectDoctype($reader);if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='numFmt'){$id=(int)$reader->getAttribute('numFmtId');$format=(string)$reader->getAttribute('formatCode');self::boundedMetadata($format);$plain=preg_replace('/"[^"]*"|\\\\.|\[[^]]*]/u','',$format)??'';if(preg_match('/[dmy]/i',$plain))$custom->add($id);}if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='cellXfs'){$inCellXfs=true;$cellXfsDepth=$reader->depth;continue;}if($inCellXfs&&$reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='xf'){$id=(int)($reader->getAttribute('numFmtId')??0);$builtIn=($id>=14&&$id<=22)||($id>=27&&$id<=36)||($id>=45&&$id<=47)||($id>=50&&$id<=58);$out->add($builtIn||$custom->has($id));}if($reader->nodeType===\XMLReader::END_ELEMENT&&$reader->depth===$cellXfsDepth&&$reader->localName==='cellXfs')$inCellXfs=false;}if(libxml_get_errors()!==[])throw new WorkbookFailure('FORMAT_INVALID');return$out;}
        finally{if($reader instanceof \XMLReader)$reader->close();fclose($stream);libxml_clear_errors();libxml_use_internal_errors($old);}
    }
    /** @return \Generator<int,array<string,array{value:string,formula:bool,type:string,style:int}>> */
    private static function sheetRows(\ZipArchive $zip,string $name,WorkbookSharedStrings $shared):\Generator
    {
        $stream=$zip->getStream($name);if($stream===false)throw new WorkbookFailure('FORMAT_INVALID');$reader=null;$old=libxml_use_internal_errors(true);
        try{$reader=\XMLReader::fromStream($stream,null,LIBXML_NONET|LIBXML_COMPACT|LIBXML_PARSEHUGE);$seenRows=[];
            while($reader->read()){
                self::rejectDoctype($reader);
                if($reader->nodeType!==\XMLReader::ELEMENT||$reader->localName!=='row'||$reader->namespaceURI!=='http://schemas.openxmlformats.org/spreadsheetml/2006/main')continue;
                $n=(int)$reader->getAttribute('r');if($n<1||$n>self::MAX_ROWS)throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED',$n?:null);if(isset($seenRows[$n]))throw new WorkbookFailure('FORMAT_INVALID',$n);$seenRows[$n]=true;$seenColumns=[];
                $rowDepth=$reader->depth;$cells=[];if(!$reader->isEmptyElement)while($reader->read()){self::rejectDoctype($reader);if($reader->nodeType===\XMLReader::END_ELEMENT&&$reader->depth===$rowDepth&&$reader->localName==='row')break;if($reader->nodeType!==\XMLReader::ELEMENT||$reader->localName!=='c'||$reader->depth!==$rowDepth+1)continue;[$col,$cell]=self::readCell($reader,$n,$shared);if(isset($seenColumns[$col]))throw new WorkbookFailure('FORMAT_INVALID',$n);$seenColumns[$col]=true;if(self::columnNumber($col)>self::MAX_COLUMNS)throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED',$n);if($cell['value']!==''||$cell['formula'])$cells[$col]=$cell;}
                yield$n=>$cells;
            }
            if(libxml_get_errors()!==[])throw new WorkbookFailure('FORMAT_INVALID');
        }finally{if($reader instanceof \XMLReader)$reader->close();fclose($stream);libxml_clear_errors();libxml_use_internal_errors($old);}
    }
    /** @return array{string,array{value:string,formula:bool,type:string,style:int}} */
    private static function readCell(\XMLReader $reader,int $row,WorkbookSharedStrings $shared):array
    {
        $ref=$reader->getAttribute('r')??'';if(!preg_match('/^([A-Z]+)([1-9][0-9]*)$/',$ref,$m)||((int)$m[2])!==$row)throw new WorkbookFailure('FORMAT_INVALID',$row);$col=$m[1];$type=$reader->getAttribute('t')??'';$style=(int)($reader->getAttribute('s')??0);$depth=$reader->depth;$formula=false;$value='';$captureDepth=-1;$capture='';
        if(!$reader->isEmptyElement)while($reader->read()){self::rejectDoctype($reader);if($reader->nodeType===\XMLReader::END_ELEMENT&&$reader->depth===$depth&&$reader->localName==='c')break;if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName==='f'){$formula=true;continue;}if($reader->nodeType===\XMLReader::ELEMENT&&(($type==='inlineStr'&&$reader->localName==='t')||($type!=='inlineStr'&&$reader->localName==='v'))){$captureDepth=$reader->depth;$capture=$reader->localName;continue;}if($captureDepth>=0&&in_array($reader->nodeType,[\XMLReader::TEXT,\XMLReader::CDATA,\XMLReader::SIGNIFICANT_WHITESPACE,\XMLReader::WHITESPACE],true))self::appendBounded($value,$reader->value);if($reader->nodeType===\XMLReader::END_ELEMENT&&$reader->depth===$captureDepth&&$reader->localName===$capture){$captureDepth=-1;$capture='';}}
        if($type==='s'){if(!preg_match('/^[0-9]+$/',$value))throw new WorkbookFailure('FORMAT_INVALID',$row);$value=$shared->get((int)$value);}if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value))throw new WorkbookFailure('VALUE_INVALID',$row);return[$col,['value'=>$value,'formula'=>$formula,'type'=>$type,'style'=>$style]];
    }
    private static function appendBounded(string &$value,string $chunk):void{if(strlen($chunk)>self::MAX_CELL-strlen($value))throw new WorkbookFailure('INPUT_LIMIT_EXCEEDED');$value.=$chunk;}
    private static function headers(array $rows):void{foreach([5=>self::HEADER5,6=>self::HEADER6]as$r=>$expected)foreach($expected as$c=>$v)if(self::norm($rows[$r][$c]['value']??'')!==self::norm($v))throw new WorkbookFailure('FORMAT_INVALID',$r,'Header mismatch');}
    private static function norm(string $v):string{return mb_strtolower(trim((string)preg_replace('/\s+/u',' ',$v)),'UTF-8');}
    private static function footer(array $cells):bool{return preg_match('/^(итого|всего|примечан)/iu',trim($cells['A']['value']??''))||preg_match('/^(итого|всего|примечан)/iu',trim($cells['E']['value']??''));}
    private static function any(array $cells,array $cols):bool{foreach($cols as$c)if(trim($cells[$c]['value']??'')!=='')return true;return false;}
    private static function columnNumber(string $column):int{$n=0;foreach(str_split($column)as$c)$n=$n*26+ord($c)-64;return$n;}

    private static function map(array $cells,bool $date1904,WorkbookDateStyles $dateStyles,int $row):array
    {
        $get=static fn(string$c):string=>trim($cells[$c]['value']??'');$f=[];foreach(self::FIELDS as$c=>$name)$f[$name]=$get($c)===''?null:$get($c);
        foreach(['year'=>'B','regnumber'=>'C','unom'=>'D','address'=>'G','entrance'=>'H']as$name=>$c)if($f[$name]===null)throw new WorkbookFailure('VALUE_INVALID',$row,'Required value missing');
        if(!preg_match('/^[0-9]{4}$/',(string)$f['year']))throw new WorkbookFailure('VALUE_INVALID',$row);$f['year']=(int)$f['year'];
        foreach([['regnumber',120],['unom',120],['sourceArea',160],['sourceDistrict',160],['address',500],['entrance',80],['existingPitType',160],['existingPitMaterial',160],['existingCabinDoor',160],['existingShaftDoor',160],['pittype',160],['pitmaterial',160],['cabinDoor',160],['shaftDoor',160],['designPeriodRaw',500]]as[$name,$max])if($f[$name]!==null&&mb_strlen((string)$f[$name],'UTF-8')>$max)throw new WorkbookFailure('VALUE_INVALID',$row);
        foreach(['existingPitType','pittype'] as $name) self::dictionary($name,$f[$name], ['глухая','железо-сетчатая','железосетчатая','комбинированная','каркасно-приставная'], $row);
        foreach(['existingPitMaterial','pitmaterial'] as $name) self::dictionary($name,$f[$name], ['железобетон','кирпич','металлокаркас и сетка','металлокаркас и стекло','железобетон и металл','кирпич и металл'], $row);
        foreach(['existingCabinDoor','existingShaftDoor','cabinDoor','shaftDoor'] as $name) self::dictionary($name,$f[$name], ['автоматические','ручные'], $row);
        foreach(['regnumber'=>'C','unom'=>'D']as$name=>$c)if(($cells[$c]['type']??'')===''&&!preg_match('/^[0-9]{1,15}$/',(string)$f[$name]))throw new WorkbookFailure('VALUE_INVALID',$row,'Unsafe numeric identifier');
        foreach(['existingFloors'=>[1,200],'existingWeight'=>[1,100000],'floors'=>[1,200],'weight'=>[1,100000],'installationDays'=>[1,3660]]as$name=>[$min,$max])$f[$name]=self::integer($f[$name],$min,$max,$row);
        foreach(['existingSpeed'=>2,'speed'=>2,'designCost'=>2,'replacementCost'=>2,'discountedDesignCost'=>2,'discountedReplacementCost'=>2]as$name=>$scale)$f[$name]=self::decimal($f[$name],$scale,$row,$name==='existingSpeed'||$name==='speed'?'20.00':'999999999999.99');
        foreach(['existingSpeed','speed'] as $name) if ($f[$name] !== null && (float)$f[$name] <= 0) throw new WorkbookFailure('VALUE_INVALID',$row);
        foreach(['existingSpeed','speed'] as $name) if ($f[$name] !== null) $f[$name] = rtrim(rtrim($f[$name], '0'), '.');
        foreach(['frequencyDoor','frequencyMain']as$name)$f[$name]=self::boolean($f[$name],$row);
        foreach(['equipmentRequestDate'=>'AA','plannedStartDate'=>'AB','plannedFinishDate'=>'AC']as$name=>$c)$f[$name]=self::date($f[$name],$cells[$c]??null,$date1904,$dateStyles,$row);
        if($f['plannedStartDate']!==null&&$f['plannedFinishDate']!==null&&$f['plannedFinishDate']<$f['plannedStartDate'])throw new WorkbookFailure('VALUE_INVALID',$row,'Finish before start');
        return$f;
    }
    private static function integer(mixed $v,int $min,int $max,int $row):?int{if($v===null)return null;if(!preg_match('/^[0-9]+$/',(string)$v))throw new WorkbookFailure('VALUE_INVALID',$row);$n=(int)$v;if($n<$min||$n>$max)throw new WorkbookFailure('VALUE_INVALID',$row);return$n;}
    private static function decimal(mixed $v,int $scale,int $row,string $max):?string{if($v===null)return null;$s=(string)$v;if(!preg_match('/^(?:0|[1-9][0-9]*)(?:\.([0-9]+))?$/',$s,$m))throw new WorkbookFailure('VALUE_INVALID',$row);[$mi,$mf]=array_pad(explode('.',$max,2),2,'');$parts=explode('.',$s,2);$whole=ltrim($parts[0],'0');$whole=$whole===''?'0':$whole;if(strlen($whole)>strlen($mi)||(strlen($whole)===strlen($mi)&&strcmp($whole,$mi)>0))throw new WorkbookFailure('VALUE_INVALID',$row);$frac=$parts[1]??'';$kept=str_pad(substr($frac,0,$scale),$scale,'0');if(isset($frac[$scale])&&$frac[$scale]>='5'){$digits=$whole.$kept;$digits=self::addOne($digits);$whole=substr($digits,0,-$scale)?:'0';$kept=substr($digits,-$scale);} $out=$whole.'.'.$kept;if(self::compareDecimal($out,$max)>0)throw new WorkbookFailure('VALUE_INVALID',$row);return$out;}
    private static function addOne(string $s):string{$carry=1;for($i=strlen($s)-1;$i>=0&&$carry;$i--){$n=(ord($s[$i])-48)+1;$s[$i]=(string)($n%10);$carry=intdiv($n,10);}return$carry?'1'.$s:$s;}
    private static function compareDecimal(string $a,string $b):int{$aa=str_replace('.','',$a);$bb=str_replace('.','',$b);return strlen($aa)<=>strlen($bb)?:strcmp($aa,$bb);}
    private static function boolean(mixed $v,int $row):?bool{if($v===null)return null;$s=mb_strtolower(trim((string)$v),'UTF-8');if(in_array($s,['да','1','true'],true))return true;if(in_array($s,['нет','0','false'],true))return false;throw new WorkbookFailure('VALUE_INVALID',$row);}
    private static function dictionary(string $field,mixed $value,array $allowed,int $row):void{if($value===null)return;$key=self::dictionaryKey((string)$value);foreach($allowed as$item)if($key===self::dictionaryKey($item))return;throw new WorkbookFailure('VALUE_INVALID',$row,'Unknown '.$field);}
    private static function dictionaryKey(string $value):string{$value=self::norm($value);$value=preg_replace('/\bи\b/u',' ',$value)??$value;return preg_replace('/[^a-zа-я0-9]+/u','',$value)??$value;}
    private static function date(mixed $v,?array $cell,bool $date1904,WorkbookDateStyles $dateStyles,int $row):?string{if($v===null)return null;if(($cell['type']??'')===''&&preg_match('/^[0-9]+(?:\.[0-9]+)?$/',(string)$v)){if(!$dateStyles->has((int)($cell['style']??-1)))throw new WorkbookFailure('VALUE_INVALID',$row);$days=(int)floor((float)$v);if($days<=0)throw new WorkbookFailure('VALUE_INVALID',$row);$base=new \DateTimeImmutable($date1904?'1904-01-01':'1899-12-30',new \DateTimeZone('UTC'));return$base->modify('+'.$days.' days')->format('Y-m-d');}foreach(['!d.m.Y','!Y-m-d']as$fmt){$d=\DateTimeImmutable::createFromFormat($fmt,(string)$v,new \DateTimeZone('UTC'));$errors=\DateTimeImmutable::getLastErrors();if($d!==false&&($errors===false||($errors['warning_count']===0&&$errors['error_count']===0))&&$d->format(substr($fmt,1))===(string)$v)return$d->format('Y-m-d');}throw new WorkbookFailure('VALUE_INVALID',$row);}
}

final class WorkbookFailure extends \RuntimeException
{
    public function __construct(public readonly string $reasonCode,public readonly ?int $row=null,string $message='Файл отклонён'){parent::__construct($message);}
}

final class WorkbookSharedStrings
{
    /** @var resource */ private $data;
    /** @var resource */ private $index;
    private int $count=0;

    public function __construct()
    {
        $this->data=tmpfile();$this->index=tmpfile();
        if($this->data===false||$this->index===false)throw new WorkbookFailure('FORMAT_INVALID');
        foreach([$this->data,$this->index]as$stream){$meta=stream_get_meta_data($stream);if(isset($meta['uri'])&&is_string($meta['uri']))@chmod($meta['uri'],0600);}
    }

    public function add(string $value):void
    {
        $offset=ftell($this->data);if($offset===false)throw new WorkbookFailure('FORMAT_INVALID');
        self::write($this->index,pack('J',$offset));self::write($this->data,pack('N',strlen($value)).$value);$this->count++;
    }

    public function get(int $position):string
    {
        if($position<0||$position>=$this->count||fseek($this->index,$position*8)!==0)throw new WorkbookFailure('FORMAT_INVALID');
        $packed=fread($this->index,8);if($packed===false||strlen($packed)!==8)throw new WorkbookFailure('FORMAT_INVALID');
        $decoded=unpack('Joffset',$packed);$offset=$decoded['offset']??null;if(!is_int($offset)||fseek($this->data,$offset)!==0)throw new WorkbookFailure('FORMAT_INVALID');
        $packedLength=fread($this->data,4);if($packedLength===false||strlen($packedLength)!==4)throw new WorkbookFailure('FORMAT_INVALID');
        $decodedLength=unpack('Nlength',$packedLength);$length=$decodedLength['length']??null;if(!is_int($length))throw new WorkbookFailure('FORMAT_INVALID');
        $value='';while(strlen($value)<$length){$chunk=fread($this->data,$length-strlen($value));if($chunk===false||$chunk==='')throw new WorkbookFailure('FORMAT_INVALID');$value.=$chunk;}return$value;
    }

    /** @param resource $stream */
    private static function write($stream,string $bytes):void
    {
        $offset=0;while($offset<strlen($bytes)){$written=fwrite($stream,substr($bytes,$offset));if($written===false||$written===0)throw new WorkbookFailure('FORMAT_INVALID');$offset+=$written;}
    }
}

final class WorkbookDateStyles
{
    /** @var resource */ private $stream;
    private int $count=0;

    public function __construct()
    {
        $this->stream=tmpfile();if($this->stream===false)throw new WorkbookFailure('FORMAT_INVALID');$meta=stream_get_meta_data($this->stream);if(isset($meta['uri'])&&is_string($meta['uri']))@chmod($meta['uri'],0600);
    }
    public function add(bool $isDate):void{if(fwrite($this->stream,$isDate?"\1":"\0")!==1)throw new WorkbookFailure('FORMAT_INVALID');$this->count++;}
    public function has(int $index):bool{if($index<0||$index>=$this->count||fseek($this->stream,$index)!==0)return false;$value=fread($this->stream,1);return$value==="\1";}
}

final class WorkbookDateFormatIds
{
    /** @var resource */ private $flags;
    public function __construct(){$this->flags=tmpfile();if($this->flags===false)throw new WorkbookFailure('FORMAT_INVALID');$meta=stream_get_meta_data($this->flags);if(isset($meta['uri'])&&is_string($meta['uri']))@chmod($meta['uri'],0600);}
    public function add(int $id):void{if($id<0||fseek($this->flags,$id)!==0||fwrite($this->flags,"\1")!==1)throw new WorkbookFailure('FORMAT_INVALID');}
    public function has(int $id):bool{if($id<0||fseek($this->flags,$id)!==0)return false;$value=fread($this->flags,1);return$value==="\1";}
}
