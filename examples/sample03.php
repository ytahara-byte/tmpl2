<?php
define( "TMPL_DIR", __DIR__ . '/Templates/');
require_once(__DIR__ . '/../src/tmpl2.php');

use Tmpl2\Tmpl2;

$data = array();
$data[] = array('year'=>2026,'month'=>1,'dr'=>1000,'cr'=>0);
$data[] = array('year'=>2026,'month'=>2,'dr'=>0,'cr'=>1000);
$data[] = array('year'=>2026,'month'=>3,'dr'=>1000,'cr'=>0);
$data[] = array('year'=>2026,'month'=>4,'dr'=>0,'cr'=>1000);
$data[] = array('year'=>2026,'month'=>5,'dr'=>2000,'cr'=>1000);
$data[] = array('year'=>2026,'month'=>6,'dr'=>1000,'cr'=>0);

$tmpl2 = new Tmpl2(TMPL_DIR . 'sample03.CSV');
$tmpl2->setquotes(0);

$tmpl2->loopset('DATA');
foreach($data as $row){
    $mk = mktime(0, 0, 0, $row['month'], 1, $row['year']);
    $tmpl2->assign('YEAR', date("Y",$mk));
    $tmpl2->assign('MONTH', date("F",$mk));
    $tmpl2->assign('DR', $row['dr']);
    $tmpl2->assign('CR', $row['cr']);
    $tmpl2->loopnext('DATA');
}
$tmpl2->loopend('DATA');

$data = $tmpl2->render();

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"data.csv\"");
echo $data;
?>
