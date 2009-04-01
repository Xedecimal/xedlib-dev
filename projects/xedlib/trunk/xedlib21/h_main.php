<?php

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_display.php');
require_once('lib/h_data.php');

require_once('lib/a_editor.php');

$page_head = '';

$db = new Database();
$db->Open('mysql://root:ransal@localhost/test');

$dsUser = new DataSet($db, 'user');

?>