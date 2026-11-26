<?php

declare(strict_types=1);

use FMonitor2\YiiRuntime\ViewSupport;
use yii\helpers\Html;

$id = (int) $objectId;
$latest = $latest ?? null;
$selected = $latest['installers'] ?? [];
$assignment = $currentAssignment ?? ['status' => 'missing', 'revision' => 0, 'engineer' => null];
$pending = $latest !== null && !$latest['hasAcceptedOriginal'];
$canUpload = $canUpload ?? false;
$canCorrect = $canCorrect ?? false;
$canReadOriginal = $canReadOriginal ?? false;
$canSelect = $canSelect ?? true;
$hasTemplate = ($lastTemplateAvailable ?? false) === true;
$templateAppendixStartDate = (string) ($lastTemplateAppendixStartDate ?? $object['plannedStartDate'] ?? '');
$path = '/pilot/objects/' . $id . '/assignment-order/selection';
$savedOrderId = $latest === null ? null : (int) $latest['orderId'];
$savedInstallerIds = array_values(array_unique(array_filter(
    array_map(static fn(array $installer): int => (int) ($installer['tabId'] ?? 0), $selected),
    static fn(int $installerId): bool => $installerId > 0,
)));
sort($savedInstallerIds, SORT_NUMERIC);
$savedEngineerId = (int) ($latest['engineer']['userId'] ?? 0);
$currentEngineerId = (int) ($assignment['engineer']['userId'] ?? 0);
$title = $latest === null ? 'Подготовка распоряжения' : 'Распоряжение ФН' . $savedOrderId;

ViewSupport::begin($this, $title, $identity);
?>
<nav class="fm2-breadcrumb" aria-label="Хлебные крошки">
    <a class="fm2-breadcrumb-link" href="/pilot/objects">Объекты монтажа</a>
    <span aria-hidden="true">/</span>
    <a class="fm2-breadcrumb-link" href="/pilot/objects/<?= $id ?>"><?= Html::encode($object['objectRegistrationNumber']) ?></a>
    <span aria-hidden="true">/</span>
    <span aria-current="page">Распоряжение</span>
</nav>

<div class="fm2-order-workflow" data-selection-picker data-search-url="/pilot/objects/<?= $id ?>/assignment-order/installers" data-saved-order-id="<?= $savedOrderId ?? '' ?>" data-saved-installer-ids="<?= Html::encode(implode(',', $savedInstallerIds)) ?>" data-saved-engineer-id="<?= $savedEngineerId > 0 ? $savedEngineerId : '' ?>" data-current-engineer-id="<?= $currentEngineerId > 0 ? $currentEngineerId : '' ?>">
    <header class="fm2-order-workflow__header">
        <h1><?= Html::encode($title) ?></h1>
        <p><?= Html::encode($object['address']) ?> · <?= Html::encode($object['objectRegistrationNumber']) ?></p>
    </header>

    <?php if ($latest && $canSelect): ?>
        <form id="assignment-order-template-form" method="post" action="/pilot/objects/<?= $id ?>/assignment-orders/<?= (int) $latest['orderId'] ?>/template">
            <?= Html::hiddenInput('_csrf', $csrf) ?>
        </form>
    <?php endif ?>

    <form class="fm2-order-workflow__sheet" method="post" action="<?= $path ?>" data-composition-form data-pending="<?= $pending ? 'true' : 'false' ?>">
        <?= Html::hiddenInput('_csrf', $csrf) ?>
        <?= Html::hiddenInput('requestId', ViewSupport::uuid()) ?>
        <?= Html::hiddenInput('mode', $pending ? 'replace_pending' : 'new_order') ?>
        <?= Html::hiddenInput('expectedSelectionRevision', $selectionRevision) ?>
        <?php if ($assignment['status'] === 'found'): ?>
            <?= Html::hiddenInput('expectedControlEngineerAssignmentRevision', $assignment['revision']) ?>
        <?php endif ?>

        <section class="fm2-order-workflow__section" aria-labelledby="composition-title">
            <div class="fm2-order-workflow__section-head">
                <div>
                    <h2 id="composition-title">Состав распоряжения</h2>
                </div>
                <span class="fm2-order-workflow__status" data-composition-status aria-live="polite"></span>
            </div>

            <div class="fm2-picker-selection fm2-order-workflow__crew" data-main-selection aria-live="polite">
                <?php if (!$selected): ?><span class="fm2-picker-selection-empty" data-selection-empty>Монтажники ещё не выбраны</span><?php endif ?>
                <?php foreach ($selected as $installer): ?>
                    <span class="fm2-picker-chip" data-selected-installer data-tab-id="<?= (int) $installer['tabId'] ?>" data-full-name="<?= Html::encode($installer['fullName']) ?>">
                        <span><?= Html::encode($installer['fullName']) ?></span>
                        <small>№ <?= str_pad((string) $installer['tabId'], 6, '0', STR_PAD_LEFT) ?></small>
                        <button type="button" data-remove-installer aria-label="Убрать <?= Html::encode($installer['fullName']) ?>"><img src="/pilot/assets/shlz-icons/close.svg" alt=""></button>
                        <?= Html::hiddenInput('installerTabIds[]', $installer['tabId']) ?>
                    </span>
                <?php endforeach ?>
            </div>

            <div class="fm2-order-workflow__engineer">
                <span>Инженер строительного контроля</span>
                <?php if ($assignment['status'] === 'found'): ?>
                    <strong><?= Html::encode($assignment['engineer']['fullName']) ?></strong>
                <?php else: ?>
                    <strong>Не закреплён</strong><small>Закрепите инженера в карточке объекта.</small>
                <?php endif ?>
            </div>

            <div class="fm2-order-workflow__actions">
                <button class="shlz-button shlz-button--secondary" type="button" data-dialog-open><?= $selected ? 'Изменить состав' : 'Выбрать монтажников' ?></button>
                <button class="shlz-button shlz-button--secondary" type="submit" data-composition-save<?= $assignment['status'] === 'found' ? '' : ' disabled' ?>><?= $pending ? 'Сохранить изменения' : ($latest ? 'Создать новое распоряжение' : 'Сохранить состав') ?></button>
            </div>
        </section>

        <section class="fm2-order-workflow__section" aria-labelledby="template-title">
            <div class="fm2-order-workflow__section-head">
                <div><h2 id="template-title">Шаблон распоряжения</h2></div>
            </div>
            <?php if ($latest && $canSelect): ?>
                <div class="fm2-order-workflow__template-row">
                    <label class="shlz-field">
                        <span class="shlz-field__label">Дата начала работ</span>
                        <span class="shlz-field__control"><input class="shlz-input" type="date" name="appendixStartDate" form="assignment-order-template-form" value="<?= Html::encode($templateAppendixStartDate) ?>" required></span>
                    </label>
                    <button class="shlz-button shlz-button--primary" type="submit" form="assignment-order-template-form" data-template-action disabled>Сформировать шаблон</button>
                </div>
                <div class="fm2-order-workflow__hint" data-template-offer data-saved-order-id="<?= $savedOrderId ?>" data-saved-installer-ids="<?= Html::encode(implode(',', $savedInstallerIds)) ?>" data-saved-engineer-id="<?= $savedEngineerId > 0 ? $savedEngineerId : '' ?>" data-current-engineer-id="<?= $currentEngineerId > 0 ? $currentEngineerId : '' ?>">
                    <p data-template-offer-exact hidden></p>
                    <p data-template-offer-save-first>Сохраните изменения состава перед формированием шаблона.</p>
                </div>
                <?php if ($hasTemplate): ?><a class="fm2-order-workflow__file-link" data-last-template-link href="/pilot/objects/<?= $id ?>/assignment-orders/<?= (int) $latest['orderId'] ?>/template">Скачать последний шаблон</a><?php endif ?>
            <?php else: ?>
                <p class="fm2-order-workflow__hint">Сначала сохраните состав распоряжения.</p>
            <?php endif ?>
        </section>

        <section class="fm2-order-workflow__section" aria-labelledby="original-title">
            <div class="fm2-order-workflow__section-head">
                <div><h2 id="original-title">Подписанный оригинал</h2><p>Согласуйте PDF в 1С ДО и загрузите оригинал с присвоенным номером.</p></div>
            </div>
            <div class="fm2-order-workflow__actions">
                <?php if ($latest && $pending && $canUpload): ?><a class="shlz-button shlz-button--secondary" href="/pilot/objects/<?= $id ?>/assignment-orders/<?= (int) $latest['orderId'] ?>/originals/submit">Загрузить оригинал</a>
                <?php elseif ($latest && !$pending && $canCorrect): ?><a class="shlz-button shlz-button--secondary" href="/pilot/objects/<?= $id ?>/assignment-orders/<?= (int) $latest['orderId'] ?>/originals/submit">Заменить скан</a>
                <?php else: ?><span class="fm2-order-workflow__hint">Загрузка станет доступна после сохранения состава.</span><?php endif ?>
                <?php if ($latest && !$pending && $canReadOriginal): ?><a class="shlz-link" href="/pilot/objects/<?= $id ?>/assignment-orders/<?= (int) $latest['orderId'] ?>/originals/history">История оригинала</a><?php endif ?>
            </div>
        </section>
    </form>

    <footer class="fm2-order-workflow__footer"><a class="shlz-link" href="/pilot/objects/<?= $id ?>">К объекту</a></footer>

    <dialog class="shlz-modal fm2-selection-modal" data-installer-dialog aria-labelledby="installer-dialog-title">
        <div class="shlz-modal__surface">
            <header class="shlz-modal__header"><div><h2 class="shlz-modal__title" id="installer-dialog-title">Выбор монтажников</h2><p>Поиск по ФИО или табельному номеру</p></div><button class="shlz-modal__close" type="button" data-dialog-close aria-label="Закрыть"><img src="/pilot/assets/shlz-icons/close.svg" alt=""></button></header>
            <div class="shlz-modal__body"><div class="fm2-picker-modal-selection"><strong data-picker-count>Выбрано: <?= count($selected) ?></strong><div class="fm2-picker-selection" data-modal-selection></div></div><label class="fm2-search-field"><span>Поиск монтажника</span><input class="shlz-input" type="search" data-installer-search placeholder="Введите минимум 2 символа" autocomplete="off"></label><p class="fm2-picker-result-meta" data-installer-status aria-live="polite">Введите минимум 2 символа</p><div class="fm2-picker-results" data-installer-results></div><button class="shlz-button" type="button" data-installer-more hidden>Показать ещё</button></div>
            <footer class="shlz-modal__footer"><button class="shlz-button shlz-button--primary" type="button" data-dialog-apply>Готово</button></footer>
        </div>
    </dialog>
</div>
<?php ViewSupport::end($this);
