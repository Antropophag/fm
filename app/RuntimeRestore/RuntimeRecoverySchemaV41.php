<?php
declare(strict_types=1);
namespace FMonitor2\RuntimeRestore;
final class RuntimeRecoverySchemaV41
{
 public const VERSION=41;
 public const DEFERRED=RuntimeRecoverySchemaV40::DEFERRED;
 public static function tables(string$prefix):array{$tables=[...RuntimeRecoverySchemaV40::tables(''),'fm2_otiz_object_payment_hold_events','fm2_otiz_payment_allocations'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
 public static function autoIncrement(string$prefix):array{$tables=[...RuntimeRecoverySchemaV40::autoIncrement(''),'fm2_otiz_object_payment_hold_events','fm2_otiz_payment_allocations'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
}
