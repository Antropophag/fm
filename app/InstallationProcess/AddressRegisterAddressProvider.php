<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

interface AddressRegisterAddressProvider
{
    /** @return array<string,mixed> */
    public function clean(string $source): array;
}
