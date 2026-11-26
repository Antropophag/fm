<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;
final class AddressRegisterImportApplication
{
    private const CURRENT_FIELDS = ['year','regnumber','unom','sourceArea','sourceDistrict','address','entrance','floors','weight','pittype','pitmaterial','cabinDoor','shaftDoor','speed','frequencyDoor','frequencyMain','designPeriodRaw','installationDays','equipmentRequestDate','plannedStartDate','plannedFinishDate'];
    public function __construct(
        private MariaDbAddressRegisterImportStore $store,
        private string $target,
        private int $actor,
        private AddressRegisterAddressProvider $provider,
        private $clock,
        private array $config = []
    ) {
        $this->config = array_replace(
            ["cacheTtlDays" => 90, "evidenceSecret" => ""],
            $config
        );
    }
    public function estimate(string $path, int $year, string $contract): array
    {
        $deny = $this->access();
        if ($deny) {
            return $deny;
        }
        $parsed = $this->scanSnapshot($path, $year, $contract)[0];
        if (count($parsed["errors"]) > 0) {
            return $this->rejected($parsed);
        }
        $keys = $this->addresses($parsed["objects"]);
        $hits = $stale = 0;
        foreach ($keys as $hash => $address) {
            $c = $this->store->cache($hash);
            if ($c && $c["lock_owner"] === null) {
                if ($this->fresh($c)) {
                    $hits++;
                } else {
                    $stale++;
                }
            }
        }
        $miss = count($keys) - $hits - $stale;
        return [
            "status" => "estimated",
            "reasonCode" => null,
            "packageId" => null,
            "previewSha256" => null,
            "summary" => $this->summary($parsed),
            "rows" => [],
            "errors" => [],
            "cost" => [
                "uniqueAddresses" => count($keys),
                "cacheHits" => $hits,
                "cacheMisses" => $miss,
                "cacheStale" => $stale,
                "estimatedRequests" => $miss + $stale,
                "estimatedRubles" => number_format(
                    ($miss + $stale) * 0.2,
                    2,
                    ".",
                    ""
                ),
            ],
        ];
    }
    public function preview(
        string $path,
        int $year,
        string $contract,
        bool $overwrite = false,
        string $reason = "",
        array $options = []
    ): array {
        if ($overwrite && trim($reason) === "") {
            return $this->error("CONFIGURATION_INVALID");
        }
        $deny = $this->access();
        if ($deny) {
            return $deny;
        }
        try {
            $opt = $this->options($options);
        } catch (\Throwable) {
            return $this->error("CONFIGURATION_INVALID");
        }
        [$parsed, $sourceSha] = $this->scanSnapshot($path, $year, $contract);
        if (count($parsed["errors"]) > 0) {
            return $this->rejected($parsed);
        }
        $parsed["objects"] = $this->canonicalizeObjects($parsed["objects"]);
        $localErrors = $this->prevalidate($parsed["objects"]);
        if ($localErrors !== []) {
            $parsed["errors"] = $localErrors;
            return $this->rejected($parsed);
        }
        $id = $this->uuid();
        $now = $this->now();
        $mode = $overwrite ? "overwrite" : "fill_missing";
        $base = $this->reportBase($parsed, $id, $sourceSha, $mode);
        $this->store->savePackage([
            "packageId" => $id,
            "target" => $this->target,
            "actor" => $this->actor,
            "status" => "blocked",
            "sourceSha" => $sourceSha,
            "previewSha" => null,
            "year" => $year,
            "contract" => $contract,
            "mode" => $mode,
            "reason" => $reason,
            "source" => $parsed,
            "report" => $base,
            "budgetLimit" => $opt["maxPaidRequests"],
            "budgetReason" => $opt["budgetReason"],
            "now" => $now,
        ]);
        return $this->prepare($id, $opt, $overwrite, $reason);
    }
    public function resume(string $packageId, array $options = []): array
    {
        $deny = $this->access();
        if ($deny) {
            return $deny;
        }
        if (!$this->validUuid($packageId)) {
            return $this->error("CONFIGURATION_INVALID");
        }
        try {
            $opt = $this->options($options);
        } catch (\Throwable) {
            return $this->error("CONFIGURATION_INVALID");
        }
        $p = $this->store->package($packageId);
        if (!$p) {
            return $this->error("PACKAGE_NOT_FOUND");
        }
        if ($p["target"] !== $this->target) {
            return $this->error("TARGET_MISMATCH");
        }
        if ($p["status"] === "applied") {
            return $p["report"];
        }
        if (!$this->hasCurrentFields($p)) return $this->previewStale($p);
        if ($p["status"] === "ready") return $p["report"];
        if (
            ($opt["retryUnknown"] ||
                $opt["maxPaidRequests"] > (int) $p["budget_limit"]) &&
            trim($opt["budgetReason"]) === ""
        ) {
            return array_replace($p["report"], [
                "reasonCode" => "BUDGET_REASON_REQUIRED",
            ]);
        }
        return $this->prepare(
            $packageId,
            $opt,
            $p["mode"] === "overwrite",
            $p["reason"]
        );
    }
    public function apply(
        string $packageId,
        string $previewSha256,
        string $operationId
    ): array {
        $deny = $this->access();
        if ($deny) {
            return $deny;
        }
        if (
            !$this->validUuid($packageId) ||
            !preg_match('/^[0-9a-f]{64}$/D', $previewSha256) ||
            !preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
                $operationId
            )
        ) {
            return $this->error("CONFIGURATION_INVALID");
        }
        $p = $this->store->package($packageId);
        if (!$p) {
            return $this->error("PACKAGE_NOT_FOUND");
        }
        if ($p["target"] !== $this->target) {
            return $this->error("TARGET_MISMATCH");
        }
        if ($p["status"] !== "applied" && !$this->hasCurrentFields($p)) {
            return $this->previewStale($p);
        }
        return $this->store->apply(
            $p,
            $previewSha256,
            $operationId,
            $this->actor,
            $this->now()
        );
    }
    public function report(string $packageId): array
    {
        $deny = $this->access();
        if ($deny) {
            return $deny;
        }
        if (!$this->validUuid($packageId)) {
            return $this->error("CONFIGURATION_INVALID");
        }
        $p = $this->store->package($packageId);
        return $p && $p["target"] === $this->target
            ? $p["report"]
            : $this->error($p ? "TARGET_MISMATCH" : "PACKAGE_NOT_FOUND");
    }
    public function exportReport(string $packageId, string $directory): array
    {
        $r = $this->report($packageId);
        $app = $r["status"] ?? "failed";
        if (
            in_array(
                $r["reasonCode"] ?? null,
                [
                    "AUTHORIZATION_DENIED",
                    "CONFIGURATION_INVALID",
                    "PACKAGE_NOT_FOUND",
                    "TARGET_MISMATCH",
                ],
                true
            )
        ) {
            return $r;
        }
        $json = $csv = $jsonPartial = $csvPartial = null;
        $jsonPublished = false;
        $jsonPartialCreated = $csvPartialCreated = false;
        try {
            $directory = $this->safeExportDirectory($directory);
            $base = $directory . DIRECTORY_SEPARATOR . $packageId;
            $json = $base . ".json";
            $csv = $base . ".csv";
            $jsonPartial = $json . ".partial";
            $csvPartial = $csv . ".partial";
            foreach ([$json, $csv, $jsonPartial, $csvPartial] as $path) {
                if (file_exists($path) || is_link($path)) {
                    throw new \RuntimeException();
                }
            }
            $jsonStream = fopen($jsonPartial, "xb");
            if (!is_resource($jsonStream)) throw new \RuntimeException();
            try {
                chmod($jsonPartial, 0600);
                AddressRegisterResultWriter::write($jsonStream, $r, true);
                if (fwrite($jsonStream, "\n") !== 1 || !fflush($jsonStream) || !fsync($jsonStream)) throw new \RuntimeException();
            } finally { fclose($jsonStream); }
            $jsonPartialCreated = true;
            $this->writeCsvReport($csvPartial, $r["rows"] ?? []);
            $csvPartialCreated = true;
            if (!rename($jsonPartial, $json)) {
                throw new \RuntimeException();
            }
            $jsonPartialCreated = false;
            $jsonPublished = true;
            if (!rename($csvPartial, $csv)) {
                throw new \RuntimeException();
            }
            $csvPartialCreated = false;
            return [
                "status" => "exported",
                "reasonCode" => null,
                "applicationStatus" => $app,
                "files" => ["json" => $json, "csv" => $csv],
            ];
        } catch (\Throwable) {
            foreach ([[$jsonPartial, $jsonPartialCreated], [$csvPartial, $csvPartialCreated]] as [$partial, $created]) {
                if ($created && is_string($partial) && is_file($partial) && !is_link($partial)) {
                    @unlink($partial);
                }
            }
            if ($jsonPublished && is_string($json) && is_file($json) && !is_link($json)) {
                @unlink($json);
            }
            return [
                "status" => "failed",
                "reasonCode" => "REPORT_EXPORT_FAILED",
                "applicationStatus" => $app,
            ];
        }
    }
    public function exportAddressEvidence(): array
    {
        $deny = $this->access();
        if ($deny) {
            return $deny;
        }
        $writer = new AddressRegisterPayloadWriter();
        foreach ($this->store->caches() as $c) {
            if ($c["accepted"] && $this->fresh($c)) {
                $writer->add([
                    "requestHash" => $c["request_hash"],
                    "request" => $c["request"],
                    "responseHash" => $c["response_hash"],
                    "capturedAt" =>
                        str_replace(
                            " ",
                            "T",
                            substr($c["captured_at_utc"], 0, 19)
                        ) . "Z",
                    "response" => $c["response"],
                ]);
            }
        }
        $payload = [
            "version" => 1,
            "provider" => "dadata-clean-address",
            "entries" => $writer->finish(),
        ];
        $payload["signature"] = $this->canonicalHmac($payload);
        return $payload;
    }
    public function importAddressEvidence(array $bundle): array
    {
        $deny = $this->access();
        if ($deny) {
            return $deny;
        }
        try {
            $sig = $bundle["signature"] ?? ""; unset($bundle["signature"]);
            if (
                !is_string($sig) ||
                !hash_equals($this->canonicalHmac($bundle), $sig) ||
                ($bundle["version"] ?? null) !== 1 ||
                ($bundle["provider"] ?? null) !== "dadata-clean-address" ||
                !is_iterable($bundle["entries"] ?? null)
            ) {
                throw new \RuntimeException();
            }
            $entryWriter = new AddressRegisterPayloadWriter();
            foreach ($bundle["entries"] as $e) {
                if (
                    !preg_match(
                        '/^[0-9a-f]{64}$/D',
                        (string) ($e["requestHash"] ?? "")
                    ) ||
                    !is_array($e["request"] ?? null) ||
                    !hash_equals(
                        (string) $e["requestHash"],
                        hash("sha256", $this->canonical($e["request"]))
                    ) ||
                    !$this->validRequestEnvelope($e["request"]) ||
                    !is_array($e["response"] ?? null) ||
                    hash(
                        "sha256",
                        json_encode(
                            $e["response"],
                            JSON_UNESCAPED_UNICODE |
                                JSON_UNESCAPED_SLASHES |
                                JSON_THROW_ON_ERROR
                        )
                    ) !==
                        ($e["responseHash"] ?? "")
                ) {
                    throw new \RuntimeException();
                }
                $captured = $this->evidenceTime(
                    (string) ($e["capturedAt"] ?? "")
                );
                if (
                    $captured === false ||
                    $captured > $this->date() ||
                    $this->date()->getTimestamp() - $captured->getTimestamp() >
                        (int) $this->config["cacheTtlDays"] * 86400 ||
                    !AddressRegisterAddressValidation::validate(
                        (string) $e["request"]["request"]["address"],
                        $e["response"]
                    )["accepted"]
                ) {
                    throw new \RuntimeException();
                }
                $e["capturedAt"] = $captured->format("Y-m-d H:i:s.u");
                $entryWriter->add($e);
            }
            $this->store->importCache($entryWriter->finish());
            return ["status" => "imported", "reasonCode" => null];
        } catch (\Throwable) {
            return ["status" => "rejected", "reasonCode" => "EVIDENCE_INVALID"];
        }
    }
    private function prepare(
        string $id,
        array $opt,
        bool $overwrite,
        string $reason
    ): array {
        $p = $this->store->package($id);
        if (!$p) {
            return $this->error("PACKAGE_NOT_FOUND");
        }
        $preparationOwner = $this->uuid();
        if (
            !$this->store->acquirePreparation(
                $id,
                $preparationOwner,
                $this->now()
            )
        ) {
            $latest = $this->store->package($id);
            if (!$latest) {
                return $this->error("PACKAGE_NOT_FOUND");
            }
            return in_array($latest["status"], ["ready", "applied", "unknown"], true)
                ? $latest["report"]
                : array_replace($latest["report"], [
                    "status" => "blocked",
                    "reasonCode" => "CACHE_BUSY",
                ]);
        }
        try {
        if ($p["status"] === "unknown" && !$opt["retryUnknown"]) {
            return $p["report"];
        }
        $parsed = $p["source"];
        $addresses = $this->addresses($parsed["objects"]);
        $evidence = new AddressRegisterEvidenceSpool();
        $paid = $this->store->attempts($id);
        foreach ($addresses as $hash => $address) {
            $ownsClaim = false;
            $cache = $this->store->cache($hash);
            if (
                $cache &&
                $cache["lock_owner"] === null &&
                $this->fresh($cache) &&
                !$opt["refreshAddresses"]
            ) {
                $evidence->put($hash, $cache);
                continue;
            }
            if ($cache && $cache["lock_owner"] !== null) {
                $ownsClaim =
                    $opt["allowPaidCalls"] &&
                    $this->store->recoverClaim(
                        $hash,
                        $id,
                        $opt["retryUnknown"],
                        $opt["budgetReason"],
                        $this->now()
                    );
                if (!$ownsClaim) {
                    return $this->finish(
                        $p,
                        "blocked",
                        "CACHE_BUSY",
                        $paid,
                        $parsed,
                        []
                    );
                }
            }
            if (
                $cache &&
                !$ownsClaim &&
                !$this->fresh($cache) &&
                !$opt["refreshAddresses"]
            ) {
                return $this->finish(
                    $p,
                    "blocked",
                    "ADDRESS_EVIDENCE_STALE",
                    $paid,
                    $parsed,
                    []
                );
            }
            if (!$opt["allowPaidCalls"]) {
                return $this->finish(
                    $p,
                    "blocked",
                    "PAID_CALLS_NOT_ALLOWED",
                    $paid,
                    $parsed,
                    []
                );
            }
            if (
                !$ownsClaim &&
                !$this->store->claimCache(
                    $hash,
                    $id,
                    $this->requestEnvelope($address)
                )
            ) {
                $completed = $this->store->cache($hash);
                if (
                    $completed &&
                    $completed["lock_owner"] === null &&
                    $this->fresh($completed) &&
                    $this->mayReuseCompletedCache($cache, $completed, $opt["refreshAddresses"])
                ) {
                    $evidence->put($hash, $completed);
                    continue;
                }
                return $this->finish(
                    $p,
                    "blocked",
                    "CACHE_BUSY",
                    $paid,
                    $parsed,
                    []
                );
            }
            if (!$ownsClaim) {
                    $completed = $this->store->readCompletedCache($hash);
                if (
                    $completed &&
                    $this->fresh($completed) &&
                    $this->mayReuseCompletedCache($cache, $completed, $opt["refreshAddresses"])
                ) {
                    $this->store->discardClaim($hash, $id);
                    $evidence->put($hash, $completed);
                    continue;
                }
            }
            $attempts = 0;
            while (true) {
                $attempts++;
                $reservation = $this->store->reserveAttempt(
                    $id,
                    $hash,
                    $opt["maxPaidRequests"],
                    $opt["budgetReason"],
                    $this->now()
                );
                if (!$reservation["reserved"]) {
                    if (
                        $ownsClaim &&
                        ($reservation["reasonCode"] ?? null) ===
                            "REQUEST_BUDGET_EXCEEDED"
                    ) {
                        $deniedPaid = (int) ($reservation["paidAttempts"] ?? $paid);
                        return array_replace($p["report"], [
                            "status" => "blocked",
                            "reasonCode" => "REQUEST_BUDGET_EXCEEDED",
                            "cost" => [
                                "paidAttempts" => $deniedPaid,
                                "remainingBudget" => max(
                                    0,
                                    (int) ($reservation["remainingBudget"] ?? 0)
                                ),
                            ],
                        ]);
                    }
                    $this->store->discardClaim($hash, $id);
                    return $this->finish(
                        $p,
                        "blocked",
                        $reservation["reasonCode"],
                        $reservation["paidAttempts"] ?? $paid,
                        $parsed,
                        []
                    );
                }
                $paid = (int) $reservation["paidAttempts"];
                $attemptId = (string) $reservation["attemptId"];
                try {
                    $response = $this->provider->clean($address);
                    $this->store->settleAttempt($attemptId, "succeeded");
                    $valid = AddressRegisterAddressValidation::validate(
                        $address,
                        $response
                    );
                    $this->store->storeCache(
                        $hash,
                        $response,
                        (bool) $valid["accepted"],
                        $this->now(),
                        $id
                    );
                    $cache = $this->store->cache($hash);
                    if (!is_array($cache)) throw new \RuntimeException('Completed cache missing');
                    $evidence->put($hash, $cache);
                    break;
                } catch (AddressRegisterProviderFailure $e) {
                    $this->store->settleAttempt(
                        $attemptId,
                        $e->outcomeUnknown ? "unknown" : "failed"
                    );
                    if ($e->outcomeUnknown) {
                        return $this->finish(
                            $p,
                            "unknown",
                            "PROVIDER_OUTCOME_UNKNOWN",
                            $paid,
                            $parsed,
                            []
                        );
                    }
                    $this->store->discardClaim($hash, $id);
                    if (!$e->retryable || $attempts >= 3) {
                        return $this->finish(
                            $p,
                            "failed",
                            $e->reasonCode,
                            $paid,
                            $parsed,
                            []
                        );
                    }
                    if (
                        !$this->store->claimCache(
                            $hash,
                            $id,
                            $this->requestEnvelope($address)
                        )
                    ) {
                        $completed = $this->store->cache($hash);
                        if (
                            $completed &&
                            $completed["lock_owner"] === null &&
                            $this->fresh($completed) &&
                            $this->mayReuseCompletedCache($cache, $completed, $opt["refreshAddresses"])
                        ) {
                            $evidence->put($hash, $completed);
                            break;
                        }
                        return $this->finish(
                            $p,
                            "blocked",
                            "CACHE_BUSY",
                            $paid,
                            $parsed,
                            []
                        );
                    }
                }
            }
        }
        $errorWriter = new AddressRegisterPayloadWriter();
        $errorCount = 0;
        $errorReason = null;
        $seen = [];
        $seenRegistrationNumbers = [];
        $objectWriter = new AddressRegisterPayloadWriter();
        $rowWriter = new AddressRegisterPayloadWriter();
        $summary = $this->summary($parsed);
        $identityLookup = $this->store->findMany($parsed["objects"]);
        $ordinal = 0;
        foreach ($parsed["objects"] as $o) {
            $f = $o["fields"];
            $hash = $this->requestHash($this->normalizeAddress($f["address"]));
            $addressEvidence = $evidence->get($hash);
            $addressValidation = AddressRegisterAddressValidation::validate(
                (string) $f["address"],
                $addressEvidence["response"]
            );
            if (!(bool) ($addressEvidence["accepted"] ?? false) || !$addressValidation["accepted"]) {
                $errorWriter->add($this->addressError($o, $addressEvidence["response"]));
                $errorCount++;
                $errorReason ??= "ADDRESS_UNVERIFIED";
                $ordinal++;
                continue;
            }
            $key = $this->sourceIdentityKey($f);
            if (isset($seen[$key])) {
                $errorWriter->add($this->err($o, "DUPLICATE_SOURCE_OBJECT")); $errorCount++;
                $errorReason ??= "DUPLICATE_SOURCE_OBJECT";
                $ordinal++;
                continue;
            }
            $seen[$key] = 1;
            $registrationNumber = (string) $f["regnumber"];
            if (isset($seenRegistrationNumbers[$registrationNumber])) {
                $errorWriter->add($this->err($o, "IDENTITY_CONFLICT")); $errorCount++;
                $errorReason ??= "IDENTITY_CONFLICT";
            }
            $seenRegistrationNumbers[$registrationNumber] = true;
            $clean = $evidence->get($hash)["response"];
            $f["address"] = $clean["result"];
            $o["fields"] = $f;
            $matches = $identityLookup->find($ordinal, $f);
            $ordinal++;
            if (count($matches) > 1) {
                $errorWriter->add($this->err($o, "IDENTITY_CONFLICT")); $errorCount++;
                $errorReason ??= "IDENTITY_CONFLICT";
            }
            if ($matches) {
                $m = $matches[0];
                if (
                    (($m["regnumber"] ?? "") !== "" &&
                        (string) $m["regnumber"] !==
                            (string) $f["regnumber"]) ||
                    (($m["unom"] ?? "") !== "" && $m["unom"] !== $f["unom"]) ||
                    trim((string) $m["entrance"]) !==
                        trim((string) $f["entrance"]) ||
                    (($m["house_fias_id"] ?? "") !== "" &&
                        !hash_equals(
                            (string) $m["house_fias_id"],
                            (string) $clean["house_fias_id"]
                        )) ||
                    (($m["house_fias_id"] ?? "") === "" &&
                        !AddressRegisterAddressValidation::validate(
                            (string) ($m["ordadr_address"] ?? ""),
                            $clean
                        )["accepted"])
                ) {
                    $errorWriter->add($this->err($o, "IDENTITY_CONFLICT")); $errorCount++;
                    $errorReason ??= "IDENTITY_CONFLICT";
                }
            }
            $d = $matches
                ? $this->store->details((int) $matches[0]["id"])
                : ["_revision" => 0];
            $o["expectedRevision"] = $d["_revision"];
            $o["evidence"] = $clean;
            $objectWriter->add($o);
            $objectChanged = false;
            foreach ($f as $field => $input) {
                    $old = $d[$field]["value"] ?? null;
                if ($input === null) {
                    $action = "SKIPPED_EMPTY"; $set = false;
                } else {
                    $manualTombstone =
                        $matches &&
                        array_key_exists($field, $d) &&
                        $old === null &&
                        ($d[$field]["source"] ?? "") === "manual";
                    $equal =
                        $matches &&
                        $old !== null &&
                        (string) $old === (string) $input;
                    $set =
                        !$equal &&
                        (!$matches ||
                            (!$manualTombstone && $old === null) ||
                            $overwrite);
                    $action = $equal
                        ? "UNCHANGED"
                        : ($set
                            ? ($old === null
                                ? "FILLED"
                                : "REPLACED")
                            : "KEPT_EXISTING");
                }
                    $rowWriter->add([
                        "sheet" => "Адресный перечень",
                        "row" => $o["sourceRow"] ?? ($o["row"] ?? 0),
                        "target" => $this->target,
                        "mode" => $p["mode"],
                        "registrationNumber" => $f["regnumber"],
                        "objectId" => $matches ? (int) $matches[0]["id"] : null,
                        "field" => $field,
                        "action" => $action,
                        "old" => $old,
                        "input" => $input,
                        "value" => $input === null ? $old : ($set ? $input : $old),
                        "reasonCode" => null,
                        "message" => $input === null
                            ? "Пустое значение пропущено."
                            : ($set
                            ? "Будет заполнено пустое поле."
                            : ($equal
                                ? "Значение уже совпадает."
                                : "Сохранено текущее значение.")),
                        "nextAction" =>
                            "Проверьте изменения и примените пакет.",
                    ]);
                    if ($action === "FILLED") { $summary["filled"]++; $objectChanged = true; }
                    elseif ($action === "REPLACED") { $summary["replaced"]++; $objectChanged = true; }
                    elseif ($action === "KEPT_EXISTING") $summary["warnings"]++;
            }
            if (!$matches) $summary["created"]++;
            elseif ($objectChanged) $summary["updated"]++;
            else $summary["unchanged"]++;
        }
        $objects = $objectWriter->finish();
        $rows = $rowWriter->finish();
        $errors = $errorWriter->finish();
        $parsed["objects"] = $objects;
        if ($errorCount > 0) {
            $summary["errors"] = $errorCount;
            $p["report"]["summary"] = $summary;
            return $this->finish(
                $p,
                "rejected",
                $errorReason ?? "IDENTITY_CONFLICT",
                $paid,
                $parsed,
                $rows,
                $errors
            );
        }
        $providerWriter = new AddressRegisterPayloadWriter();
        foreach ($evidence as $h => $e) {
            $providerWriter->add([
                "provider" => "dadata-clean-address",
                "capturedAt" =>
                    str_replace(
                        " ",
                        "T",
                        substr($e["captured_at_utc"], 0, 19)
                    ) . "Z",
                "requestHash" => $h,
                "responseHash" => $e["response_hash"],
                "response" => $e["response"],
            ]);
        }
        $provider = $providerWriter->finish();
        $report = array_replace($p["report"], [
            "status" => "ready",
            "reasonCode" => null,
            "phase" => "preview",
            "summary" => $summary,
            "rows" => $rows,
            "objects" => $objects,
            "providerEvidence" => $provider,
            "cost" => [
                "paidAttempts" => $paid,
                "remainingBudget" => max(
                    0,
                    max((int) $p["budget_limit"], $opt["maxPaidRequests"]) -
                        $paid
                ),
            ],
        ]);
        $sha = AddressRegisterPreviewHasher::hash($report);
        $report["previewSha256"] = $sha;
        $parsed["objects"] = $objects;
        $this->store->readyPackage(
            $id,
            $sha,
            $parsed,
            $report,
            $this->now(),
            $opt["maxPaidRequests"],
            $opt["budgetReason"]
        );
        return $report;
        } finally {
            $this->store->releasePreparation($id, $preparationOwner);
        }
    }
    private function finish(
        array $p,
        string $status,
        string $code,
        int $paid,
        array $source,
        iterable $rows,
        iterable $errors = []
    ): array {
        $r = array_replace($p["report"], [
            "status" => $status,
            "reasonCode" => $code,
            "rows" => $rows,
            "errors" => $errors,
            "cost" => [
                "paidAttempts" => $paid,
                "remainingBudget" => max(0, (int) $p["budget_limit"] - $paid),
            ],
        ]);
        $this->store->updatePackage(
            $p["package_id"],
            $status,
            $r,
            $this->now()
        );
        return $r;
    }
    private function reportBase(
        array $p,
        string $id,
        string $sha,
        string $mode
    ): array {
        return [
            "status" => "blocked",
            "reasonCode" => null,
            "packageId" => $id,
            "previewSha256" => null,
            "target" => $this->target,
            "mode" => $mode,
            "phase" => "preview",
            "source" => ["sheet" => "Адресный перечень", "sha256" => $sha],
            "summary" => $this->summary($p),
            "rows" => [],
            "errors" => $p["errors"],
            "objects" => $p["objects"],
            "providerEvidence" => [],
        ];
    }
    private function hasCurrentFields(array $package): bool
    {
        $found = false;
        foreach (($package["source"]["objects"] ?? []) as $object) {
            $found = true;
            if (array_keys($object["fields"] ?? []) !== self::CURRENT_FIELDS) return false;
        }
        return $found;
    }
    private function previewStale(array $package): array
    {
        return array_replace($package["report"], [
            "status" => "failed",
            "reasonCode" => "PREVIEW_STALE",
        ]);
    }
    private function summary(array $p): array
    {
        return [
            "selected" => count($p["objects"]),
            "excluded" => (int) $p["excluded"],
            "created" => 0,
            "updated" => 0,
            "unchanged" => 0,
            "filled" => 0,
            "replaced" => 0,
            "warnings" => 0,
            "errors" => count($p["errors"]),
        ];
    }
    private function rejected(array $p): array
    {
        return [
            "status" => "rejected",
            "reasonCode" => $p["errors"][0]["reasonCode"] ?? "VALUE_INVALID",
            "packageId" => null,
            "previewSha256" => null,
            "summary" => $this->summary($p),
            "rows" => [],
            "errors" => $p["errors"],
        ];
    }
    private function access(): ?array
    {
        return $this->store->authorized($this->actor)
            ? null
            : $this->error("AUTHORIZATION_DENIED");
    }
    private function error(string $c): array
    {
        return [
            "status" => "failed",
            "reasonCode" => $c,
            "packageId" => null,
            "previewSha256" => null,
            "summary" => [
                "selected" => 0,
                "excluded" => 0,
                "created" => 0,
                "updated" => 0,
                "unchanged" => 0,
                "filled" => 0,
                "replaced" => 0,
                "warnings" => 0,
                "errors" => 1,
            ],
            "rows" => [],
            "errors" => [],
        ];
    }
    private function options(array $x): array
    {
        $d = [
            "allowPaidCalls" => false,
            "maxPaidRequests" => 0,
            "refreshAddresses" => false,
            "retryUnknown" => false,
            "budgetReason" => "",
        ];
        foreach ($x as $k => $v) {
            if (!array_key_exists($k, $d)) {
                throw new \InvalidArgumentException();
            }
        }
        $o = array_replace($d, $x);
        if (
            !is_bool($o["allowPaidCalls"]) ||
            !is_int($o["maxPaidRequests"]) ||
            $o["maxPaidRequests"] < 0 ||
            $o["maxPaidRequests"] > 100000 ||
            !is_bool($o["refreshAddresses"]) ||
            !is_bool($o["retryUnknown"]) ||
            !is_string($o["budgetReason"])
        ) {
            throw new \InvalidArgumentException();
        }
        return $o;
    }
    private function prevalidate(iterable $objects): array
    {
        $seen = [];
        $seenRegistrationNumbers = [];
        $errors = [];
        $identityLookup = $this->store->findMany($objects);
        $ordinal = 0;
        foreach ($objects as $o) {
            $f = $o["fields"];
            $key = $this->sourceIdentityKey($f);
            if (isset($seen[$key])) {
                $errors[] = $this->err($o, "DUPLICATE_SOURCE_OBJECT");
                $ordinal++;
                continue;
            }
            $registrationNumber = (string) $f["regnumber"];
            if (isset($seenRegistrationNumbers[$registrationNumber])) {
                $errors[] = $this->err($o, "IDENTITY_CONFLICT");
                $ordinal++;
                continue;
            }
            $seen[$key] = true;
            $seenRegistrationNumbers[$registrationNumber] = true;
            $matches = $identityLookup->find($ordinal, $f);
            $ordinal++;
            if (count($matches) > 1) {
                $errors[] = $this->err($o, "IDENTITY_CONFLICT");
                continue;
            }
            if ($matches !== []) {
                $match = $matches[0];
                if (
                    (($match["regnumber"] ?? "") !== "" &&
                        (string) $match["regnumber"] !==
                            (string) $f["regnumber"]) ||
                    (($match["unom"] ?? "") !== "" &&
                        (string) $match["unom"] !== (string) $f["unom"]) ||
                    trim((string) ($match["entrance"] ?? "")) !==
                        trim((string) $f["entrance"])
                ) {
                    $errors[] = $this->err($o, "IDENTITY_CONFLICT");
                }
            }
        }
        return $errors;
    }
    private function canonicalizeObjects(iterable $objects): AddressRegisterPayloadSequence
    {
        $writer = new AddressRegisterPayloadWriter();
        foreach ($objects as $object) {
            foreach (["pittype", "pitmaterial"] as $field) {
                $value = $object["fields"][$field] ?? null;
                if ($value !== null) {
                    $object["fields"][$field] =
                        ObjectDetailsReferenceCatalogue::codeForDisplay(
                            $field,
                            $value
                        );
                }
            }
            $writer->add($object);
        }
        return $writer->finish();
    }
    private function addresses(iterable $o): array
    {
        $r = [];
        foreach ($o as $x) {
            $a = $this->normalizeAddress($x["fields"]["address"]);
            $r[$this->requestHash($a)] = $a;
        }
        return $r;
    }
    /** @return array{0:array{objects:AddressRegisterPayloadSequence,excluded:int,errors:AddressRegisterPayloadSequence},1:string} */
    private function scanSnapshot(string $path, int $year, string $contract): array
    {
        $invalid = static fn(string $reason, string $message): array => [["objects"=>AddressRegisterPayloadSequence::fromIterable([]),"excluded"=>0,"errors"=>AddressRegisterPayloadSequence::fromIterable([["reasonCode"=>$reason,"row"=>null,"message"=>$message]])],hash('sha256','')];
        if (!stream_is_local($path) || !is_file($path)) return $invalid("FORMAT_INVALID", "Некорректный файл");
        $size = filesize($path); if ($size === false) return $invalid("FORMAT_INVALID", "Некорректный файл");
        if ($size > 20971520) return $invalid("INPUT_LIMIT_EXCEEDED", "Превышен предел входного файла");
        $source = fopen($path, 'rb'); if (!is_resource($source)) return [["objects"=>AddressRegisterPayloadSequence::fromIterable([]),"excluded"=>0,"errors"=>AddressRegisterPayloadSequence::fromIterable([["reasonCode"=>"FORMAT_INVALID","row"=>null,"message"=>"Некорректный файл"]])], hash('sha256','')];
        $stat=fstat($source);if(!is_array($stat)||(((int)($stat['mode']??0))&0170000)!==0100000){fclose($source);return $invalid("FORMAT_INVALID", "Некорректный файл");}if(($stat['size']??0)>20971520){fclose($source);return $invalid("INPUT_LIMIT_EXCEEDED", "Превышен предел входного файла");}
        $snapshot = tempnam(sys_get_temp_dir(), 'fm2-address-source-'); if (!is_string($snapshot)) { fclose($source); throw new \RuntimeException(); }
        $target = fopen($snapshot, 'wb'); if (!is_resource($target)) { fclose($source); @unlink($snapshot); throw new \RuntimeException(); }
        chmod($snapshot, 0600); $hash = hash_init('sha256');
        try {
            $total=0;while (!feof($source)) { $chunk=fread($source,65536); if($chunk===false)throw new \RuntimeException(); if($chunk==='')break;$total+=strlen($chunk);if($total>20971520)return [["objects"=>AddressRegisterPayloadSequence::fromIterable([]),"excluded"=>0,"errors"=>AddressRegisterPayloadSequence::fromIterable([["reasonCode"=>"INPUT_LIMIT_EXCEEDED","row"=>null,"message"=>"Превышен предел входного файла"]])],hash('sha256','')]; hash_update($hash,$chunk); $offset=0;while($offset<strlen($chunk)){$n=fwrite($target,substr($chunk,$offset));if(!is_int($n)||$n<1)throw new \RuntimeException();$offset+=$n;} }
            if(!fflush($target)||!fsync($target))throw new \RuntimeException(); fclose($target);$target=null;fclose($source);$source=null;
            $objects=new AddressRegisterPayloadWriter();$errors=new AddressRegisterPayloadWriter();
            $counts=AddressRegisterWorkbook::scan($snapshot,$year,$contract,static fn(array$o)=>$objects->add($o),static fn(array$e)=>$errors->add($e));
            return [["objects"=>$objects->finish(),"excluded"=>$counts["excluded"],"errors"=>$errors->finish()],hash_final($hash)];
        } finally { if(is_resource($source))fclose($source);if(is_resource($target))fclose($target);@unlink($snapshot); }
    }
    private function normalizeAddress(string $a): string
    {
        return trim((string) preg_replace("/\s+/u", " ", $a));
    }
    private function sourceIdentityKey(array $fields): string
    {
        return hash("sha256", $this->canonical([
            "registrationNumber" => (string) $fields["regnumber"],
            "unom" => (string) $fields["unom"],
            "entrance" => trim((string) $fields["entrance"]),
            "address" => $this->normalizeAddress((string) $fields["address"]),
        ]));
    }
    private function requestHash(string $address): string
    {
        return hash("sha256", $this->canonical($this->requestEnvelope($address)));
    }
    private function requestEnvelope(string $address): array
    {
        return [
            "provider" => "dadata-clean-address",
            "providerVersion" => "v1",
            "qualityRulesVersion" => "ADDRESS-REGISTER-IMPORT-001@2026-11-18",
            "context" => ["country" => "RU", "regionIsoCode" => "RU-MOW"],
            "request" => ["address" => $address],
        ];
    }
    private function validRequestEnvelope(array $request): bool
    {
        return ($request["provider"] ?? null) === "dadata-clean-address" &&
            ($request["providerVersion"] ?? null) === "v1" &&
            ($request["qualityRulesVersion"] ?? null) ===
                "ADDRESS-REGISTER-IMPORT-001@2026-11-18" &&
            ($request["context"] ?? null) === [
                "country" => "RU",
                "regionIsoCode" => "RU-MOW",
            ] &&
            is_string($request["request"]["address"] ?? null) &&
            $request["request"]["address"] ===
                $this->normalizeAddress($request["request"]["address"]);
    }
    private function fresh(array $c): bool
    {
        if ($c["lock_owner"] !== null) {
            return false;
        }
        $at = new \DateTimeImmutable(
            $c["captured_at_utc"],
            new \DateTimeZone("UTC")
        );
        $age = $this->date()->getTimestamp() - $at->getTimestamp();
        return $age >= 0 &&
            $age <= (int) $this->config["cacheTtlDays"] * 86400;
    }
    private function mayReuseCompletedCache(?array $initial, array $completed, bool $refresh): bool
    {
        if (!$refresh || $initial === null) {
            return true;
        }
        return (int) ($completed["entry_version"] ?? 0) >
            (int) ($initial["entry_version"] ?? 0);
    }
    private function evidenceTime(string $value): \DateTimeImmutable|false
    {
        $date = \DateTimeImmutable::createFromFormat(
            "!Y-m-d\TH:i:s\Z",
            $value,
            new \DateTimeZone("UTC")
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $date === false ||
            ($errors !== false &&
                ($errors["warning_count"] !== 0 || $errors["error_count"] !== 0)) ||
            $date->format("Y-m-d\TH:i:s\Z") !== $value
        ) {
            return false;
        }
        return $date;
    }
    private function now(): string
    {
        return $this->date()
            ->setTimezone(new \DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s.u");
    }
    private function date(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(($this->clock)());
    }
    private function err(array $o, string $c): array
    {
        return [
            "row" => $o["sourceRow"] ?? ($o["row"] ?? 0),
            "field" => "identity",
            "reasonCode" => $c,
            "message" => "Противоречивая идентичность объекта.",
            "nextAction" => "Исправьте исходный перечень.",
        ];
    }
    private function addressError(array $object, array $cleaned): array
    {
        return [
            "row" => $object["sourceRow"] ?? ($object["row"] ?? 0),
            "field" => "address",
            "reasonCode" => "ADDRESS_UNVERIFIED",
            "message" => "Адрес не прошёл строгую проверку.",
            "nextAction" => "Проверьте исходный адрес.",
            "address" => (string) ($object["fields"]["address"] ?? ""),
            "validation" => [
                "accepted" => false,
                "qc" => $cleaned["qc"] ?? null,
                "qcHouse" => $cleaned["qc_house"] ?? null,
                "fiasLevel" => $cleaned["fias_level"] ?? null,
                "fiasActualityState" => $cleaned["fias_actuality_state"] ?? null,
                "regionIsoCode" => $cleaned["region_iso_code"] ?? null,
            ],
        ];
    }
    private function secret(): string
    {
        $s = (string) $this->config["evidenceSecret"];
        if (strlen($s) < 32) {
            throw new \RuntimeException();
        }
        return $s;
    }
    private function canonical(array $x): string
    {
        $sort = function (&$v) use (&$sort) {
            if (!is_array($v)) {
                return;
            }
            foreach ($v as &$z) {
                $sort($z);
            }
            if (!array_is_list($v)) {
                ksort($v, SORT_STRING);
            }
        };
        $sort($x);
        return json_encode(
            $x,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );
    }
    private function canonicalHmac(array $value): string
    {
        $context = hash_init('sha256', HASH_HMAC, $this->secret());
        $write = function (mixed $item) use (&$write, $context): void {
            if (is_array($item) || $item instanceof \Traversable) {
                $isList = !is_array($item) || array_is_list($item);
                hash_update($context, $isList ? '[' : '{'); $first = true;
                if (!$isList) { ksort($item, SORT_STRING); }
                foreach ($item as $key => $child) {
                    if (!$first) hash_update($context, ',');
                    if (!$isList) hash_update($context, json_encode((string)$key, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).':');
                    $write($child); $first = false;
                }
                hash_update($context, $isList ? ']' : '}'); return;
            }
            hash_update($context, json_encode($item, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        };
        $write($value); return hash_final($context);
    }
    private function csv(mixed $v): string
    {
        $s =
            is_scalar($v) || $v === null
                ? (string) $v
                : json_encode(
                    $v,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
        return preg_match('/^\s*[=+\-@\t\r]/u', $s) ? "'" . $s : $s;
    }
    private function writeExclusive(string $path, string $bytes): void
    {
        $h = fopen($path, "xb");
        if (!$h) {
            throw new \RuntimeException();
        }
        try {
            if (!chmod($path, 0600)) {
                throw new \RuntimeException();
            }
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($h, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new \RuntimeException();
                }
                $offset += $written;
            }
            if (!fflush($h) || !fsync($h)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable $error) {
            fclose($h);
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            throw $error;
        } finally {
            if (is_resource($h)) {
                fclose($h);
            }
        }
    }
    private function writeCsvReport(string $path, iterable $rows): void
    {
        $handle = fopen($path, "xb");
        if (!$handle) {
            throw new \RuntimeException();
        }
        $keys = [
            "sheet",
            "row",
            "target",
            "mode",
            "registrationNumber",
            "objectId",
            "field",
            "action",
            "old",
            "input",
            "value",
            "reasonCode",
            "message",
            "nextAction",
        ];
        try {
            if (!chmod($path, 0600)) {
                throw new \RuntimeException();
            }
            if (
                fputcsv(
                    $handle,
                    [
                        "Лист",
                        "Строка",
                        "Контур",
                        "Режим",
                        "Регномер",
                        "ID объекта",
                        "Поле",
                        "Действие",
                        "Старое",
                        "Ввод",
                        "Итог",
                        "Код причины",
                        "Сообщение",
                        "Следующее действие",
                    ],
                    escape: ""
                ) === false
            ) {
                throw new \RuntimeException();
            }
            foreach ($rows as $row) {
                $values = [];
                foreach ($keys as $key) {
                    $values[] = $this->csv($row[$key] ?? "");
                }
                if (fputcsv($handle, $values, escape: "") === false) {
                    throw new \RuntimeException();
                }
            }
            if (!fflush($handle) || !fsync($handle)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable $error) {
            fclose($handle);
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
            throw $error;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
    private function safeExportDirectory(string $directory): string
    {
        if ($directory === "" || !is_dir($directory) || is_link($directory)) {
            throw new \RuntimeException();
        }
        $real = realpath($directory);
        if ($real === false || (fileperms($real) & 0777) !== 0700) {
            throw new \RuntimeException();
        }
        $project = realpath(dirname(__DIR__, 2));
        if ($project !== false && $this->pathWithin($real, $project)) {
            throw new \RuntimeException();
        }
        $absolute = str_starts_with($directory, DIRECTORY_SEPARATOR)
            ? $directory
            : getcwd() . DIRECTORY_SEPARATOR . $directory;
        $prefix = DIRECTORY_SEPARATOR;
        foreach (array_values(array_filter(explode(DIRECTORY_SEPARATOR, $absolute), "strlen")) as $index => $part) {
            if ($index === 0 && in_array($part, ["tmp", "var"], true)) {
                $prefix = realpath(DIRECTORY_SEPARATOR . $part) ?: DIRECTORY_SEPARATOR . $part;
                continue;
            }
            $prefix = rtrim($prefix, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part;
            if (is_link($prefix)) {
                throw new \RuntimeException();
            }
        }
        return $real;
    }
    private function pathWithin(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }
    private function validUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $value
        ) === 1;
    }
    private function uuid(): string
    {
        $h = bin2hex(random_bytes(16));
        return substr($h, 0, 8) .
            "-" .
            substr($h, 8, 4) .
            "-4" .
            substr($h, 13, 3) .
            "-8" .
            substr($h, 17, 3) .
            "-" .
            substr($h, 20, 12);
    }
}
