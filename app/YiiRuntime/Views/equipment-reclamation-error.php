<?php
declare(strict_types=1);
use FMonitor2\YiiRuntime\ViewSupport;
use yii\helpers\Html;
ViewSupport::begin($this,'Добавить рекламацию',$identity);
?>
<nav class="fm2-breadcrumb" aria-label="Хлебные крошки"><a class="fm2-breadcrumb-link" href="/pilot/objects/<?= (int)$objectId ?>?tab=readiness#equipment-reclamations">Объект монтажа</a><span aria-hidden="true">/</span><span aria-current="page">Добавить рекламацию</span></nav>
<section class="fm2-panel fm2-reclamation-error">
    <h1>Добавить рекламацию</h1>
    <?php if(($errors['_form']??null)!==null):?><p class="fm2-problem" role="alert"><?=Html::encode($errors['_form'])?></p><?php endif?>
    <form method="post" action="/pilot/objects/<?= (int)$objectId ?>/reclamations">
        <?=Html::hiddenInput('_csrf',$csrf)?><?=Html::hiddenInput('requestId',ViewSupport::uuid())?>
        <label class="shlz-field"><span class="shlz-field__label">Номер рекламации</span><span class="shlz-field__control"><input class="shlz-input" name="number" maxlength="255" value="<?=Html::encode($values['number'])?>" required></span></label>
        <label class="shlz-field"><span class="shlz-field__label">Дней задержки</span><span class="shlz-field__control"><input class="shlz-input" type="number" name="delayDays" min="1" max="65535" step="1" value="<?=Html::encode($values['delayDays'])?>"<?=isset($errors['delayDays'])?' aria-invalid="true" aria-describedby="reclamation-days-error"':''?> required></span><?php if(isset($errors['delayDays'])):?><span class="shlz-field__secondary" id="reclamation-days-error" role="alert"><?=Html::encode($errors['delayDays'])?></span><?php else:?><span class="shlz-field__secondary">Целое число от 1 до 65 535.</span><?php endif?></label>
        <div class="shlz-cluster"><a class="shlz-link" href="/pilot/objects/<?= (int)$objectId ?>?tab=readiness#equipment-reclamations">Вернуться к объекту</a><button class="shlz-button shlz-button--primary" type="submit">Добавить</button></div>
    </form>
</section>
<?php ViewSupport::end($this);
