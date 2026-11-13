<?php

declare(strict_types=1);

use FMonitor2\YiiRuntime\Assets\AddressRegisterAssetBundle;
use FMonitor2\YiiRuntime\ViewSupport;
use yii\helpers\Html;

AddressRegisterAssetBundle::register($this);
$visible = $preferences['visible'];
$ordered = array_values(array_unique([...$visible, ...array_keys($catalogue)]));
$statuses = [
    '' => 'Все стадии',
    'no_case' => 'Нет монтажного дела',
    'needs_assignment_order' => 'Требуется распоряжение',
    'ready_to_open' => 'Готов к открытию',
    'installation' => 'Монтажные работы',
    'document_closeout' => 'Документарное закрытие',
    'completed' => 'Работы завершены',
    'needs_assignment_change' => 'Требуется изменение',
];
$format = static function (mixed $value): string {
    if ($value === null || $value === '') return '—';
    if (is_bool($value)) return $value ? 'Да' : 'Нет';
    if (is_array($value)) return $value === [] ? '—' : implode(', ', array_map('strval', $value));
    return (string) $value;
};
$query = ['q' => $q, 'status' => $status];
$stateJson = json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
ViewSupport::begin($this, 'Адресный перечень', $identity, 'address-register', 'wide');
?>
<div class="fm2-address-register" data-register>
    <header class="fm2-page-header fm2-register-header">
        <div><h1>Адресный перечень</h1><p>Сводные данные по объектам монтажа</p></div>
        <button class="shlz-button shlz-button--secondary" type="button" data-register-open aria-expanded="false" aria-controls="register-settings">Колонки</button>
    </header>

    <section class="fm2-register-settings" id="register-settings" data-register-settings hidden aria-labelledby="register-settings-title">
        <div class="fm2-register-settings__heading">
            <div><h2 id="register-settings-title">Колонки</h2><p>Выберите состав, порядок и ширину таблицы.</p></div>
            <label class="shlz-field fm2-register-column-search"><span class="shlz-field__label">Найти колонку</span><span class="shlz-field__control"><input class="shlz-input" type="search" maxlength="80" data-register-column-search autocomplete="off"></span></label>
        </div>
        <div class="fm2-register-column-list" data-register-column-list>
        <?php foreach ($ordered as $key): $definition = $catalogue[$key]; $isAddress = $key === 'address'; ?>
            <div class="fm2-register-column" data-column-key="<?=Html::encode($key)?>" data-column-label="<?=Html::encode(mb_strtolower($definition['label']))?>">
                <label class="shlz-choice fm2-register-column__choice"><input class="shlz-checkbox" type="checkbox" <?=$isAddress?'checked disabled':(in_array($key, $visible, true)?'checked':'')?>><span><?=Html::encode($definition['label'])?></span></label>
                <div class="fm2-register-column__move" aria-label="Порядок колонки <?=Html::encode($definition['label'])?>">
                    <button class="shlz-button shlz-button--sm" type="button" data-column-up <?=$isAddress?'disabled':''?>>Выше</button>
                    <button class="shlz-button shlz-button--sm" type="button" data-column-down <?=$isAddress?'disabled':''?>>Ниже</button>
                </div>
                <label class="fm2-register-column__width"><span>Ширина</span><input class="shlz-input" type="number" min="80" max="640" step="1" value="<?=(int)$preferences['widths'][$key]?>"><span>px</span></label>
            </div>
        <?php endforeach ?>
        </div>
        <div class="fm2-register-settings__actions">
            <button class="shlz-button shlz-button--primary" type="button" data-register-save>Сохранить</button>
            <button class="shlz-button shlz-button--secondary" type="button" data-register-reset>По умолчанию</button>
            <button class="shlz-button shlz-button--secondary" type="button" data-register-cancel>Отмена</button>
            <span class="fm2-register-status" data-register-status role="status" aria-live="polite"></span>
        </div>
    </section>

    <section class="fm2-list-surface fm2-register-surface">
        <form method="get" action="/pilot/address-register" class="fm2-user-toolbar fm2-register-toolbar">
            <label class="shlz-field fm2-register-search"><span class="shlz-field__label">Поиск</span><span class="shlz-field__control"><input class="shlz-input" type="search" name="q" maxlength="120" value="<?=Html::encode($q)?>" placeholder="Адрес, регномер, завномер, подъезд"></span></label>
            <?=ViewSupport::choice('status', $status, $statuses, 'Стадия')?>
            <button class="shlz-button" type="submit">Показать</button>
            <?php if ($q !== '' || $status !== ''): ?><a class="shlz-button shlz-button--secondary" href="/pilot/address-register">Сбросить</a><?php endif ?>
        </form>

        <div class="fm2-register-summary"><strong><?=(int)$filters['total']?></strong><span><?=((int)$filters['total']===1?'объект':'объектов')?></span></div>
        <?php if ($objects === []): ?>
            <div class="shlz-empty-state shlz-empty-state--basic fm2-register-empty"><div class="shlz-empty-state__content"><h2>Ничего не найдено</h2><p>Измените поиск или сбросьте фильтры. Настройки колонок доступны выше.</p></div></div>
        <?php else: ?>
            <div class="shlz-table-wrap fm2-register-table-scroll" tabindex="0" aria-label="Адресный перечень, горизонтальная прокрутка">
                <table class="shlz-table fm2-register-table" data-register-table>
                    <thead class="shlz-table__head"><tr class="shlz-table__row">
                    <?php foreach ($ordered as $key): ?>
                        <th class="shlz-table__cell" scope="col" data-column="<?=Html::encode($key)?>" style="--register-column-width:<?=(int)$preferences['widths'][$key]?>px" <?=in_array($key,$visible,true)?'':'hidden'?>><?=Html::encode($catalogue[$key]['label'])?></th>
                    <?php endforeach ?>
                    </tr></thead>
                    <tbody><?php foreach ($objects as $object): ?><tr class="shlz-table__row">
                    <?php foreach ($ordered as $key): $value=$format($object[$key]??null); ?>
                        <td class="shlz-table__cell" data-column="<?=Html::encode($key)?>" data-label="<?=Html::encode($catalogue[$key]['label'])?>" style="--register-column-width:<?=(int)$preferences['widths'][$key]?>px" <?=in_array($key,$visible,true)?'':'hidden'?>>
                            <?php if ($key==='address' && (int)($object['caseId']??0)>0): ?><a class="shlz-link fm2-register-address" href="/pilot/objects/<?=(int)$object['id']?>" title="<?=Html::encode($value)?>"><?=Html::encode($value)?></a><?php else: ?><span title="<?=Html::encode($value)?>"><?=Html::encode($value)?></span><?php endif ?>
                        </td>
                    <?php endforeach ?>
                    </tr><?php endforeach ?></tbody>
                </table>
            </div>
        <?php endif ?>
        <?=ViewSupport::pagination('/pilot/address-register', (int)$filters['page'], (int)$filters['pages'], (int)$filters['total'], 50, $query, 'Страницы адресного перечня')?>
    </section>
    <form hidden data-register-preferences-form><?=Html::hiddenInput(Yii::$app->request->csrfParam,Yii::$app->request->csrfToken)?></form>
    <script type="application/json" data-register-state><?=$stateJson?></script>
    <script type="application/json" data-register-defaults><?=json_encode($defaults, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_THROW_ON_ERROR)?></script>
</div>
<?php ViewSupport::end($this); ?>
