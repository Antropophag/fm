<?php
declare(strict_types=1);
namespace FMonitor2\Otiz;
use yii\db\Connection;

/** Public home-training maintenance facade. */
final class TrainingOtizRecalculation
{
    private MariaDbTrainingOtizRecalculation $owner;

    public function __construct(Connection $db, string $processPrefix, string $legacyPrefix, string $baseUrl, \Closure $clock)
    {
        $this->owner = new MariaDbTrainingOtizRecalculation($db, $processPrefix, $legacyPrefix, $baseUrl, $clock);
    }

    public function preview(int $actor): array
    {
        return $this->owner->preview($actor);
    }

    public function apply(int $actor, string $previewHash, string $operationId): array
    {
        return $this->owner->apply($actor, $previewHash, $operationId);
    }
}
