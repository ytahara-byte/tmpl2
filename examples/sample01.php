<?php
define( "TMPL_DIR", __DIR__ . '/Templates/');
define( "DEF_LIBPATH" , __DIR__ . '/../src/')
require_once(DEF_LIBPATH  . 'tmpl2.php');

use Tmpl2\Tmpl2;

$tmpl2 = new Tmpl2(TMPL_DIR . 'sample01.html');
$tmpl2->assign('TITLE', 'sample01');
$tmpl2->assign('THEME', 'This is a simple theme.');
$tmpl2->assign('LIST1', 'First list item');
$tmpl2->assign('LIST2', 'Second list item');
$tmpl2->assign('LIST3', 'Third list item');
$tmpl2->assign('COPYRIGHT', 'Copyright (c) 2023 Your Company. All rights reserved.');

echo $tmpl->render();

?>
