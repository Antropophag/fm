<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/PreopeningFixture.php';
use FMonitor2\EquipmentReclamation\EquipmentReclamations;
// ORDER-LOCAL-PREVIEW-001 / RECLAMATION-DELAY-001: FG3-01 and FCR-1 at real HTTP seams.
function reviewCardControls(PreopeningFixture $f,array &$cookies,?string $expected,bool $originalControls):void
{
    $before=$f->facts();$page=$f->request('GET','/pilot/objects/4512',[],$cookies);
    assertSameValue(200,$page['status'],'card read admitted');
    $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$page['body']);$xpath=new DOMXPath($dom);
    foreach(['Новое распоряжение','Продолжить подготовку'] as $label){
        $links=$xpath->query('//*[@id="object-panel-documents"]//a[normalize-space()="'.$label.'"]');
        assertSameValue($expected===$label?1:0,$links->length,'INTENDED_RED Documents action '.$label.' state/permission');
        if($links->length)assertSameValue('/pilot/objects/4512/assignment-order/selection',$links->item(0)->getAttribute('href'),'existing public selection route');
    }
    if(!$originalControls)foreach(['История оригинала','Заменить скан'] as $label)assertSameValue(0,$xpath->query('//*[@id="object-panel-documents"]//a[normalize-space()="'.$label.'"]')->length,'selector does not acquire original controls');
    assertSameValue($before,$f->facts(),'card GET creates no business facts');
}
$tests=[
 'Documents accepted/pending and independent permissions'=>static function(PreopeningFixture $f):void {
    $f->start();$owner=[];$f->login($owner);assertSameValue(303,$f->selection($owner)['status'],'selection setup');
    assertSameValue(201,$f->upload($owner,$f->metadata($owner))['status'],'signed original setup');
    reviewCardControls($f,$owner,'Новое распоряжение',true);
    $f->db->query("DELETE FROM {$f->p}fm2_pilot_role_permissions WHERE role_id=7 AND permission<>'objects.read'");
    $selector=[];$f->login($selector,97);reviewCardControls($f,$selector,null,false);
    $f->insert($f->p.'fm2_pilot_role_permissions',['role_id'=>7,'permission'=>'assignment_order.composition.select']);
    reviewCardControls($f,$selector,'Новое распоряжение',false);
    assertSameValue(303,$f->selection($owner,'77777777-0000-4000-8000-000000000001',[7001],'new_order',1)['status'],'pending next document setup');
    reviewCardControls($f,$owner,'Продолжить подготовку',true);
    reviewCardControls($f,$selector,'Продолжить подготовку',false);
    $f->db->query("DELETE FROM {$f->p}fm2_pilot_role_permissions WHERE role_id=7 AND permission='assignment_order.composition.select'");
    reviewCardControls($f,$selector,null,false);
 },
 'Reclamation unavailable evidence is service failure'=>static function(PreopeningFixture $f):void {
    $db=$f->db;$p=$f->p;FMonitor2\Tests\Support\DeadlineCertificateFixture::grants($db,$p);
    $service=new EquipmentReclamations($db,$p,static fn()=>'2026-11-26T12:00:00Z');
    $service->add(['requestId'=>'51515151-0000-4000-8000-000000000001','installationCaseId'=>6101,'actorId'=>18,'number'=>'REVIEW-CLAIM','delayDays'=>65535]);
    $f->start();$cookies=[];$f->login($cookies);
    assertSameValue(200,$f->request('GET','/pilot/objects/4512',[],$cookies)['status'],'healthy card before failure probes');
    $failures=[];
    foreach(['missing','corrupt','deadline_overflow'] as $axis){
        $before=[$f->facts(),$f->rows('fm2_equipment_reclamations')];
        $oldCase=$f->rows('fm2_installation_cases')[0];
        if($axis==='missing')$db->query("RENAME TABLE {$p}fm2_equipment_reclamations TO {$p}fm2_equipment_reclamations_hidden");
        elseif($axis==='corrupt')$db->query("UPDATE {$p}fm2_equipment_reclamations SET normalized_number_sha256=REPEAT('0',64)");
        else $db->query("UPDATE {$p}fm2_installation_cases SET actual_start_date='9999-10-01' WHERE id=6101");
        try{$page=$f->request('GET','/pilot/objects/4512',[],$cookies);}
        finally{
            if($axis==='missing')$db->query("RENAME TABLE {$p}fm2_equipment_reclamations_hidden TO {$p}fm2_equipment_reclamations");
            elseif($axis==='corrupt')$db->prepare("UPDATE {$p}fm2_equipment_reclamations SET normalized_number_sha256=?")->execute([hash('sha256','review-claim')]);
            else $db->prepare("UPDATE {$p}fm2_installation_cases SET actual_start_date=? WHERE id=6101")->execute([$oldCase['actual_start_date']]);
        }
        assertSameValue($before,[$f->facts(),$f->rows('fm2_equipment_reclamations')],$axis.' GET preserves facts');
        foreach(['STORAGE_UNAVAILABLE','EVIDENCE_INVALID','DEADLINE_OVERFLOW'] as $code)assertSameValue(false,str_contains($page['body'],$code),$axis.' hides technical error');
        if($page['status']!==503)$failures[]=$axis.' expected503 got'.$page['status'];
    }
    assertSameValue([],$failures,'INTENDED_RED unavailable reclamation evidence returns503');
    $db->query("DELETE FROM {$p}fm2_pilot_role_permissions WHERE role_id=1 AND permission='objects.read'");
    assertSameValue(403,$f->request('GET','/pilot/objects/4512',[],$cookies)['status'],'actual access denial remains403');
 },
];
$failed=0;foreach($tests as $name=>$test){$f=null;try{$f=new PreopeningFixture(dirname(__DIR__,2));$test($f);echo "PASS $name\n";}catch(Throwable $error){$failed++;echo "FAIL $name: {$error->getMessage()}\n";}finally{if($f)$f->close();}}exit($failed?1:0);
