<?php
define( "TMPL_DIR", __DIR__ . '/Templates/');
require_once(__DIR__ . '/../src/tmpl2.php');

use Tmpl2\Tmpl2;

$tmpl2 = new Tmpl2(TMPL_DIR . 'sample02.txt');
$tmpl2->setquotes(0);
$tmpl2->assign('NAME', 'John Appleseed');
$tmpl2->assign('NUMBER', '12345');

$messeg = $tmpl->render();

?>
