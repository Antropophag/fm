<?php
declare(strict_types=1);
namespace FMonitor2\RuntimeRestore;
final class RuntimeRecoverySchemaV43
{
 public const VERSION=43;
 public const DEFERRED=RuntimeRecoverySchemaV42::DEFERRED;
 public static function tables(string$prefix):array{$tables=[...RuntimeRecoverySchemaV42::tables(''),'fm2_equipment_reclamations'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
 public static function autoIncrement(string$prefix):array{$tables=[...RuntimeRecoverySchemaV42::autoIncrement(''),'fm2_equipment_reclamations'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
}
