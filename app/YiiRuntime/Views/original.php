<?php

declare(strict_types=1);

use FMonitor2\YiiRuntime\ViewSupport;
use yii\helpers\Html;

$title = $current ? 'Заменить скан' : 'Загрузить оригинал';
$path = '/pilot/objects/' . (int) $objectId . '/assignment-orders/' . (int) $orderId . '/originals';
$display = static fn(mixed $value): string => is_string($value) ? trim($value) : '';
$registrationNumber = $display($objectIdentity['registrationNumber'] ?? null);
$address = $display($objectIdentity['address'] ?? null);
$entrance = $display($objectIdentity['entrance'] ?? null);
$factoryNumber = $display($objectIdentity['factoryNumber'] ?? null);
$persistedAppendixStartDate = $current ? $display($current['appendixStartDate'] ?? null) : '';
$appendixStartDate = $current ? $persistedAppendixStartDate : $display($suggestedAppendixStartDate ?? ($plannedStartDate ?? ''));
$externalDocumentNumber = $current ? $display($current['externalDocumentNumber'] ?? null) : '';
$appendixStartHelp = $current && $persistedAppendixStartDate === '' ? 'В сохранённой редакции дата не была зафиксирована. Для изменения реквизитов создайте новое распоряжение.' : ($current ? 'Дата сохранённой редакции.' : (($suggestedAppendixFromTemplate ?? false) ? 'Дата из шаблона. Сверьте с подписанным оригиналом.' : 'Предложена плановая дата. Сверьте её с подписанным оригиналом.'));
$currentDocumentDate = $current ? (new DateTimeImmutable((string) $current['documentDate']))->format('d.m.Y') : '';
ViewSupport::begin($this, $title, $identity);
?>
<nav class="fm2-breadcrumb" aria-label="Хлебные крошки">
    <a class="fm2-breadcrumb-link" href="/pilot/objects">Объекты</a><span aria-hidden="true">/</span>
    <a class="fm2-breadcrumb-link" href="/pilot/objects/<?= (int) $objectId ?>"><?= Html::encode($registrationNumber !== '' ? $registrationNumber : 'Регномер не указан') ?></a><span aria-hidden="true">/</span>
    <span aria-current="page">Оригинал</span>
</nav>
<form class="fm2-original-workflow" action="<?= $path ?>" data-original-upload-form data-return-url="/pilot/objects/<?= (int) $objectId ?>">
    <?= Html::hiddenInput('csrfToken', $csrf) ?>
    <?= Html::hiddenInput('requestId', ViewSupport::uuid()) ?>
    <?= Html::hiddenInput('mode', $mode) ?>
    <?= Html::hiddenInput('rootOriginalId', $current['rootId'] ?? '') ?>
    <?= Html::hiddenInput('targetRevisionId', $current['revisionId'] ?? '') ?>
    <?= Html::hiddenInput('expectedCurrentRevisionId', $current['revisionId'] ?? '') ?>
    <?php if (!$current): ?><?= Html::hiddenInput('correctionReason', '') ?><?php endif ?>
    <header class="fm2-original-workflow__header">
        <div><h1><?= $title ?></h1><p>Распоряжение ФН<?= (int) $orderId ?> · версия <?= (int) ($orderVersion ?? 0) ?></p></div>
        <div class="fm2-original-workflow__context"><strong><?= Html::encode($address !== '' ? $address : 'Адрес не указан') ?><?= $entrance !== '' ? ' · подъезд ' . Html::encode($entrance) : '' ?></strong><span><?= Html::encode($registrationNumber !== '' ? $registrationNumber : 'Регномер не указан') ?> · <?= Html::encode($factoryNumber !== '' ? 'заводской № ' . $factoryNumber : 'заводской номер не указан') ?></span></div>
    </header>
    <fieldset class="fm2-original-workflow__sheet" data-original-fields disabled>
        <?php if ($current): ?><div class="fm2-original-workflow__revision"><span><strong>Текущая редакция <?= (int) $current['revisionNumber'] ?> · от <?= Html::encode($currentDocumentDate) ?></strong></span><a class="shlz-link" href="<?= $path ?>/history">История и PDF</a></div><?php endif ?>

        <section class="fm2-original-workflow__crew" aria-labelledby="original-crew-title">
            <h2 id="original-crew-title">Состав распоряжения</h2>
            <div class="fm2-original-workflow__people">
                <?php foreach ($composition['installers'] as $installer): ?><div><strong><?= Html::encode($installer['fullName']) ?></strong><span>Табельный № <?= Html::encode((string) ($installer['tabId'] ?? 'Не указан')) ?></span></div><?php endforeach ?>
            </div>
            <div class="fm2-original-workflow__engineer"><span>Инженер строительного контроля</span><strong><?= Html::encode($composition['engineer']['fullName']) ?></strong></div>
        </section>

        <section class="fm2-original-workflow__document" aria-labelledby="signed-original">
            <div class="fm2-original-workflow__section-head"><h2 id="signed-original">Файл и реквизиты</h2><span>PDF · до 20 МиБ</span></div>
            <label class="fm2-file-drop fm2-original-workflow__drop" data-file-drop><input type="file" name="original" accept="application/pdf,.pdf" required><img src="/pilot/assets/shlz-icons/cloud-upload.svg" data-shlz-icon="cloud-upload" alt=""><span><strong><u>Выберите</u> или перетащите PDF</strong><small data-file-name>Файл не выбран</small></span></label>
            <div class="fm2-original-workflow__fields">
                <?php if ($current): ?>
                    <?= Html::hiddenInput('externalDocumentNumber',$externalDocumentNumber) ?><?= Html::hiddenInput('appendixStartDate',$appendixStartDate) ?>
                    <div class="fm2-original-workflow__fixed-field"><span>Номер в 1С ДО</span><strong><?= Html::encode($externalDocumentNumber !== '' ? $externalDocumentNumber : 'Не зафиксирован') ?></strong></div>
                    <div class="fm2-original-workflow__fixed-field"><span>Дата начала работ</span><strong><?= Html::encode($appendixStartDate !== '' ? (new DateTimeImmutable($appendixStartDate))->format('d.m.Y') : 'Не зафиксирована') ?></strong></div>
                <?php else: ?>
                    <label class="shlz-field"><span class="shlz-field__label">Номер в 1С ДО</span><span class="shlz-field__control"><input class="shlz-input" name="externalDocumentNumber" maxlength="255" value="<?= Html::encode($externalDocumentNumber) ?>" required></span></label>
                    <label class="shlz-field"><span class="shlz-field__label">Дата начала работ</span><span class="shlz-field__control"><input class="shlz-input" type="date" name="appendixStartDate" value="<?= Html::encode($appendixStartDate) ?>" aria-describedby="appendix-start-help" required></span></label>
                    <p class="fm2-original-workflow__field-help" id="appendix-start-help"><?= Html::encode($appendixStartHelp) ?></p>
                <?php endif ?>
                <?php if ($current): ?><label class="shlz-field fm2-original-workflow__reason"><span class="shlz-field__label">Причина замены скана</span><span class="shlz-field__control"><input class="shlz-input" name="correctionReason" maxlength="500" required></span></label><?php endif ?>
            </div>
        </section>

        <footer class="fm2-original-workflow__actions"><div><a class="shlz-link" href="/pilot/objects/<?= (int) $objectId ?>/assignment-order/selection">К составу</a><p><?= $current ? 'Только другой скан того же документа. Для изменения реквизитов или состава создайте новое распоряжение.' : 'После загрузки откроется карточка объекта.' ?></p></div><button class="shlz-button shlz-button--primary" type="submit" data-original-submit><?= $title ?></button></footer>
    </fieldset>
    <noscript><p role="alert">Для загрузки оригинала включите JavaScript и обновите страницу.</p></noscript>
    <div class="fm2-original-status" role="status" aria-live="polite"><progress data-original-progress max="100" value="0" hidden></progress><p data-original-status></p></div>
</form>
<?php ViewSupport::end($this);
