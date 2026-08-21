<?php
define( "TMPL_DIR", __DIR__ . '/Templates/');
define( "DEF_LIBPATH" , __DIR__ . '/../src/')
require_once(DEF_LIBPATH  . 'tmpl2.php');

use Tmpl2\Tmpl2;

$tmpl2 = new Tmpl2(TMPL_DIR . 'sample02.txt');
$tmpl2->setquotes(0);
$tmpl2->assign('NAME', 'John Appleseed');
$tmpl2->assign('NUMBER', '12345');

$messeg = $tmpl->render();

?>
