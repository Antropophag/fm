<?php
declare(strict_types=1);
namespace FMonitor2\RuntimeRestore;

final class RuntimeRecoverySchemaV40
{
    public const VERSION=40;
    public const DEFERRED=RuntimeRecoverySchemaV39::DEFERRED;
    public static function tables(string$prefix):array{$tables=[...RuntimeRecoverySchemaV39::tables(''),'fm2_password_events','fm2_password_recovery_requests','fm2_password_recovery_attempts'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
    public static function autoIncrement(string$prefix):array{$tables=[...RuntimeRecoverySchemaV39::autoIncrement(''),'fm2_password_events','fm2_password_recovery_attempts'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
}
