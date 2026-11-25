<?php
declare(strict_types=1);
namespace FMonitor2\Otiz;
use DateTimeImmutable;
use DateTimeZone;
use mysqli;

final class MariaDbNativePremiumInputs
{
    use MariaDbNativePremiumInputsEvidence;
    use MariaDbNativePremiumInputsProgress;
    use MariaDbNativePremiumInputsValues;
    use MariaDbNativePremiumInputsDocumentary;
    private $clock;
    public function __construct(
        private mysqli $db,
        private string $prefix,
        private ?string $legacyPrefix = null,
        ?\Closure $clock = null,
    ) {
        if (
            !preg_match('/^[A-Za-z0-9_]{0,25}$/D', $prefix) ||
            ($legacyPrefix !== null && !preg_match('/^[A-Za-z0-9_]{0,25}$/D', $legacyPrefix))
        ) {
            throw new \InvalidArgumentException();
        }
        $this->legacyPrefix ??= $prefix;
        $this->clock =
            $clock ??
            static fn() => new DateTimeImmutable("now", new DateTimeZone("Europe/Moscow"))->format(
                DateTimeImmutable::ATOM,
            );
    }
    public function forDate(string $date): array
    {
        if (!$this->date($date)) {
            throw new \DomainException("INVALID_DATE");
        }
        $at = ($this->clock)();
        $p = $this->prefix;
        $l = $this->legacyPrefix;
        $q = $this->db->prepare(
            "SELECT c.id case_id,c.legacy_installation_object_id object_id,c.actual_start_date,l.regnumber,l.ordadr_address address,l.workdatestart,l.plan_finish_date FROM `{$p}fm2_installation_cases`c LEFT JOIN `{$p}fm2_migration_classification_provenance`m ON m.output_kind='operational_case' AND m.output_id=c.id AND m.legacy_object_id=c.legacy_installation_object_id JOIN `{$l}fm_maintable`l ON l.id=c.legacy_installation_object_id WHERE c.process_state IN('working','completed') AND c.actual_start_date<=? AND (m.category='native_candidate' OR m.category IS NULL) ORDER BY c.legacy_installation_object_id",
        );
        $q->execute([$date]);
        return array_map(fn($r) => $this->row($r, $date, $at), $q->get_result()->fetch_all(MYSQLI_ASSOC));
    }
    private function row(array $b, string $date, string $at): array
    {
        $case = (int) $b["case_id"];
        $object = (int) $b["object_id"];
        $issues = [];
        $contractStart=(string)($b['workdatestart']??'');$contractFinish=(string)($b['plan_finish_date']??'');$actualStart=(string)($b['actual_start_date']??'');$period=null;
        $original = null;
        try{$norm=ContractualReplacementPeriod::fromContractDates($contractStart,$contractFinish);$allowance=ContractualReplacementPeriod::allowanceFinish($actualStart,$norm);$period=['contractStartDate'=>$contractStart,'contractFinishDate'=>$contractFinish,'normDays'=>$norm,'actualStartDate'=>$actualStart,'allowanceFinishDate'=>$allowance];
            $c = ['objectId'=>$object,'contractStartDate'=>$contractStart,'contractFinishDate'=>$contractFinish,'normDays'=>$norm,'actualStartDate'=>$actualStart,'allowanceFinishDate'=>$allowance];
            $original = [
                "value" => $allowance,
                "capturedAt" => $at,
                "source" => $this->source("Договорный норматив от фактического начала", "fm_maintable/$object/workdatestart+plan_finish_date;fm2_installation_cases/$case/actual_start_date", $c),
            ];
        }catch(\InvalidArgumentException|\DomainException){
            $this->issue($issues, "DEADLINE_EVIDENCE_ABSENT");
        }
        $certificate = $this->certificate($case, $issues);
        $reclamations = $this->reclamations($case, $issues);
        $deadlineAllowance = null;
        if ($original !== null && $reclamations !== null) {
            try {
                $deadlineAllowance = ReclamationDeadline::resolve($original['value'], $certificate, $reclamations);
            } catch (\InvalidArgumentException|\DomainException) {
                $this->issue($issues, 'DEADLINE_EVIDENCE_INVALID');
            }
        }
        $completion = $this->completion($case, $issues);
        [$composition, $selected] = $this->composition($case, $object, $issues);
        [$progress, $progressProof, $contrib] = $this->progress($case, $date, $issues);
        $finance = $this->finance($object, $issues);
        $team = $this->team($case, $progress, $selected, $contrib, $issues);
        $deadline = $deadlineAllowance['effectiveDeadline'] ?? null;
        $pto = $completion["date"];
        $operands = null;
        if ($issues === []) {
            $fact = static fn($v, $d, $s) => [
                "value" => $v,
                "effectiveDate" => $d,
                "source" => $s,
            ];
            $fs = [
                "label" => $finance["label"],
                "locator" => $finance["locator"],
                "contentSha256" => $finance["sha"],
            ];
            $operands = [
                "reportDate" => $fact(
                    $date,
                    $date,
                    $this->source("Выбранная отчётная дата", "otiz/report-date/" . $date, ["reportDate" => $date]),
                ),
                "premiumCents" => $fact($finance["premium"], $finance["date"], $fs),
                "shaftBp" => $fact($finance["shaft"], $finance["date"], $fs),
                "progressBp" => $fact($progress, $date, $progressProof["source"]),
                "deadlineDate" => $fact(
                    $deadline,
                    $certificate["certificateDate"] ?? substr($at, 0, 10),
                    $deadlineAllowance['source'],
                ),
                "completionDate" => $fact($pto, $completion["date"] ?? $date, $completion["evidence"]["source"]),
            ];
        }
        return [
            "id" => $object,
            "caseId" => $case,
            "reg" => (string) $b["regnumber"],
            "address" => (string) $b["address"],
            "progress" => $progress,
            "deadline" => $deadline,
            "pto" => $pto,
            "premium" => (int) ($finance["premium"] ?? 0),
            "shaft" => (int) ($finance["shaft"] ?? 0),
            "team" => $team,
            "issues" => $issues,
            "operands" => $operands,
            "sourceEvidence" => [
                "capturedAt" => $at,
                "originalDeadline" => $original,
                "contractualPeriod" => $period,
                "certificate" => $certificate,
                "reclamations" => $reclamations,
                "deadlineAllowance" => $deadlineAllowance,
                "completion" => $completion["evidence"],
                "composition" => $composition,
                "progress" => $progressProof,
            ],
        ];
    }
}
