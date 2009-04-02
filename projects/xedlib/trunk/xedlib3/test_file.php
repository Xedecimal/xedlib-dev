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
$dsLogs = new DataSet($db, 'docmanlog');
$logger = new LoggerAuth($dsLogs, $dsUser);

if (!$user = $lm->Prepare($ca))
{
	die($lm->Get($me));
}

$fm_actions = array(
	FM_ACTION_UNKNOWN => 'Unknown',
	FM_ACTION_CREATE  => 'Created',
	FM_ACTION_DELETE  => 'Deleted',
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

$ca = GetVar('ca');

$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));
$fm->Behavior->Recycle = true;
$fm->Behavior->ShowAllFiles = true;
$fm->Behavior->Watcher = array('fm_watcher');

$fm->Behavior->AllowAll();
$fm->Prepare($ca);

$page_body = "<p><a href=\"{$me}?ca=logout\">Log out</a></p>";
$page_body .= $fm->Get($me, $ca);

$logger->TrimByCount(5);
$page_body .= $logger->Get(10);

$context = null;
$files = count($fm->files['files']);
$dirs = count($fm->files['dirs']);
$page_body .= <<<EOF
	<script type="text/javascript">var dirs = "{$dirs}";</script>
	<script type="text/javascript">var files = "{$files}";</script>
EOF;

$page_head .= <<<EOF
	<script type="text/javascript" src="lib/js/yui/yahoo-min.js" ></script>
	<script type="text/javascript" src="lib/js/yui/connection-min.js" ></script>
	<script type="text/javascript">
	var handleSuccess = function(o)
	{
		if (o.responseText !== undefined)
		{
			var src = document.getElementById(o.argument.id);
			if (o.argument.dir == 'up') var dst = src.previousSibling;
			else var dst = src.nextSibling;
			var insrc = src.innerHTML;
			src.innerHTML = dst.innerHTML;
			dst.innerHTML = insrc;
		}
	}

	var callback =
	{
		success: handleSuccess
	};

	function MoveFile(id, dir, url)
	{
		callback.argument = {'id':id,'dir':dir};
		var request = YAHOO.util.Connect.asyncRequest('POST', url, callback, null);
		return false;
	}
	</script>
EOF;

$t = new Template($context);
echo $t->Get('template_test.html');

?>