<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;
final class ObjectDetailsReferenceCatalogue
{
 private const VALUES=[
  'pittype'=>['7'=>'7','40'=>'Глухая','201'=>'Железо-сетчатая','202'=>'Комбинированная','203'=>'Каркасно-приставная'],
  'pitmaterial'=>['9'=>'9','41'=>'Железобетон','72'=>'Железобетон и металл','86'=>'Кирпич','112'=>'Кирпич и металл','123'=>'Металлокаркас и стекло','124'=>'Металлокаркас и сетка'],
  'lift_type'=>['1'=>'Пассажирский','2'=>'Грузовой'],
 ];
 public static function options(string$field):array{return self::VALUES[$field]??throw new \InvalidArgumentException('Unknown reference field.');}
 public static function display(string$field,string$code):string{return self::VALUES[$field][$code]??throw new \InvalidArgumentException('Unknown reference value.');}
 public static function codeForDisplay(string$field,string$value):string
 {
  $key=self::dictionaryKey($value);
  foreach(self::options($field)as$code=>$display)if(self::dictionaryKey($display)===$key)return(string)$code;
  throw new \InvalidArgumentException('Unknown reference value.');
 }
 private static function dictionaryKey(string$value):string
 {
  $value=mb_strtolower(trim((string)preg_replace('/\s+/u',' ',$value)),'UTF-8');
  $value=preg_replace('/\bи\b/u',' ',$value)??$value;
  return preg_replace('/[^a-zа-я0-9]+/u','',$value)??$value;
 }
}
