<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/InspectionFixture.php';
// FLEXIBLE-ADDRESS-SEARCH-001: active construction-control HTTP and real actor filters.
$f=null;
try{$f=new InspectionFixture(dirname(__DIR__,2));$f->open();$f->queueFixtures();$h=$f->http;$p=$h->p;
$h->db->query("UPDATE {$p}fm_maintable SET ordadr_address='ул. Берёзовая, д.12, корп.2',entrance='3' WHERE id=4512");
$h->db->query("UPDATE {$p}fm_maintable SET ordadr_address='ул. Берёзовая, д.112, корп.2',entrance='3' WHERE id=4513");
$before=$h->facts();foreach(['березовая 12 2 3','к2 березовя п3 д12','д12к2п3']as$q){$r=$f->page('/pilot/construction-control?ownership=all&query='.rawurlencode($q));assertSameValue(200,$r['status'],'control status');preg_match_all('/data-object-id="(\d+)"/',$r['body'],$m);assertSameValue(['4512'],$m[1],'INTENDED_RED control structured search '.$q);}
$r=$f->page('/pilot/construction-control?ownership=all&query='.rawurlencode('березовая д12 п13'));assertSameValue(false,str_contains($r['body'],'data-object-id="4512"'),'entrance exact');
foreach(['CONTROL-4512',$h->db->query("SELECT regnumber FROM {$p}fm_maintable WHERE id=4512")->fetch_column()]as$needle){$r=$f->page('/pilot/construction-control?ownership=all&query='.rawurlencode($needle));assertSameValue(true,str_contains($r['body'],'data-object-id="4512"'),'control preserves number search');}
$h->db->query("UPDATE {$p}fm_maintable SET ordadr_address='ул. Берёзовая, д.12, корп.2',entrance='3' WHERE id=4513");
$r=$f->page('/pilot/construction-control?ownership=mine&query='.rawurlencode('березовая 12 2 3'));preg_match_all('/data-object-id="(\d+)"/',$r['body'],$m);assertSameValue(['4512'],$m[1],'same address owned positive + foreign negative');
$h->db->query("UPDATE {$p}fm_maintable SET ordadr_address='ул. Берёзовая, д.112, корп.2',entrance='3' WHERE id=4513");assertSameValue($before,$h->facts(),'control search no facts');
echo "PASS: FLEXIBLE-ADDRESS-SEARCH-001 construction control\n";
}finally{if($f instanceof InspectionFixture)$f->close();}
