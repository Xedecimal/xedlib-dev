<?php

//Configuration

$xlpath = 'lib/';
$page_title = 'Storage Tests';

//Requirements

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_data.php');
require_once('lib/h_display.php');
require_once('lib/h_template.php');

require_once('lib/a_editor.php');

//Data

$db = new Database('etap', 'localhost', 'root', 'ransal');
$ds = new DataSet($db, 'task');
$ds->display = array(new DisplayColumn('Name', 'title'));
$ds->fields = array(
	'Name' => array('title', 'text')
);
$ds->AddChild(new Relation($ds, 'id', 'parent'));
$dsComment = new DataSet($db, 'comment');
$dsComment->display = array(
	new DisplayColumn('Comment', 'title')
);
$ds->AddChild(new Relation($dsComment, 'id', 'task'));

//Preparation

$ca = GetVar('ca');
$ci = GetVar('ci');

$t = new Template();

$edTest = new EditorData('test', $ds);
$edTest->Prepare($ca);

//Presentation

$page_body = $edTest->Get($me, $ci);

echo $t->Get('template_test.html');

?>