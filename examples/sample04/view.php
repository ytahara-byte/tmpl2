<?php
ini_set('display_errors', 'on');

require_once("./class.php");

$class = new memberctl();
$class->SetKeyword('a001'); 
$class->InitFiledParamData();
$class->ViewDisplay();
?>
