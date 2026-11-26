<?php
declare(strict_types=1);
namespace FMonitor2\RuntimeRestore;
final class RuntimeRecoverySchemaV42
{
 public const VERSION=42;
 public const DEFERRED=RuntimeRecoverySchemaV41::DEFERRED;
 public static function tables(string$prefix):array{return RuntimeRecoverySchemaV41::tables($prefix);}
 public static function autoIncrement(string$prefix):array{return RuntimeRecoverySchemaV41::autoIncrement($prefix);}
}
