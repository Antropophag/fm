<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class MariaDbAddressRegisterImportPrincipal
{
    public static function current(\mysqli $db):string
    {
        return (string) $db->query('SELECT CURRENT_USER()')->fetch_column();
    }
}
