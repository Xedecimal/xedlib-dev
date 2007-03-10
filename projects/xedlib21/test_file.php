<?php

session_start();

require_once('h_main.php');
require_once('lib/h_template.php');
require_once('lib/a_file.php');
require_once('lib/a_log.php');

$ca = GetVar('ca');

$dsUser = new DataSet($db, 'user');
$lm = new LoginManager();
$lm->AddDataset($dsUser, 'usr_pass', 'usr_name');
if (!$user = $lm->Prepare($ca))
{
	die($lm->Get($me));
}

$fm_actions = array(
	FM_ACTION_UNKNOWN => 'Unknown',
	FM_ACTION_CREATE => 'Created',
	FM_ACTION_DELETE => 'Deleted',
	FM_ACTION_REORDER => 'Reordered',
	FM_ACTION_RENAME => 'Renamed',
	FM_ACTION_UPDATE => 'Updated',
	FM_ACTION_UPLOAD => 'Uploaded',
);

$dsLogs = new DataSet($db, 'docmanlog');
$logger = new LoggerAuth($dsLogs, $dsUser, $fm_actions);

function fm_watcher($action, $target)
{
	global $user, $logger, $fm_actions;

	$logger->Log($user['usr_id'], $action, $target);
}

$page_title = "File Administration Demo";
$page_head = '';

$ca = GetVar('ca');

$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));
$fm->Behavior->Recycle = true;
$fm->Behavior->ShowAllFiles = true;

$fm->Behavior->Watcher = array('fm_watcher');

$fm->Behavior->AllowAll();
$fm->Prepare($ca);
$page_body = $fm->Get($me, $ca);

$logger->TrimByCount(5);
$page_body .= $logger->Get(10);

$context = null;
$t = new Template($context);
echo $t->Get('template_test.html');

?>