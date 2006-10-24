<?php

//Configuration

$page_title = 'Storage Tests';
$page_head = '';

//Requirements

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_data.php');
require_once('lib/h_display.php');
require_once('lib/h_template.php');

require_once('lib/a_editor.php');

//Data

$v = new Validation('name', '.+', 'You must specify a name.');

$ret = FormValidate('formtest', $v, isset($ca));

$page_head .= '<script type="text/javascript">'."\n".$ret['js'].'</script>';

$db = new Database('test', 'localhost', 'root', 'ransal');
$ds = new DataSet($db, 'test');
$ds->AddChild(new Relation($ds, 'id', 'parent'));
$ds->display = array(new DisplayColumn('Name', 'name'));
$ds->fields = array(
	'Name' => array('name', 'text')
);
$ds->Validation = $v;
$dsChild = new DataSet($db, 'child');
$dsChild->display = array(
	new DisplayColumn('Child', 'example')
);
$dsChild->fields = array(
	'Example' => array('example', 'text')
);
$ds->AddChild(new Relation($dsChild, 'id', 'parent'));

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