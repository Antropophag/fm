<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/InspectionFixture.php';
$f=null;
try {
    $f=new InspectionFixture(dirname(__DIR__,2));$f->open();$h=$f->http;$p=$h->p;
    $h->db->query("UPDATE {$p}fm2_workforce_catalog SET reconciliation_state='missing_from_delivery' WHERE installer_tab_id=7002");
    $h->db->query("UPDATE {$p}fm2_installation_cases SET actual_start_date=NULL WHERE id=6101");
    for($i=0;$i<51;$i++)$h->db->query("INSERT INTO {$p}fm2_workforce_catalog(installer_tab_id,fio,position,employment_status,workforce_source,workforce_source_updated_at,reconciliation_state) SELECT ".(8000+$i).",'Дополнительный {$i}',position,employment_status,workforce_source,workforce_source_updated_at,'delivered' FROM {$p}fm2_workforce_catalog WHERE installer_tab_id=7001");
    $page=$f->page('/pilot/installers');assertSameValue(200,$page['status'],'directory remains accessible');
    $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8"'.$page['body']);$xp=new DOMXPath($dom);
    $total=(int)$h->db->query("SELECT COUNT(*) FROM {$p}fm2_workforce_catalog WHERE reconciliation_state='delivered'")->fetch_column();
    assertSameValue((string)$total,$xp->evaluate('string(//*[@data-filtered-count]/@data-filtered-count)'),'INTENDED_RED: count uses same reconciled catalogue');
    assertSameValue((string)$total,trim($xp->evaluate('string(//*[@data-directory-summary="total"])')),'summary same catalogue');
    assertSameValue(0,$xp->query('//*[@data-installer-tab-id="7002"]')->length,'unreconciled row excluded');
    $page2=$f->page('/pilot/installers?page=2');$dom2=new DOMDocument();@$dom2->loadHTML('<?xml encoding="UTF-8"'.$page2['body']);$xp2=new DOMXPath($dom2);
    $ids=[];foreach([$xp,$xp2]as$x)foreach($x->query('//*[@data-installer-tab-id]')as$n)$ids[]=$n->getAttribute('data-installer-tab-id');
    assertSameValue([$total,$total],[count($ids),count(array_unique($ids))],'all permitted rows occur exactly once across pages');
    $searched=$f->page('/pilot/installers?q=7001');$searchedDom=new DOMDocument();@$searchedDom->loadHTML('<?xml encoding="UTF-8"'.$searched['body']);$sx=new DOMXPath($searchedDom);
    assertSameValue(['1',(string)$total],[$sx->evaluate('string(//*[@data-filtered-count]/@data-filtered-count)'),trim($sx->evaluate('string(//*[@data-directory-summary="total"])'))],'filtered one vs whole catalogue explicitly distinct');
    $filtered=$f->page('/pilot/installers?availability=assigned&current=absent');
    assertSameValue(200,$filtered['status'],'assigned without active work filter');
    assertSameValue(true,str_contains($filtered['body'],'data-installer-tab-id="7001"'),'assignment remains visible before actual start');
    assertSameValue(true,str_contains($filtered['body'],'TEST-4512'),'permitted assigned object preserved');
    assertSameValue(false,str_contains($filtered['body'],'Нет действующих закреплений'),'zero current work does not deny assignment');
    echo "PASS MIKRYUKOVA-FEEDBACK-001 directory\n";
} finally {if($f!==null)$f->close();}
