<?php
declare(strict_types=1);
namespace FMonitor2\RuntimeRestore;
final class RuntimeRecoverySchemaV44
{
 public const VERSION=44;
 public const DEFERRED=RuntimeRecoverySchemaV43::DEFERRED;
 public static function tables(string$prefix):array{$tables=[...RuntimeRecoverySchemaV43::tables(''),'fm2_address_import_packages','fm2_address_import_cache','fm2_address_import_attempts','fm2_address_import_objects','fm2_address_import_operations','fm2_address_import_ids','fm2_address_import_payload_chunks'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
 public static function autoIncrement(string$prefix):array{$tables=[...RuntimeRecoverySchemaV43::autoIncrement(''),'fm2_address_import_ids'];sort($tables,SORT_STRING);return array_map(static fn(string$t):string=>$prefix.$t,$tables);}
}
