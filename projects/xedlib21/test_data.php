<?php

//Configuration

$page_title = 'Storage Tests';
$page_body = '';

//Requirements

require_once('lib/h_utility.php');
HandleErrors();
require_once('lib/h_data.php');
require_once('lib/h_display.php');
require_once('lib/h_template.php');

require_once('lib/a_editor.php');

// Data

$editor = GetVar('editor');

$imgError = ' <img src="'.GetRelativePath(dirname(__FILE__)).'/lib/images/error.png" alt="Error" />';
$v = new Validation('name', '.+', $imgError.' You must specify a name.');

$ret = FormValidate('formtest', $v, isset($ca));

$page_head = '<script type="text/javascript">'."\n".$ret['js'].'</script>';

$db = new Database('test', 'localhost', 'root', 'ransal');

// dsChild

$dsChild = new DataSet($db, 'child');
$dsChild->Description = "Child";
$dsChild->Display = array(
	new DisplayColumn('Child', 'example')
);
$dsChild->Fields = array(
	'Example' => array('example', 'text')
);

// dsBoth

$dsBoth = new DataSet($db, 'test');
$dsBoth->Description = "Both Item";
$dsBoth->AddChild(new Relation($dsBoth, 'id', 'parent'));
$dsBoth->Display = array(
	new DisplayColumn('Name', 'name'),
	new DisplayColumn('Second', 'second')
);
$dsBoth->Fields = array(
	'Name' => array('name', 'text'),
	'Second' => array('second', 'text')
);
$dsBoth->Validation = $v;
$dsBoth->Errors = $ret['errors'];

$dsBoth->AddChild(new Relation($dsChild, 'id', 'parent'));

// dsForeign

$dsForeign = new DataSet($db, 'test');
$dsForeign->Description = "Foreign Item";
$dsForeign->Display = array(
	new DisplayColumn('Name', 'name'),
	new DisplayColumn('Second', 'second')
);
$dsForeign->Fields = array(
	'Name' => array('name', 'text'),
	'Second' => array('second', 'text')
);
$dsForeign->AddChild(new Relation($dsChild, 'id', 'parent'));

// dsSelf

$dsSelf = new DataSet($db, 'test');
$dsSelf->Description = "Self Item";
$dsSelf->Display = array(
	new DisplayColumn('Name', 'name'),
	new DisplayColumn('Second', 'second')
);
$dsSelf->Fields = array(
	'Name' => array('name', 'text'),
	'Second' => array('second', 'text')
);
$dsSelf->AddChild(new Relation($dsSelf, 'id', 'parent'));

//Preparation

$ca = GetVar('ca');
$ci = GetVar('ci');

$t = new Template();

$edBoth = new EditorData('test_both', $dsBoth);
if ($editor == 'test_both') $edBoth->Prepare($ca);

$edForeign = new Editordata('test_foreign', $dsForeign);
if ($editor == 'test_foreign') $edForeign->Prepare($ca);

$edSelf = new Editordata('test_self', $dsSelf);
if ($editor == 'test_self') $edSelf->Prepare($ca);

//Presentation

$tbl = new Table('tblMain',
	array('<h2>Self</h2><i>No Child</i>', '<h2>Foreign</h2><i>No Tree</i>', '<h2>Both</h2><i>Tree and child</i>'),
	array('valign="top"', 'valign="top"', 'valign="top"')
);

$ret = $edSelf->Get($me, $ci);
$row[0] = $ret['table'];
foreach ($ret['forms'] as $frm)
	$row[0] .= GetBox("box_{$frm->name}", $frm->State.' '.$frm->Description,
		$frm->Get('action="'.$me.'" method="post"'),
		'templates/box.html');

$ret = $edForeign->Get($me, $ci);
$row[1] = $ret['table'];
foreach ($ret['forms'] as $frm)
	$row[1] .= GetBox("box_{$frm->name}", $frm->State.' '.$frm->Description,
		$frm->Get('action="'.$me.'" method="post"'),
		'templates/box.html');
		
$ret = $edBoth->Get($me, $ci);
$row[2] = $ret['table'];
foreach ($ret['forms'] as $frm)
	$row[2] .= GetBox("box_{$frm->name}", $frm->State.' '.$frm->Description,
		$frm->Get('action="'.$me.'" method="post"'),
		'templates/box.html');

$tbl->AddRow($row);

$page_body .= $tbl->Get();

$page_body .= "Be careful about moving ROOT items in the foreign table. They
actually can re-align if in the correct order, but you will destroy their tree
if they cannot find their parents by their order (a part of manual sorting that
cannot allow us to automatically sort).";

echo $t->Get('template_test.html');

?>