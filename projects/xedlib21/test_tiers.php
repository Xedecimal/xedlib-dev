<?php

require_once('h_main.php');
require_once('lib/h_template.php');

global $db, $me;

$page_head = '';
$page_body = '';
$page_title = 'Tier Test';

session_start();

$ca = GetVar('ca');

$dsTest = new DataSet($db, 'test');

$t = new Template();

$page_head .= '<script type="text/javascript" src="lib/js/helper.js"></script>';

$page_body .= '<h3>Construction</h3>';

isset($_SESSION['sessForm']) ? $b = $_SESSION['sessForm'] : $b = new Billderator('builder');
$b->Prepare($ca);
$page_body .= $b->Get($me);

$_SESSION['sessForm'] = $b;

$page_body .= '<h3>Editor</h3>';

$ed = new EditorData('edTest', $dsTest);
$ed->Prepare($ca);
$page_body .= EditorData::GetUI($me, $ed->Get($me, GetVar('ci')));

$page_body .= '<h3>Display</h3>';

$dd = new DisplayData('dd', $dsTest);
$dd->Prepare();
$dd->SearchFields = array('name');
$page_body .= $dd->Get($me, $ca);

echo $t->Get('template_test.html');

?>