<?php

session_start();

require_once('h_main.php');
require_once('lib/classes/LoggerAuth.php');
require_once('lib/modules/FileManager/FileManager.php');
require_once('lib/modules/user/user.php');

Module::Initialize(dirname(__FILE__), true);

$dsUser = new DataSet($db, 'user', 'usr_id');
$dsLogs = new DataSet($db, 'docmanlog');
$logger = new LoggerAuth('la', $dsLogs, $dsUser);

$user = ModUser::Authenticate();

$fm_actions = array(
	FM_ACTION_UNKNOWN => 'Unknown',
	FM_ACTION_CREATE  => 'Created',
	FM_ACTION_DELETE  => 'Deleted',
	FM_ACTION_REORDER => 'Reordered',
	FM_ACTION_RENAME  => 'Renamed',
	FM_ACTION_UPDATE  => 'Updated',
	FM_ACTION_UPLOAD  => 'Uploaded',
);

$page_title = 'File Administration Demo';

$ca = Server::GetVar('ca');

$_d['nav.links']['Log out'] = '{{app_abs}}?ca=logout';
$_d['nav.links']['Files'] = '{{app_abs}}';
$_d['nav.links']['Users'] = '{{app_abs}}?editor=user';

class TestFile extends Module
{
	function Get()
	{
		if (!file_exists('test')) mkdir('test');
		$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));
		#$fm->uid = $user['usr_id'];
		$fm->Behavior->Recycle = true;
		$fm->Behavior->AllowCopy = $fm->Behavior->AllowLink = true;
		#$fm->Behavior->ShowAllFiles = $user['usr_name'] == 'Admin';
		$fm->Behavior->Watcher = array('fm_watcher');

		global $_d;
		$fm->Behavior->Target = $_d['app_abs'].'/test_file.php';

		$fm->Behavior->AllowAll();
		$fm->Prepare();

		return $fm->Get();
	}
}
Module::Register('TestFile');

die(Module::Run('t.xml'));

?>