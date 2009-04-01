<?php

session_start();

require_once('h_main.php');
$GLOBALS['error_file'] = 'debug.txt';
require_once('lib/h_template.php');
require_once('lib/a_file.php');
require_once('lib/a_log.php');

global $db, $me;

$ed = GetState('editor');
$ca = GetVar('ca');
$ci = GetVar('ci');

$dsUser = new DataSet($db, 'user', 'usr_id');
$lm = new LoginManager('lm');
$lm->AddDataset($dsUser, 'usr_pass', 'usr_name');
$dsLogs = new DataSet($db, 'docmanlog');
$logger = new LoggerAuth('logger', $dsLogs, $dsUser);

$ca = GetVar('ca');

$t = new Template();

if (!$user = $lm->Prepare($ca))
{
	$page_title = 'Login';
	$page_body = $lm->Get($me);
	die($t->ParseFile('template_test.xml'));
}

$fm_actions = array(
	FM_ACTION_UNKNOWN  => 'Unknown',
	FM_ACTION_CREATE   => 'Created',
	FM_ACTION_DELETE   => 'Deleted',
	FM_ACTION_DOWNLOAD => 'Downloaded',
	FM_ACTION_MOVE     => 'Moved',
	FM_ACTION_REORDER  => 'Reordered',
	FM_ACTION_RENAME   => 'Renamed',
	FM_ACTION_UPDATE   => 'Updated',
	FM_ACTION_UPLOAD   => 'Uploaded',
);

if ($ca == 'login') $logger->Log($user['usr_id'], 'Logged in', '');

function fm_watcher($action, $target)
{
	global $user, $logger, $fm_actions;
	$logger->Log($user['usr_id'], $fm_actions[$action], $target);
}

$page_title = 'File Administration Demo';

$page_body = "<p><a href=\"{$me}?ca=logout\">Log out</a>
| <a href=\"{$me}?editor=fman\">Files</a>
| <a href=\"{$me}?editor=user\">Users</a>
| <a href=\"{$me}?editor=logs\">Activity</a></p>";

if ($ed == 'user')
{
	if ($ca == 'ajupdate')
	{
		$nval = GetVar('update_value');
		$res = null;
		preg_match('/(.+):(.+):(.+)/', $_POST['element_id'], $res);
		$dsUser->Update(array('usr_id' => $res[3]), array($res[2] => $nval));
		die($nval);
	}

	$dsUser->DisplayColumns = array(
		'usr_name' => new DisplayColumn('Name')
	);
	$dsUser->FieldInputs = array(
		'usr_date' => 'NOW()',
		'usr_name' => new FormInput('Name', 'text'),
		'usr_pass' => new FormInput('Password', 'password')
	);
	$dsUser->Description = 'User';
	$edUser = new EditorData('user', $dsUser);
	$edUser->AddHandler(new FileAccessHandler('test'));
	$edUser->AddHandler($logger);
	$edUser->Prepare($ca);
	$page_body .= $edUser->GetUI($me, $ci);

	$page_head .= <<<EOF
<script type="text/javascript" src="lib/js/jquery.inplace.js"></script>
<script type="text/javascript">
//<![CDATA[
$(document).ready(function () {
	$('.editor_cell').editInPlace({
		url: "$me",
		params: "editor=user&ca=ajupdate"
	});
});
//]]>
</script>
EOF;
}

if ($ed == 'logs')
{
	$page_head .= <<<EOF
<script type="text/javascript" src="lib/js/jquery.tablefilter.js"></script>
<script type="text/javascript">
function filter_callback(item, col)
{
	if (this.value == 'No Filter') val = '';
	else val = this.value;
	$.uiTableFilter($('#logger_table'), val, col)
}

function Populate(name, col)
{
	sel = $('#filter_'+name);

	//Event Handlers
	if (sel[0].type == 'text') { sel.keyup(filter_callback); return; }
	else sel.change(filter_callback);

	items = $('[id ^= logger_table:'+name+']');
	opts = Array();
	for (ix = 0; ix < items.length; ix++) opts[items[ix].innerHTML] = 1;
	ix = 0;
	sel[0].options[ix++] = new Option('No Filter');
	for (opt in opts) sel[0].options[ix++] = new Option(opt);
}

$(document).ready(function () {
	Populate('log_date', 'Date');
	Populate('usr_name', 'User');
	Populate('log_action', 'Action');
	Populate('log_target', 'Target');
});
</script>
EOF;
	$page_body .= 'Date: <select id="filter_log_date"></select>';
	$page_body .= 'User: <select id="filter_usr_name"></select>';
	$page_body .= 'Action: <select id="filter_log_action"></select>';
	$page_body .= 'Target: <input type="text" id="filter_log_target" />';
	$page_body .= $logger->Get();
}

function fm_callback($file, $path)
{
	return array('ca' => 'viewfile', 'cf' => urlencode($path));
}

$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));

if ($ca == 'viewfile')
{
	$cf = GetVar('cf');
	$pi = pathinfo($cf);
	$fi = new FileInfo($cf);
	$ftype = $pi['extension'];

	$rpath = substr($fi->dir, strlen($fm->Root));

	$logger->Log($user['usr_id'], $fm_actions[FM_ACTION_DOWNLOAD], $cf);

	if ($ftype == 'flv')
	{
		$server = "http://".GetVar('HTTP_HOST').'/';

		if (count(explode('/', GetVar('cf'))) > 2)
			$page_body .= <<<EOF
NOTE: Video <b>must finish in its entirety and reset back to 0%</b> before your
quiz will be available.<br />
Once this has happened, click <a href="{$me}?cf={$rpath}">here</a> to return
and complete a	short quiz. <br>Clicking away from this page before completing
your video will require you to start the video from the beginning.

<script type="text/javascript" src="xedlib/js/swfobject.js"></script>
<div id="flashdiv">
You don't have <a href="http://www.adobe.com/go/flash">Flash!</a>
</div>
<script type="text/javascript">
var so = new SWFObject('flash/SafeAssure.swf', 'mymovie', '550', '550', '8', '#FFFFFF');
so.addParam('flashvars', 'flvLocation={$cf}&amp;server={$server}');
so.write('flashdiv');
</script>
EOF;
		die($t->ParseFile('template_test.html'));
	}
	else if ($ftype == 'pdf')
	{
		$page_body .= '<object data="'.rawurlencode($cf).'" type="application/pdf"
			width="700" height="500"> alt : <a href="'.rawurlencode($cf).'">'.
			$fi->filename.'</a></object>';
	}
	else if ($ftype == 'jpg' || $ftype == 'jpeg' ||
			 $ftype == 'gif' || $ftype == 'png')
	{
		$page_body .= "<img src=\"{$fi->path}\" />";
	}
	else
	{
		$page_body .= <<<EOF
<script type="text/javascript">
window.open('/{$cf}','file');
</script>
EOF;
	}

	$page_body .= "<p>Click <a href=\"{$me}?cf=".urlencode($rpath)."\">here</a> to return.</p>";
	die($t->ParseFile('template_test.xml'));
}

if ($ed == 'fman')
{
	$fm->Behavior->FileCallback = 'fm_callback';
	$fm->uid = $user['usr_id'];
	//$fm->Behavior->Recycle = true;
	$fm->Behavior->AllowSearch = true;
	$fm->Behavior->ShowAllFiles = $user['usr_name'] == 'Admin';
	$fm->Behavior->Watcher = array('fm_watcher');
	//$fm->View->ShowTitle = true;
	//$fm->Behavior->QuickCaptions = true;
	$fm->View->Sort = FM_SORT_MANUAL;

	$fm->Behavior->AllowAll();

	$fm->Prepare();
	$page_body .= $fm->Get();

	$logger->TrimByDate(mktime(0, 0, 0, date('m'), date('d'), date('y')-1));
	$page_body .= $logger->Get();
}

echo $t->ParseFile('template_test.xml');

?>