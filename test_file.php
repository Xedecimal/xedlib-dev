<?php

session_start();

require_once('h_main.php');
require_once('lib/a_log.php');
require_once('lib/a_file.php');

$_d['me'] = GetVar('SCRIPT_NAME');

$ca = GetVar('ca');
$ed = GetVar('editor');
$ci = GetVar('ci');

$GLOBALS['app_abs'] = GetRelativePath(dirname(__FILE__)).'/'.basename(__FILE__);
$GLOBALS['app_rel'] = '';

Module::Initialize();

$dsUser = new DataSet($db, 'user', 'usr_id');
$lm = new LoginManager('lm');
$lm->AddDataset($dsUser, 'usr_pass', 'usr_name');
$dsLogs = new DataSet($db, 'docmanlog');
$logger = new LoggerAuth('la', $dsLogs, $dsUser);

if (!$user = $lm->Prepare($ca))
{
	die($lm->Get());
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

$page_body = "<p><a href=\"{$_d['me']}?ca=logout\">Log out</a>
| <a href=\"{$_d['me']}\">Files</a>
| <a href=\"{$_d['me']}?editor=user\">Users</a></p>";

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
	$page_body .= EditorData::GetUI($me, $edUser->Get($me, $ci));
}
else
{
	$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));
	$fm->uid = $user['usr_id'];
	$fm->Behavior->Recycle = true;
	$fm->Behavior->AllowCopy = $fm->Behavior->AllowLink = true;
	$fm->Behavior->ShowAllFiles = $user['usr_name'] == 'Admin';
	$fm->Behavior->Watcher = array('fm_watcher');

	$fm->Behavior->AllowAll();
	$fm->Prepare($ca);

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

	$page_body .= $fm->Get();
	$logger->TrimByCount(5);
	$page_body .= $logger->Get(10);
}

$context = null;
$t = new Template($context);
echo $t->ParseFile('t.xml');

die(Module::Run('t.xml'));

?>