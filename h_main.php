<?php

require_once('lib/classes/Server.php');
require_once('lib/classes/File.php');
require_once('lib/classes/data/Database.php');
Server::HandleErrors();

$_d['me'] = Server::GetVar('SCRIPT_NAME');
$_d['app_abs'] = Server::GetRelativePath(dirname(__FILE__));
$_d['app_rel'] = '';
$_d['page_head'] = '';
$_d['page_title'] = 'Xedlib Tests';

$_d['settings']['database'] = 'mysqli://root:ransal@localhost/test';

$db = new Database();
$db->Open($_d['settings']['database']);

?>