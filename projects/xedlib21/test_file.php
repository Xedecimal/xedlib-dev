<?php

require_once('lib/h_template.php');
require_once('lib/a_file.php');

$page_title = "File Administration Demo";
$page_head = '';

$ca = GetVar('ca');

$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));
$fm->Behavior->Recycle = true;
$fm->Behavior->AllowAll();
$fm->Prepare($ca);
$page_body = $fm->Get($me, $ca);

$t = new Template();
echo $t->Get('template_test.html');

?>