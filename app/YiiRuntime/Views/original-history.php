<?php

declare(strict_types=1);

use FMonitor2\YiiRuntime\ViewSupport;
use yii\helpers\Html;

$objectId = (int) $history['objectId'];
$orderId = (int) $history['orderId'];
$base = '/pilot/objects/' . $objectId . '/assignment-orders/' . $orderId . '/originals';
$display = static fn(mixed $value): string => is_string($value) ? trim($value) : '';
$registrationNumber = $display($objectIdentity['registrationNumber'] ?? null);
$address = $display($objectIdentity['address'] ?? null);
$entrance = $display($objectIdentity['entrance'] ?? null);
$factoryNumber = $display($objectIdentity['factoryNumber'] ?? null);
$instant = static function (string $value): string {
    try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m.Y, H:i'); } catch (Throwable) { return 'Время недоступно'; }
};
$size = static fn(int $bytes): string => $bytes < 1024 ? $bytes . ' Б' : number_format($bytes / 1024, $bytes < 10240 ? 1 : 0, ',', ' ') . ' КиБ';
ViewSupport::begin($this, 'История оригинала', $identity);
?>
<nav class="fm2-breadcrumb" aria-label="Хлебные крошки">
    <a class="fm2-breadcrumb-link" href="/pilot/objects">Объекты</a><span aria-hidden="true">/</span>
    <a class="fm2-breadcrumb-link" href="/pilot/objects/<?= $objectId ?>"><?= Html::encode($registrationNumber !== '' ? $registrationNumber : 'Регномер не указан') ?></a><span aria-hidden="true">/</span>
    <span aria-current="page">История оригинала</span>
</nav>
<div class="fm2-original-history">
    <header class="fm2-original-history__header">
        <div><h1>История оригинала ФН<?= $orderId ?></h1><p>Версия распоряжения <?= (int) $history['orderVersion'] ?> · Редакций: <?= (int) $history['totalRevisions'] ?></p></div>
        <div class="fm2-original-history__context"><strong><?= Html::encode($address !== '' ? $address : 'Адрес не указан') ?><?= $entrance !== '' ? ' · подъезд ' . Html::encode($entrance) : '' ?></strong><span><?= Html::encode($registrationNumber !== '' ? $registrationNumber : 'Регномер не указан') ?> · <?= Html::encode($factoryNumber !== '' ? 'заводской № ' . $factoryNumber : 'заводской номер не указан') ?></span></div>
    </header>

    <section class="fm2-original-history__sheet" aria-label="Редакции подписанного оригинала">
        <?php foreach ($history['revisions'] as $revision): $current = $revision['revisionId'] === $history['currentRevisionId']; ?>
            <article class="fm2-original-history__revision">
                <header>
                    <div class="fm2-original-history__revision-heading"><h2>Редакция <?= (int) $revision['revisionNumber'] ?><?= $current ? ' · текущая' : '' ?></h2></div>
                    <div class="shlz-file-row fm2-original-history__file">
                        <span class="shlz-file-row__visual" aria-hidden="true"><img src="/pilot/assets/shlz-file-types/file-pdf-default.svg" alt=""></span>
                        <span class="shlz-file-row__content"><a class="shlz-file-row__primary" href="<?= $base ?>/<?= Html::encode($revision['revisionId']) ?>/download" title="Подписанный оригинал.pdf">Подписанный оригинал.pdf</a><span class="shlz-file-row__meta">PDF · <?= Html::encode($size((int) $revision['byteSize'])) ?></span></span>
                        <span class="shlz-file-row__actions"><a class="shlz-file-row__action" href="<?= $base ?>/<?= Html::encode($revision['revisionId']) ?>/download" aria-label="Скачать подписанный оригинал, редакция <?= (int) $revision['revisionNumber'] ?>"><img src="/pilot/assets/shlz-icons/download.svg" alt=""></a></span>
                    </div>
                </header>
                <dl class="fm2-original-history__facts">
                    <div><dt>Номер в 1С ДО</dt><dd><?= Html::encode((string) ($revision['externalDocumentNumber'] ?? 'Не указан')) ?></dd></div>
                    <div><dt>Дата распоряжения</dt><dd><time datetime="<?= Html::encode($revision['documentDate']) ?>"><?= Html::encode((new DateTimeImmutable($revision['documentDate']))->format('d.m.Y')) ?></time></dd></div>
                    <div><dt>Дата начала работ</dt><dd><?php if (is_string($revision['appendixStartDate']) && $revision['appendixStartDate'] !== ''): ?><time datetime="<?= Html::encode($revision['appendixStartDate']) ?>"><?= Html::encode((new DateTimeImmutable($revision['appendixStartDate']))->format('d.m.Y')) ?></time><?php else: ?>Не зафиксирована<?php endif ?></dd></div>
                </dl>
                <?php if ($revision['correctionReason']): ?><p class="fm2-original-history__reason"><strong>Причина замены скана</strong><span><?= Html::encode($revision['correctionReason']) ?></span></p><?php endif ?>
                <footer><span>Загрузил: <?= Html::encode((string) ($revision['actorName'] ?? 'Пользователь недоступен')) ?></span><span><time datetime="<?= Html::encode($revision['uploadedAt']) ?>"><?= $instant($revision['uploadedAt']) ?></time> МСК</span></footer>
            </article>
        <?php endforeach ?>
    </section>
    <footer class="fm2-original-history__actions"><a class="shlz-link" href="/pilot/objects/<?= $objectId ?>">К объекту</a><a class="shlz-link" href="<?= $base ?>/submit">Заменить скан</a><a class="shlz-link" href="/pilot/objects/<?= $objectId ?>/assignment-order/selection">Новое распоряжение</a></footer>
</div>
<?php ViewSupport::end($this);
