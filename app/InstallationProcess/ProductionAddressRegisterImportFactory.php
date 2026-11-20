<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class ProductionAddressRegisterImportFactory
{
    public static function create(
        \mysqli $db,
        string $processPrefix,
        string $legacyPrefix,
        string $target,
        int $operatorUserId,
        AddressRegisterAddressProvider $provider,
        callable $clock,
        array $configuration = [],
    ):AddressRegisterImportApplication {
        if (!in_array($target, ['dev', 'prod'], true) || $operatorUserId < 1) {
            throw new \InvalidArgumentException();
        }
        AddressRegisterImportSchemaMigration::assertCompatible($db, $processPrefix);
        return new AddressRegisterImportApplication(
            MariaDbAddressRegisterImportStore::create($db, $processPrefix, $legacyPrefix),
            $target,
            $operatorUserId,
            $provider,
            $clock,
            $configuration,
        );
    }
}
