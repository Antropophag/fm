<?php
declare(strict_types=1);
namespace FMonitor2\AddressRegister;

final class Columns
{
    /** @return array<string,array{label:string,group?:string}> */
    public static function catalogue(): array
    {
        return [
            'registrationNumber'=>['label'=>'Регистрационный номер','group'=>'Объект'],
            'address'=>['label'=>'Адрес','group'=>'Объект'],
            'entrance'=>['label'=>'Подъезд','group'=>'Объект'],
            'factoryNumber'=>['label'=>'Заводской номер','group'=>'Объект'],
            'status'=>['label'=>'Статус','group'=>'Ход работ'],
            'completionProgress'=>['label'=>'Выполнение, %','group'=>'Ход работ'],
            'plannedStartDate'=>['label'=>'Плановое начало','group'=>'Сроки'],
            'plannedFinishDate'=>['label'=>'Плановое окончание','group'=>'Сроки'],
            'actualStartDate'=>['label'=>'Фактическое начало','group'=>'Сроки'],
            'actualFinishDate'=>['label'=>'Фактическое окончание','group'=>'Сроки'],
            'controlEngineer'=>['label'=>'Стройконтроль','group'=>'Участники'],
            'installers'=>['label'=>'Монтажники','group'=>'Участники'],
            'floors'=>['label'=>'Этажность','group'=>'Характеристики'],
            'weight'=>['label'=>'Грузоподъёмность','group'=>'Характеристики'],
            'speed'=>['label'=>'Скорость','group'=>'Характеристики'],
            'lift_type'=>['label'=>'Тип лифта','group'=>'Характеристики'],
            'pittype'=>['label'=>'Тип шахты','group'=>'Характеристики'],
            'pitmaterial'=>['label'=>'Материал шахты','group'=>'Характеристики'],
            'paired'=>['label'=>'Спаренный лифт','group'=>'Характеристики'],
            'ptoDate'=>['label'=>'Дата акта ПТО','group'=>'Документы'],
            'declarationDate'=>['label'=>'Дата декларации','group'=>'Документы'],
            'comment'=>['label'=>'Комментарий','group'=>'Объект'],
        ];
    }

    /** @return array{revision:int,visible:list<string>,layoutVersion:int} */
    public static function defaults(): array
    {
        return [
            'revision'=>0,
            'visible'=>array_keys(self::catalogue()),
            'layoutVersion'=>2,
        ];
    }
}
