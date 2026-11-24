<?php

declare(strict_types=1);

namespace FMonitor2\InstallationProcess;
use FMonitor2\Otiz\ContractualReplacementPeriod;

final class MariaDbLegacyInstallationObject
{
    public function __construct(
        private readonly \mysqli $connection,
        private readonly string $tablePrefix = '',
    ) {
        MariaDbSchemaInspector::validateTablePrefix($this->tablePrefix);
    }

    /** @return array{address: string, entrance: string, objectRegistrationNumber: string, plannedStartDate: ?string, plannedFinishDate: ?string, ptoActDate: ?string} */
    public function getInstallationObjectSnapshot(int $installationObjectId): array
    {
        $actualStart=MariaDbSchemaInspector::tableExists($this->connection,$this->tablePrefix.'fm2_installation_cases')
            ?",(SELECT c.actual_start_date FROM `{$this->tablePrefix}fm2_installation_cases` c WHERE c.legacy_installation_object_id=m.id ORDER BY c.id DESC LIMIT 1) actual_start_date"
            :",NULL actual_start_date";
        $statement = $this->connection->prepare(
            'SELECT m.id,m.ordadr_address,m.entrance,m.regnumber,m.workdatestart,m.workdateendadjusted,m.plan_finish_date,m.ptoactdate '.$actualStart
            . " FROM `{$this->tablePrefix}fm_maintable` m WHERE m.id=? LIMIT 1",
        );
        $statement->bind_param('i', $installationObjectId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        if ($row === null) {
            throw new \RuntimeException('Legacy installation object not found.');
        }

        $contractStart=self::plannedDate($row['workdatestart']);$contractFinish=self::plannedDate($row['plan_finish_date']);$actualStartDate=self::plannedDate($row['actual_start_date']);$normativePeriodDays=null;
        if($contractStart!==null&&$contractFinish!==null){try{$normativePeriodDays=ContractualReplacementPeriod::fromContractDates($contractStart,$contractFinish);}catch(\InvalidArgumentException|\DomainException){}}
        return [
            'address' => trim((string) $row['ordadr_address']),
            'entrance' => trim((string) $row['entrance']),
            'objectRegistrationNumber' => trim((string) $row['regnumber']),
            'plannedStartDate' => self::plannedDate($row['workdatestart']),
            'plannedFinishDate' => self::plannedDate($row['workdateendadjusted'])??self::plannedDate($row['plan_finish_date']),
            'ptoActDate' => self::optionalDate($row['ptoactdate']),
        ]+($normativePeriodDays===null?[]:['contractStartDate'=>$contractStart,'contractFinishDate'=>$contractFinish,'normativePeriodDays'=>$normativePeriodDays,'actualStartDate'=>$actualStartDate]);
    }

    private static function plannedDate(mixed $value): ?string
    { try { return self::optionalDate($value); } catch (\RuntimeException) { return null; } }

    private static function optionalDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || preg_match('/^0+$/D', $normalized) === 1 || str_starts_with($normalized, '0000-00-00')) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\D|$)/D', $normalized, $parts) !== 1
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new \RuntimeException('Unrecognized legacy calendar date.');
        }

        return $parts[1] . '-' . $parts[2] . '-' . $parts[3];
    }
}
