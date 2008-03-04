<?php

session_start();

require_once('h_main.php');
$GLOBALS['error_file'] = 'debug.txt';
require_once('lib/h_template.php');
require_once('lib/a_file.php');
require_once('lib/a_log.php');

$ca = GetVar('ca');
$ed = GetVar('editor');
$ci = GetVar('ci');

$dsUser = new DataSet($db, 'user', 'usr_id');
$lm = new LoginManager();
$lm->AddDataset($dsUser, 'usr_pass', 'usr_name');
$dsLogs = new DataSet($db, 'docmanlog');
$logger = new LoggerAuth($dsLogs, $dsUser);

$ca = GetVar('ca');

if (!$user = $lm->Prepare($ca))
{
	die($lm->Get($me));
}

$fm_actions = array(
	FM_ACTION_UNKNOWN => 'Unknown',
	FM_ACTION_CREATE  => 'Created',
	FM_ACTION_DELETE  => 'Deleted',
	FM_ACTION_MOVE    => 'Moved',
	FM_ACTION_REORDER => 'Reordered',
	FM_ACTION_RENAME  => 'Renamed',
	FM_ACTION_UPDATE  => 'Updated',
	FM_ACTION_UPLOAD  => 'Uploaded',
);

if ($ca == 'login') $logger->Log($user['usr_id'], 'Logged in', '');

function fm_watcher($action, $target)
{
	global $user, $logger, $fm_actions;
	$logger->Log($user['usr_id'], $fm_actions[$action], $target);
}

$page_title = 'File Administration Demo';
$page_head = '<script type="text/javascript" src="lib/js/swfobject.js"></script>';

$page_body = "<p><a href=\"{$me}?ca=logout\">Log out</a>
| <a href=\"{$me}\">Files</a>
| <a href=\"{$me}?editor=user\">Users</a></p>";

if ($ed == 'user')
{
	$dsUser->DisplayColumns = array(
		'usr_name' => new DisplayColumn('Name'),
	);
	$dsUser->FieldInputs = array(
		'usr_date' => 'NOW()',
		'usr_name' => new FormInput('Name', 'text'),
		'usr_pass' => new FormInput('Password', 'password')
	);
	$dsUser->Description = 'User';
	$edUser = new EditorData('user', $dsUser);
	$edUser->AddHandler(new FileAccessHandler('test'));
	$edUser->Prepare($ca);
	$page_body .= $edUser->GetUI($me, $ci);
}
else
{
	$fm = new FileManager('fman', 'test', array('Gallery'));
	$fm->uid = $user['usr_id'];
	//$fm->Behavior->Recycle = true;
	$fm->Behavior->AllowSearch = true;
	$fm->Behavior->ShowAllFiles = $user['usr_name'] == 'Admin';
	$fm->Behavior->Watcher = array('fm_watcher');
	//$fm->Behavior->QuickCaptions = true;
	$fm->View->Sort = FM_SORT_MANUAL;

	$fm->Behavior->AllowAll();

	$fm->Prepare($ca);
	$page_body .= $fm->Get($me, $ca);

	$logger->TrimByCount(5);
	$page_body .= $logger->Get(10);
}

$context = null;
$t = new Template($context);
echo $t->Get('template_test.html');

?>