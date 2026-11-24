<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/PasswordAccessFixture.php';
$pf=new PasswordAccessFixture();$f=$pf->web;
try {$owner=$pf->owner();
$made=$owner->provision(9101,'block.race@shlz.ru','Block Race');$id=$made['userId'];
$r=$pf->parallel([['method'=>'setInitialPassword','arguments'=>['block.race@shlz.ru',PasswordAccessFixture::PASSWORD,PasswordAccessFixture::PASSWORD]],['method'=>'changeStatus','arguments'=>[9101,$id,'block']]]);
assertSameValue('changed',$r[1]['status'],'block wins final access state');assertSameValue(true,in_array($r[0]['status'],['activated','invalid'],true),'serialized initial outcome');assertSameValue('blocked',$f->user($id)['activation_state'],'initial cannot unblock');
$f->db->query("INSERT INTO {$f->p}fm2_pilot_role_permissions(role_id,permission) VALUES(9201,'access.superadminister')");assertSameValue('changed',$owner->changeRole(9101,9403,9211,'attach')['status'],'second superadministrator');
$r=$pf->parallel([['method'=>'changeRole','arguments'=>[9101,9402,9211,'detach']],['method'=>'changeStatus','arguments'=>[9101,9403,'block']]]);$statuses=array_column($r,'status');sort($statuses);assertSameValue(['changed','invalid'],$statuses,'cross-command last-super serialization');assertSameValue(1,(int)$f->db->query("SELECT COUNT(DISTINCT u.user_id) FROM {$f->p}fm2_pilot_users u JOIN {$f->p}fm2_pilot_user_roles ur ON ur.user_id=u.user_id JOIN {$f->p}fm2_pilot_roles r ON r.role_id=ur.role_id WHERE u.status=1 AND BINARY u.activation_state=BINARY 'active' AND r.status=1 AND BINARY r.code=BINARY 'superadministrator'")->fetch_column(),'at least one last super survives');
echo "PASS: pending-password/block and last-super concurrency\n";
}finally{$pf->close();}
