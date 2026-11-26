<?php
declare(strict_types=1);

/** Synthetic literal XLSX source, independent of the importer. */
final class AddressRegisterWorkbookFixture
{
    /** Materialize only the deliberately small assertion fixtures, not production reports. */
    public static function items(iterable $items): array
    {
        return is_array($items) ? $items : iterator_to_array($items, false);
    }

    public static function row(string $reg='TEST-0001', string $house='10'): array
    {
        return ['A'=>'1','B'=>'2027','C'=>$reg,'D'=>'70001','E'=>'Северный','F'=>'Тестовый',
            'G'=>'г Москва, ул Тестовая, д '.$house,'H'=>'1а','I'=>'9','J'=>'320','K'=>'Глухая','L'=>'Кирпич',
            'M'=>'Автоматические','N'=>'Автоматические','O'=>'0.70999999999999897',
            'P'=>'9','Q'=>'400','R'=>'Глухая','S'=>'Кирпич','T'=>'Автоматические','U'=>'Автоматические',
            'V'=>'1','W'=>'да','X'=>'нет','Y'=>'Сентябрь 2026 г.- 20 января 2027 г.','Z'=>'45',
            'AA'=>'01.12.2026','AD'=>'57962.41','AE'=>'3655456'];
    }

    public static function write(string $path,array $rows,array $cells=[],array $formulas=[]):void
    {
        $grid=[1=>['AD'=>'Приложение № 1 к договору TEST-CONTRACT'],
            5=>['A'=>'№ п/п','B'=>'Год замены','C'=>'Рег №','D'=>'UNOM','E'=>'Административный округ','F'=>'Район','G'=>'Адрес многоквартирного дома ','H'=>'Подъезд','I'=>'Характеристики существующих лифтов','P'=>'Характеристики устанавливаемых лифтов*','Y'=>'Сроки начала и окончания работ по разработке проектной документации','Z'=>'Количество дней на установку лифта','AB'=>'Дата начала производства работ','AC'=>'Дата окончания производства работ','AD'=>'Стоимость, руб.','AF'=>'Стоимость со снижением, руб'],
            6=>['I'=>'Этажность здания','J'=>'Грузоподъемность, кг','K'=>'Тип шахты','L'=>'Материл стен шахты','M'=>'Тип дверей кабины','N'=>'Тип дверей шахты','O'=>'Скорость','P'=>'Этажность здания','Q'=>'Грузоподъемность, кг','R'=>'Тип шахты','S'=>'Материл стен шахты','T'=>'Тип дверей кабины','U'=>'Тип дверей шахты','V'=>'Скорость','W'=>'Наличие частотного преобразователя привода дверей','X'=>'Наличие частотного преобразователя главного привода','AA'=>'дата подачи заявки на оборудование','AD'=>'стоимость проектной документации','AE'=>'стоимость работ по замене лифтового оборудования**','AF'=>'стоимость проектной документации','AG'=>'стоимость работ по замене лифтового оборудования']];
        foreach($rows as$i=>$row)$grid[7+$i]=$row;
        foreach($cells as$ref=>$value){preg_match('/^([A-Z]+)([0-9]+)$/',$ref,$m);$grid[(int)$m[2]][$m[1]]=$value;}
        $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        ksort($grid);foreach($grid as$n=>$row){$xml.='<row r="'.$n.'"'.($n===8?' hidden="1"':'').'>';foreach($row as$c=>$value){$ref=$c.$n;$escaped=htmlspecialchars((string)$value,ENT_XML1,'UTF-8');$xml.='<c r="'.$ref.'" t="inlineStr">'.(isset($formulas[$ref])?'<f>'.htmlspecialchars($formulas[$ref],ENT_XML1,'UTF-8').'</f>':'').'<is><t>'.$escaped.'</t></is></c>';}$xml.='</row>';}
        $xml.='</sheetData><autoFilter ref="A6:AG99"><filterColumn colId="1"><filters><filter val="2028"/></filters></filterColumn></autoFilter></worksheet>';
        $z=new ZipArchive();if($z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('fixture ZIP');
        $z->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $z->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Адресный перечень" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $z->addFromString('xl/worksheets/sheet1.xml',$xml);$z->close();chmod($path,0600);
    }

    public static function address(string $house='10'):array
    {
        return ['result'=>'г Москва, ул Тестовая, д '.$house,'house'=>$house,'block'=>null,'block_type'=>null,
            'house_fias_id'=>'11111111-1111-4111-8111-'.str_pad($house,12,'0',STR_PAD_LEFT),
            'qc'=>0,'qc_house'=>2,'fias_level'=>'8','fias_actuality_state'=>'0',
            'region_iso_code'=>'RU-MOW','country_iso_code'=>'RU','unparsed_parts'=>null,'flat'=>null,'room'=>null];
    }

    public static function replaceEntry(string $path,string $name,callable $replace):void
    {
        $z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('fixture open');
        $value=$z->getFromName($name);$z->addFromString($name,$replace($value===false?'':$value));$z->close();
    }

    public static function numericCell(string $path,string $ref,string $value, bool $date=false):void
    {
        self::replaceEntry($path,'xl/worksheets/sheet1.xml',static fn($xml)=>preg_replace('/<c r="'.preg_quote($ref,'/').'"[^>]*>.*?<\/c>/s','<c r="'.$ref.'"'.($date?' s="1"':'').'><v>'.$value.'</v></c>',$xml));
        if($date)self::replaceEntry($path,'xl/styles.xml',static fn()=>'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/></cellXfs></styleSheet>');
    }
}
