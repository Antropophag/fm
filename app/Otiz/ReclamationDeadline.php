<?php
declare(strict_types=1);

namespace FMonitor2\Otiz;

final class ReclamationDeadline
{
    public static function resolve(?string $normativeDeadline, ?array $certificate, array $reclamationEvidence): array
    {
        $normative = $normativeDeadline === null ? null : self::date($normativeDeadline);
        if ($certificate !== null) {
            if (!isset($certificate['newDeadline'], $certificate['certificateDate'], $certificate['source'])
                || !is_string($certificate['newDeadline'])
                || !is_string($certificate['certificateDate'])
                || !is_array($certificate['source'])) {
                throw new \InvalidArgumentException('CERTIFICATE_EVIDENCE_INVALID');
            }
            $base = self::date($certificate['newDeadline']);
            self::date($certificate['certificateDate']);
            self::source($certificate['source']);
            $basis = 'certificate';
        } else {
            if ($normative === null) {
                throw new \DomainException('DEADLINE_EVIDENCE_ABSENT');
            }
            $base = $normative;
            $basis = 'normative';
        }

        if (!array_key_exists('items', $reclamationEvidence)
            || !array_key_exists('totalDays', $reclamationEvidence)
            || !array_key_exists('source', $reclamationEvidence)
            || !is_array($reclamationEvidence['items'])
            || !array_is_list($reclamationEvidence['items'])
            || !is_int($reclamationEvidence['totalDays'])
            || $reclamationEvidence['totalDays'] < 0
            || !is_array($reclamationEvidence['source'])) {
            throw new \InvalidArgumentException('RECLAMATION_EVIDENCE_INVALID');
        }
        self::source($reclamationEvidence['source']);
        $sum = 0;
        foreach ($reclamationEvidence['items'] as $item) {
            if (!is_array($item)
                || !isset($item['id'], $item['installationCaseId'], $item['number'], $item['delayDays'], $item['actorId'], $item['recordedAt'])
                || !is_int($item['id']) || $item['id'] < 1
                || !is_int($item['installationCaseId']) || $item['installationCaseId'] < 1
                || !is_string($item['number']) || trim($item['number']) !== $item['number'] || $item['number'] === ''
                || mb_strlen($item['number'], 'UTF-8') > 255
                || !is_int($item['delayDays']) || $item['delayDays'] < 1 || $item['delayDays'] > 65535
                || !is_int($item['actorId']) || $item['actorId'] < 1
                || !is_string($item['recordedAt']) || trim($item['recordedAt']) === '') {
                throw new \InvalidArgumentException('RECLAMATION_EVIDENCE_INVALID');
            }
            try {
                new \DateTimeImmutable($item['recordedAt']);
            } catch (\Throwable) {
                throw new \InvalidArgumentException('RECLAMATION_EVIDENCE_INVALID');
            }
            if ($sum > PHP_INT_MAX - $item['delayDays']) {
                throw new \InvalidArgumentException('RECLAMATION_EVIDENCE_INVALID');
            }
            $sum += $item['delayDays'];
        }
        if ($sum !== $reclamationEvidence['totalDays']) {
            throw new \DomainException('RECLAMATION_EVIDENCE_INVALID');
        }

        $effective = self::date(self::dateObject($base)->modify('+' . $sum . ' days')->format('Y-m-d'));
        $proof = [
            'baseDeadline' => $base,
            'baseBasis' => $basis,
            'certificateSource' => $certificate['source'] ?? null,
            'reclamationsSource' => $reclamationEvidence['source'],
            'reclamationDays' => $sum,
            'effectiveDeadline' => $effective,
        ];
        return [
            'baseDeadline' => $base,
            'baseBasis' => $basis,
            'reclamationDays' => $sum,
            'effectiveDeadline' => $effective,
            'source' => [
                'label' => 'Срок для расчёта просрочки с учётом рекламаций',
                'locator' => 'otiz/reclamation-deadline',
                'contentSha256' => hash('sha256', json_encode($proof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ],
        ];
    }

    private static function source(array $source): void
    {
        if (array_keys($source) !== ['label', 'locator', 'contentSha256']
            || !is_string($source['label']) || trim($source['label']) === ''
            || !is_string($source['locator']) || trim($source['locator']) === ''
            || !is_string($source['contentSha256'])
            || preg_match('/^[a-f0-9]{64}$/D', $source['contentSha256']) !== 1) {
            throw new \InvalidArgumentException('SOURCE_EVIDENCE_INVALID');
        }
    }

    private static function date(string $value): string
    {
        self::dateObject($value);
        return $value;
    }

    private static function dateObject(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('DATE_INVALID');
        }
        return $date;
    }
}
