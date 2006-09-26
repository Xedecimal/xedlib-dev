<?php

$xlpath = 'lib/';

$page_title = 'Storage Tests';

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_data_mysql.php');
require_once('lib/h_display.php');
require_once('lib/h_template.php');

$t = new Template();

$ca = GetVar('ca');
$ci = GetVar('ci');

$db = new Database('test', 'localhost', 'root', 'ransal');
$ds = new DataSet($db, 'test');
$ds->display = array(new DisplayColumn('Name', 'name'));
$ds->fields = array(
	'Name' => array('name', 'text')
);
$ds->AddChild(new Relation($ds, 'id', 'parent'));
$dsChild = new DataSet($db, 'child');
$dsChild->display = array(
	new DisplayColumn('Example', 'example')
);
$dsChild->fields = array(
	'Example' => array('example', 'text')
);
$ds->AddChild(new Relation($dsChild, 'id', 'parent'));

$edTest = new EditorData('test', $ds);
$edTest->Prepare($ca);

$page_body = $edTest->Get($me, $ci);

echo $t->Get('template_test.html');

?>