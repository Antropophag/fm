<?php
declare(strict_types=1);

/** Literal v40/v41 fixture data; never derives expectations from production manifests. */
final class AddressRegisterPredecessorFixture
{
    private const TABLES = [
        'fm2_password_events', 'fm2_password_recovery_requests', 'fm2_password_recovery_attempts',
        'fm2_otiz_object_payment_hold_events', 'fm2_otiz_payment_allocations',
    ];
    private const NEXT = [
        'fm2_password_events' => 920001,
        'fm2_password_recovery_attempts' => 920002,
        'fm2_otiz_object_payment_hold_events' => 920003,
        'fm2_otiz_payment_allocations' => 920004,
    ];

    public static function seed(mysqli $db, string $p, int $objectId): array
    {
        $db->query("INSERT INTO {$p}fm2_pilot_users(user_id,full_name,email,status,activation_state,session_version,source_updated_at) VALUES(99992,'Predecessor sentinel','predecessor@example.test',1,'pending_password',7,'2026-11-24T10:00:00Z')");
        $db->query("INSERT INTO {$p}fm2_pilot_auth_credentials(user_id,email_normalized,password_hash,password_set_at,updated_at) VALUES(99992,'predecessor@example.test',NULL,NULL,'2026-11-24T10:00:00Z')");
        $db->query("INSERT INTO {$p}fm2_password_events(id,user_id,action,actor_user_id,occurred_at_utc) VALUES(910001,99992,'recovery_requested',NULL,'2026-11-24 10:00:00.123456')");
        $db->query("INSERT INTO {$p}fm2_password_recovery_requests(request_id,user_id,email_normalized,session_version,token_hash,encrypted_token,key_id,created_at_utc,expires_at_utc,used_at_utc,delivery_state) VALUES('14141414-1414-4414-8414-141414141414',99992,'predecessor@example.test',7,UNHEX(REPEAT('ab',32)),NULL,'synthetic-key','2026-11-24 10:00:00.123456','2026-11-24 10:30:00.123456','2026-11-24 10:01:00.654321','delivered')");
        $db->query("INSERT INTO {$p}fm2_password_recovery_attempts(id,request_id,email_normalized,client_address,attempted_at_utc,accepted) VALUES(910002,'14141414-1414-4414-8414-141414141414','predecessor@example.test','192.0.2.44','2026-11-24 10:00:00.123456',1)");
        // Valid FK parents for the v41 allocation, with independently chosen nonzero cents.
        $db->query("INSERT INTO {$p}fm2_otiz_calculation_revisions(id,revision,status,report_date,actor_user_id,input_json,projection_json,created_at,updated_at) VALUES(910010,1,'accepted','2026-11-24',99992,'{}','{}','2026-11-24T10:00:00Z','2026-11-24T10:00:00Z')");
        $db->query("INSERT INTO {$p}fm2_otiz_recipient_obligations(id,calculation_id,object_id,employee_id,tab_number,full_name,amount_cents) VALUES(910011,910010,$objectId,'sentinel-employee','sentinel-tab','Predecessor employee',12345)");
        $db->query("INSERT INTO {$p}fm2_otiz_payment_facts(id,calculation_id,amount_cents,payment_date,created_at) VALUES(910012,910010,12345,'2026-11-24','2026-11-24T10:00:00Z')");
        $db->query("INSERT INTO {$p}fm2_otiz_object_payment_hold_events(id,object_id,revision,held,reason,actor_user_id,created_at) VALUES(910003,$objectId,1,1,'Historical hold sentinel',99992,'2026-11-24T10:00:00Z')");
        $db->query("INSERT INTO {$p}fm2_otiz_payment_allocations(id,payment_id,obligation_id,amount_cents) VALUES(910004,910012,910011,12345)");
        foreach (self::NEXT as $name => $next) $db->query("ALTER TABLE `{$p}{$name}` AUTO_INCREMENT=$next");
        $snapshot = self::snapshot($db, $p);
        foreach (self::TABLES as $name) assertSameValue(true, count($snapshot[$name]['rows']) > 0, 'populated predecessor '.$name);
        foreach (self::NEXT as $name => $next) assertSameValue($next, $snapshot[$name]['next'], 'independent sequence gap '.$name);
        return $snapshot;
    }

    public static function snapshot(mysqli $db, string $p): array
    {
        $out = [];
        foreach (self::TABLES as $name) {
            $table = $p.$name;
            $rows = $db->query("SELECT * FROM `$table`")->fetch_all(MYSQLI_ASSOC);
            usort($rows, static fn(array $a, array $b): int => strcmp(serialize($a), serialize($b)));
            $statement = $db->prepare('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $statement->execute([$table]);
            $next = $statement->get_result()->fetch_column();
            $out[$name] = ['rows' => $rows, 'ddl' => $db->query("SHOW CREATE TABLE `$table`")->fetch_row()[1], 'next' => $next === null ? null : (int)$next];
        }
        return $out;
    }
}
