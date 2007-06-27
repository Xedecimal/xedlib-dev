<?php

session_start();

require_once('lib/h_template.php');
require_once('lib/h_utility.php');

$c = null;
$t = new Template($c);
$page_body = '';
$page_title = 'Utility Test';
$page_head = '';

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

$error = MakeError('This is a notice.', E_USER_NOTICE);
$page_body .= $error;
$e = new ErrorClass();
$error = $e->MakeError('This is a warning in a class.', E_USER_WARNING);
$page_body .= $error;

Trace("Here's a trace with debug off<br/>\n");
$debug = true;
Trace("Here's a trace with debug on<br/>\n");

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

////////////////////////////////////////////////////////////////////////////////
//Files
//

$page_body .= "<h3>Files</h3>\n";

$page_body .= "<p>Current relative path: ".GetRelativePath(dirname(__FILE__))."</p>\n";

////////////////////////////////////////////////////////////////////////////////
//Callbacks
//

$page_body .= "<h3>Callbacks</h3>\n";

class DCallback
{
	function FunctionTwo($arg1, $arg2)
	{
		global $page_body;
		$page_body .= "Arg1: {$arg1} and Arg2: {$arg2}<br/>\n";
	}
}

class SCallback
{
	static function FunctionThree($arg1, $arg2, $arg3)
	{
		global $page_body;
		$page_body .= "Arg1: {$arg1} and Arg2: {$arg2} Arg3: {$arg3}<br/>\n";
	}
}

$dc = new DCallback();

$callbacks = array(
	array($dc, 'FunctionTwo'),
	array('SCallback', 'FunctionThree')
);

RunCallbacks($callbacks, 'heya', 'mang', 'three');

echo $t->Get('template_test.html');

?>