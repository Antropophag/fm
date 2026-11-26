<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

use yii\db\Connection;

final readonly class MariaDbPilotUserDisplayNameQuery
{
    public function __construct(private Connection $db,private string $prefix)
    {
        if(strlen($prefix)>28||preg_match('/^[A-Za-z0-9_]*$/D',$prefix)!==1)throw new \InvalidArgumentException('Invalid table prefix.');
    }

    /** @return array<int,string> */
    public function find(array$userIds):array
    {
        $ids=array_values(array_unique(array_filter(array_map(static fn(mixed$id):int=>(int)$id,$userIds),static fn(int$id):bool=>$id>0)));
        if($ids===[])return[];
        $params=[];$marks=[];
        foreach($ids as$index=>$id){$key=':user'.$index;$marks[]=$key;$params[$key]=$id;}
        $rows=$this->db->createCommand("SELECT user_id,full_name,email FROM `{$this->prefix}fm2_pilot_users` WHERE user_id IN(".implode(',',$marks).')',$params)->queryAll();
        $names=[];
        foreach($rows as$row){$name=trim((string)$row['full_name']);if($name==='')$name=trim((string)$row['email']);$names[(int)$row['user_id']]=$name===''?'Пользователь недоступен':$name;}
        foreach($ids as$id)if(!isset($names[$id]))$names[$id]='Пользователь недоступен';
        return$names;
    }
}
