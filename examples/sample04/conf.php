<?php
ini_set('display_errors', 'on');

require_once("./class.php");

$class = new memberctl();

$class->InitFiledParamData();

if ($class->GetPostFeld()) {
	if (!is_null($class->GetPost('conf'))) {
		$error = $class->ErrorChek();
		if (count($error) > 0) {
			$class->EditDisplay($error);
		} else {
			$class->ConfDisplay();
		}
	} else {
		$class->Notfound();
	}
} else {
	$class->Notfound();
}
?>
