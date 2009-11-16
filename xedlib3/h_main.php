<?php

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_data.php');
require_once('lib/h_display.php');
require_once('lib/h_module.php');

$_d['me'] = GetVar('SCRIPT_NAME');
$_d['app_abs'] = GetRelativePath(dirname(__FILE__));
$_d['app_rel'] = '';
$_d['page_head'] = '';
$_d['page_title'] = 'Xedlib Tests';

$db = new Database();
$db->Open('mysql://root:ransal@localhost/test');

?>