<?php
declare(strict_types=1);
namespace FMonitor2\AddressRegister;

final class Columns
{
    /** @return array<string,array{label:string,width:int,group?:string}> */
    public static function catalogue(): array
    {
        return [
            'address'=>['label'=>'Адрес','width'=>300,'group'=>'Объект'],
            'entrance'=>['label'=>'Подъезд','width'=>100,'group'=>'Объект'],
            'registrationNumber'=>['label'=>'Регистрационный номер','width'=>180,'group'=>'Объект'],
            'factoryNumber'=>['label'=>'Заводской номер','width'=>180,'group'=>'Объект'],
            'status'=>['label'=>'Статус','width'=>200,'group'=>'Ход работ'],
            'completionProgress'=>['label'=>'Выполнение, %','width'=>120,'group'=>'Ход работ'],
            'plannedStartDate'=>['label'=>'Плановое начало','width'=>150,'group'=>'Сроки'],
            'plannedFinishDate'=>['label'=>'Плановое окончание','width'=>170,'group'=>'Сроки'],
            'actualStartDate'=>['label'=>'Фактическое начало','width'=>170,'group'=>'Сроки'],
            'actualFinishDate'=>['label'=>'Фактическое окончание','width'=>190,'group'=>'Сроки'],
            'controlEngineer'=>['label'=>'Стройконтроль','width'=>220,'group'=>'Участники'],
            'installers'=>['label'=>'Монтажники','width'=>280,'group'=>'Участники'],
            'floors'=>['label'=>'Этажность','width'=>110,'group'=>'Характеристики'],
            'weight'=>['label'=>'Грузоподъёмность','width'=>170,'group'=>'Характеристики'],
            'speed'=>['label'=>'Скорость','width'=>120,'group'=>'Характеристики'],
            'lift_type'=>['label'=>'Тип лифта','width'=>160,'group'=>'Характеристики'],
            'pittype'=>['label'=>'Тип шахты','width'=>160,'group'=>'Характеристики'],
            'pitmaterial'=>['label'=>'Материал шахты','width'=>190,'group'=>'Характеристики'],
            'paired'=>['label'=>'Спаренный лифт','width'=>150,'group'=>'Характеристики'],
            'ptoDate'=>['label'=>'Дата акта ПТО','width'=>150,'group'=>'Документы'],
            'declarationDate'=>['label'=>'Дата декларации','width'=>160,'group'=>'Документы'],
            'comment'=>['label'=>'Комментарий','width'=>320,'group'=>'Объект'],
        ];
    }

    /** @return array{revision:int,visible:list<string>,widths:array<string,int>} */
    public static function defaults(): array
    {
        $catalogue=self::catalogue();
        return [
            'revision'=>0,
            'visible'=>['address','entrance','registrationNumber','status','completionProgress','plannedStartDate','plannedFinishDate','actualStartDate','actualFinishDate','controlEngineer','installers'],
            'widths'=>array_map(static fn(array $column):int=>$column['width'],$catalogue),
        ];
    }
}
