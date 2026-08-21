<?php
ini_set('display_errors', 'on');
define( "TMPL_FILE_DIR", __DIR__ . '/Templates/');

if (!defined('DEF_TMPL2_PATH')) {
define( "DEF_TMPL2_PATH" , __DIR__ . '/../src/');
//define( "DEF_TMPL2_PATH" , './');
};
require_once(DEF_TMPL2_PATH  . 'tmpl2.php');
use Tmpl2\Tmpl2;


$objTmpl = new Tmpl2(TMPL_FILE_DIR . 'sample04/top.html');
$objTmpl->assign('TITLE','Sample-04');
echo $objTmpl->render();
?>