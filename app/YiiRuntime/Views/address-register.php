<?php

declare(strict_types=1);

use FMonitor2\YiiRuntime\Assets\AddressRegisterAssetBundle;
use FMonitor2\YiiRuntime\MainNavigation;
use FMonitor2\YiiRuntime\ViewSupport;
use yii\helpers\Html;

AddressRegisterAssetBundle::register($this);
$visible = $preferences['visible'];
$ordered = array_values(array_unique([...$visible, ...array_keys($catalogue)]));
$statuses = [''=>'Все стадии','no_case'=>'Нет монтажного дела','needs_assignment_order'=>'Требуется распоряжение','ready_to_open'=>'Готов к открытию','installation'=>'Монтажные работы','document_closeout'=>'Документарное закрытие','completed'=>'Работы завершены','needs_assignment_change'=>'Требуется изменение'];
$tones = ['Монтажные работы'=>'cyan','Работы завершены'=>'bright-green','Документарное закрытие'=>'purple','Требуется распоряжение'=>'orange','Готов к открытию'=>'source-blue','Требуется изменение'=>'pink'];
$format = static function (string $key, mixed $value): string {
    if ($value === null || $value === '') return '—';
    if (is_bool($value)) return $value ? 'Да' : 'Нет';
    if (str_ends_with($key, 'Date') && is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $date)) return $date[3].'.'.$date[2].'.'.$date[1];
    if ($key === 'completionProgress') return (string)$value.'%';
    return is_array($value) ? implode(', ', array_map('strval', $value)) : (string)$value;
};
$json = static fn(array $value): string => json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
ViewSupport::begin($this, 'Адресный перечень', $identity, 'address-register', 'wide');
?>
<div class="fm2-address-register" data-register>
    <header class="fm2-register-header">
        <div class="fm2-register-title"><h1>Адресный перечень</h1><span class="fm2-register-count"><?=(int)$filters['total']?> объектов</span></div>
        <div class="fm2-register-header-actions"><span class="fm2-register-notice" data-register-notice role="status"></span><a class="shlz-button shlz-button--sm shlz-button--icon" href="/pilot/feedback?from=%2Fpilot%2Faddress-register" aria-label="Обратная связь"><?=MainNavigation::icon('chat','shlz-button__icon')?></a></div>
    </header>
    <div class="fm2-register-toolbar">
        <form method="get" action="/pilot/address-register" class="fm2-register-search">
            <label class="shlz-field"><span class="shlz-visually-hidden">Найти объект</span><span class="shlz-field__control"><?=MainNavigation::icon('search','shlz-field__icon')?><input class="shlz-input" type="search" name="q" maxlength="120" value="<?=Html::encode($q)?>" placeholder="Адрес, регномер или завномер"></span></label>
            <?=Html::hiddenInput('status',$status)?><button class="shlz-button shlz-button--primary" type="submit">Найти</button>
        </form>
        <div class="fm2-register-tools">
            <button class="shlz-button" type="button" data-register-filter-open data-shlz-drawer-trigger="register-filters"><?=MainNavigation::icon('filter','shlz-button__icon')?>Фильтры<?php if($status!==''):?><span class="fm2-register-filter-count">1</span><?php endif?></button>
            <button class="shlz-button" type="button" data-register-open data-shlz-drawer-trigger="register-columns"><?=MainNavigation::icon('settings','shlz-button__icon')?>Колонки</button>
        </div>
    </div>
    <?php if($q!==''||$status!==''):?><div class="fm2-register-applied"><span><?=Html::encode($status!==''?$statuses[$status]:'Все стадии')?><?=$q!==''?' · '.Html::encode($q):''?></span><a class="shlz-link" href="/pilot/address-register">Сбросить</a></div><?php endif?>
    <?php if($objects===[]):?>
        <div class="shlz-empty-state fm2-register-empty"><h2 class="shlz-empty-state__title">Объекты не найдены</h2><p class="shlz-empty-state__description">Измените запрос или сбросьте фильтры.</p><div class="shlz-empty-state__actions"><a class="shlz-button" href="/pilot/address-register">Сбросить фильтры</a></div></div>
    <?php else:?>
        <div class="shlz-table-wrap fm2-register-table-scroll" tabindex="0" aria-label="Адресный перечень, прокрутка таблицы">
            <table class="shlz-table fm2-register-table" data-register-table><caption class="shlz-visually-hidden">Данные объектов монтажа</caption>
                <thead class="shlz-table__head"><tr class="shlz-table__row"><?php foreach($ordered as$key):?><th class="shlz-table__cell" scope="col" data-column="<?=Html::encode($key)?>" style="--register-column-width:<?=(int)$preferences['widths'][$key]?>px" <?=in_array($key,$visible,true)?'':'hidden'?>><?=Html::encode($key==='completionProgress'?'Прогресс':$catalogue[$key]['label'])?></th><?php endforeach?></tr></thead>
                <tbody><?php foreach($objects as$object):?><tr class="shlz-table__row"><?php foreach($ordered as$key):$value=$format($key,$object[$key]??null);?><td class="shlz-table__cell" data-column="<?=Html::encode($key)?>" style="--register-column-width:<?=(int)$preferences['widths'][$key]?>px" <?=in_array($key,$visible,true)?'':'hidden'?>>
                    <?php if($key==='address'&&(int)($object['caseId']??0)>0):?><a class="shlz-link fm2-register-address" href="/pilot/objects/<?=(int)$object['id']?>" title="<?=Html::encode($value)?>"><?=Html::encode($value)?></a>
                    <?php elseif($key==='status'):?><span class="shlz-status shlz-status--<?=$tones[$value]??'neutral'?>"><?=Html::encode($value)?></span>
                    <?php else:?><span title="<?=Html::encode($value)?>"><?=Html::encode($value)?></span><?php endif?>
                </td><?php endforeach?></tr><?php endforeach?></tbody>
            </table>
        </div>
    <?php endif?>
    <footer class="fm2-register-footer"><?=ViewSupport::pagination('/pilot/address-register',(int)$filters['page'],(int)$filters['pages'],(int)$filters['total'],50,['q'=>$q,'status'=>$status],'Страницы адресного перечня')?></footer>

    <dialog class="shlz-drawer fm2-register-drawer" id="register-filters" data-shlz-drawer data-register-filter-dialog aria-labelledby="register-filter-title">
        <form class="shlz-drawer__surface" method="get" action="/pilot/address-register">
            <header class="shlz-drawer__header"><h2 class="shlz-drawer__title" id="register-filter-title">Фильтры</h2><button class="shlz-drawer__close" type="button" data-shlz-drawer-close aria-label="Закрыть фильтры"><img src="/pilot/assets/shlz-icons/close.svg" alt=""></button></header>
            <div class="shlz-drawer__body"><?=Html::hiddenInput('q',$q)?><?=ViewSupport::choice('status',$status,$statuses,'Стадия работ')?></div>
            <footer class="shlz-drawer__footer"><a class="shlz-button" href="/pilot/address-register<?= $q!==''?'?q='.rawurlencode($q):'' ?>">Сбросить</a><button class="shlz-button shlz-button--primary" type="submit" data-register-filter-apply>Применить</button></footer>
        </form>
    </dialog>
    <dialog class="shlz-drawer fm2-register-drawer" id="register-columns" data-shlz-drawer data-register-settings aria-labelledby="register-columns-title">
        <div class="shlz-drawer__surface">
            <header class="shlz-drawer__header"><h2 class="shlz-drawer__title" id="register-columns-title">Колонки</h2><button class="shlz-drawer__close" type="button" data-shlz-drawer-close aria-label="Закрыть настройки колонок"><img src="/pilot/assets/shlz-icons/close.svg" alt=""></button></header>
            <div class="shlz-drawer__body">
                <p class="fm2-register-drawer-intro">Настройте таблицу для себя. Адрес всегда остаётся первым.</p>
                <label class="shlz-field"><span class="shlz-visually-hidden">Найти колонку</span><span class="shlz-field__control"><?=MainNavigation::icon('search','shlz-field__icon')?><input class="shlz-input" type="search" placeholder="Найти колонку" maxlength="80" data-register-column-search autocomplete="off" autofocus></span></label>
                <div class="fm2-register-column-tools"><span data-register-selected-count></span><button class="shlz-button shlz-button--text shlz-button--sm" type="button" data-register-reset>По умолчанию</button></div>
                <div class="fm2-register-column-list" data-register-column-list><?php foreach($ordered as$key):$definition=$catalogue[$key];$fixed=$key==='address';?>
                    <div class="fm2-register-column" data-column-key="<?=Html::encode($key)?>" data-column-label="<?=Html::encode(mb_strtolower($definition['label']))?>">
                        <label class="shlz-choice fm2-register-column-choice"><input class="shlz-checkbox shlz-checkbox--sm" type="checkbox" <?=$fixed?'checked disabled':(in_array($key,$visible,true)?'checked':'')?>><span><?=Html::encode($definition['label'])?></span></label>
                        <div class="fm2-register-column-options">
                            <label class="fm2-register-column-width"><span>Ширина</span><span class="shlz-field__control"><input class="shlz-input" type="number" min="80" max="640" step="1" value="<?=(int)$preferences['widths'][$key]?>" aria-label="Ширина: <?=Html::encode($definition['label'])?>"></span><span>px</span></label>
                            <div class="fm2-register-column-move"><button class="shlz-button shlz-button--sm shlz-button--icon" type="button" data-column-up <?=$fixed?'disabled':''?> aria-label="Переместить вверх: <?=Html::encode($definition['label'])?>"><?=MainNavigation::icon('chevron-left-duo','shlz-button__icon fm2-register-move-up')?></button><button class="shlz-button shlz-button--sm shlz-button--icon" type="button" data-column-down <?=$fixed?'disabled':''?> aria-label="Переместить вниз: <?=Html::encode($definition['label'])?>"><?=MainNavigation::icon('chevron-left-duo','shlz-button__icon fm2-register-move-down')?></button></div>
                        </div>
                    </div><?php endforeach?></div>
            </div>
            <p class="fm2-register-status" data-register-status role="status" aria-live="polite"></p>
            <footer class="shlz-drawer__footer"><button class="shlz-button" type="button" data-register-cancel data-shlz-drawer-close>Отмена</button><button class="shlz-button shlz-button--primary" type="button" data-register-save>Сохранить</button></footer>
        </div>
    </dialog>
    <form hidden data-register-preferences-form><?=Html::hiddenInput(Yii::$app->request->csrfParam,Yii::$app->request->csrfToken)?></form>
    <script type="application/json" data-register-state><?=$json($preferences)?></script><script type="application/json" data-register-defaults><?=$json($defaults)?></script>
</div>
<?php ViewSupport::end($this); ?>
