<?php

require_once('lib/h_template.php');
require_once('lib/a_file.php');

$page_head = '';

$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));
$fm->Behavior->AllowAll();
$page_body = $fm->Get(GetVar('SCRIPT_NAME'), GetVar('ca'));

$t = new Template();
echo $t->Get('template_test.html');

?>