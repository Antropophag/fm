<?php
declare(strict_types=1);
namespace FMonitor2\Otiz;

final class OtizRegisterAdmission
{
    public static function decorate(array $rows, callable $admission): array
    {
        $memo = [];
        foreach ($rows as &$row) {
            try {$projection = json_decode((string) ($row['projection_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);}
            catch (\Throwable) {$projection = null;}
            $objects=is_array($projection)&&array_keys($projection)!==[]&&isset($projection['objects'])&&is_array($projection['objects'])?$projection['objects']:null;
            if($objects===null||$objects===[]||!array_is_list($objects)){$row['blocking_reason']='Основание выплаты не подтверждено';continue;}
            foreach ($objects as $object) {
                if(!is_array($object)||!is_int($object['objectId']??null)||$object['objectId']<1){$row['blocking_reason']='Основание выплаты не подтверждено';break;}
                $objectId = (int) $object['objectId'];
                if (!array_key_exists($objectId, $memo)) $memo[$objectId] = $admission($objectId);
                $result = $memo[$objectId];
                if (($result['decision'] ?? 'unknown') === 'allow') continue;
                $row['blocking_reason'] = match ((string) ($result['reasonCode'] ?? '')) {
                    'COMPOSITION_MISMATCH' => 'Несоответствие состава',
                    default => 'Основание выплаты не подтверждено',
                };
                break;
            }
        }
        unset($row);
        return $rows;
    }
}
