<?php

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_display.php');
require_once('lib/h_data.php');

require_once('lib/a_editor.php');

$db = new Database();
$db->Open('mysql://root:ransal@localhost/test');

?>