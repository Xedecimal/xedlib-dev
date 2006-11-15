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

$imgError = ' <img src="'.GetRelativePath(dirname(__FILE__)).'/lib/images/error.png" alt="Error" />';
$v = new Validation('name', '.+', $imgError.' You must specify a name.');

$ret = FormValidate('formtest', $v, isset($ca));

$page_head .= '<script type="text/javascript">'."\n".$ret['js'].'</script>';

$db = new Database('test', 'localhost', 'root', 'ransal');
$ds = new DataSet($db, 'test');
$ds->AddChild(new Relation($ds, 'id', 'parent'));
$ds->Display = array(
	new DisplayColumn('Name', 'name'),
	new DisplayColumn('Second', 'second')
);
$ds->Fields = array(
	'Name' => array('name', 'text'),
	'Second' => array('second', 'text')
);
$ds->Validation = $v;
$ds->Errors = $ret['errors'];
$dsChild = new DataSet($db, 'child');
$dsChild->Display = array(
	new DisplayColumn('Child', 'example')
);
$dsChild->Fields = array(
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

$ed = $edTest->Get($me, $ci);

$page_body = $ed['table'];
foreach ($ed['forms'] as $frm)
	$page_body .= GetBox("box_{$frm->name}", $frm->name,
		$frm->Get('action="'.$me.'" method="post"'),
		'templates/box.html');

echo $t->Get('template_test.html');

?>