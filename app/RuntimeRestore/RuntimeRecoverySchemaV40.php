<?php
declare(strict_types=1);
namespace FMonitor2\RuntimeRestore;

final class RuntimeRecoverySchemaV40
{
    public const VERSION = 40;
    public const DEFERRED = RuntimeRecoverySchemaV39::DEFERRED;
    private const TABLES = [
        'fm2_address_import_packages', 'fm2_address_import_cache',
        'fm2_address_import_attempts', 'fm2_address_import_objects',
        'fm2_address_import_operations', 'fm2_address_import_ids',
        'fm2_address_import_payload_chunks',
    ];

    public static function tables(string $prefix):array
    {
        $tables = [...RuntimeRecoverySchemaV39::tables($prefix), ...array_map(
            static fn(string $name):string => $prefix . $name,
            self::TABLES,
        )];
        sort($tables, SORT_STRING);
        return $tables;
    }

    public static function autoIncrement(string $prefix):array
    {
        $tables = [...RuntimeRecoverySchemaV39::autoIncrement($prefix), $prefix . 'fm2_address_import_ids'];
        sort($tables, SORT_STRING);
        return $tables;
    }
}
