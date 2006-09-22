<?php

session_start();

require_once('lib/h_template.php');
require_once('lib/h_utility.php');

$t = new Template();
$page_body = '';

////////////////////////////////////////////////////////////////////////////////
//Errors
//

function MakeError($message, $type = E_USER_ERROR)
{
	ob_start();
	trigger_error($message, $type);
	return ob_get_clean();
}

class ErrorClass
{
	function MakeError($message, $type = E_USER_ERROR)
	{
		ob_start();
		trigger_error($message, $type);
		return ob_get_clean();
	}
}

$page_body .= "<h3>Errors</h3>\n";

$page_body .= "Setting up errors...<br/>\n";

HandleErrors();

$page_body .= "Passing 2 errors...<br/>";

$e = new ErrorClass();
$error = MakeError('This is a notice.', E_USER_WARNING);
$page_body .= $error;
$error = $e->MakeError('This is a warning in a class.', E_USER_WARNING);
$page_body .= $error;

////////////////////////////////////////////////////////////////////////////////
//Sessions
//

$page_body .= "<h3>Session</h3>";

$page_body .= "Setting a variable...";
$test = SetVar('test', 'value');

$page_body .= "Set test to {$test}<br/>";

$test2 = GetVar('test');

$page_body .= "Grabbing session var 'test' which is: \"{$test2}\"<br/>\n";

$page_body .= "Persisting a variable: \"".Persist('persisted', 'pervalue')."\"<br/>\n";

echo $t->Get('template_test.html');

?>