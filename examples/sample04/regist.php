<?php
ini_set('display_errors', 'on');


require_once("./class.php");

$class = new memberctl();

$class->InitFiledParamData();

if ($class->GetPostFeld()) {
	if (!is_null($class->GetPost('edit'))) {
		$class->EditDisplay();
	} else if (!is_null($class->GetPost('fine'))) {
		$class->DataEntry();
		$class->RegistDisplay();
	} else {
		$class->Notfound();
	}
} else {
	$class->Notfound();
}
?>
