<?php
declare(strict_types=1);
namespace FMonitor2\Otiz;
final class ContractualReplacementPeriod
{
 public static function fromContractDates(string$s,string$f):int{$a=self::date($s);$b=self::date($f);if($b<$a)throw new \DomainException('CONTRACT_PERIOD_INVALID');return(int)$a->diff($b)->days+1;}
 public static function allowanceFinish(string$s,int$d):string{if($d<1)throw new \InvalidArgumentException('NORM_DAYS_INVALID');return self::date($s)->modify('+'.($d-1).' days')->format('Y-m-d');}
 private static function date(string$v):\DateTimeImmutable{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v,new \DateTimeZone('UTC'));$e=\DateTimeImmutable::getLastErrors();if($d===false||($e!==false&&($e['warning_count']||$e['error_count']))||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('DATE_INVALID');return$d;}
}
