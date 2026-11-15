<?php
declare(strict_types=1);
namespace FMonitor2\YiiRuntime;

use Yii;

final class ReadOnlySession
{
    public static function release(): void
    {
        $request = Yii::$app->request;
        if (!$request->getIsGet() && !$request->getIsHead()) throw new \LogicException('Read-only session release requires GET or HEAD.');
        if (!Yii::$app->has('session', true) || !Yii::$app->session->isActive) return;
        Yii::$app->session->close();
    }
}
