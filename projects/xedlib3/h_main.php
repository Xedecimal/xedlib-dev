<?php

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_display.php');
require_once('lib/h_data.php');

$db = new Database();
$db->Open('mysql://root:ransal@localhost/test');

?>