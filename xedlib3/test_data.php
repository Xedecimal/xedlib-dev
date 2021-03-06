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

$imgError = ' <img src="'.GetRelativePath(dirname(__FILE__))
	.'/lib/images/error.png" alt="Error" />';

$db = new Database();
$db->Open('mysql://root:ransal@localhost/test');

// dsChild

$dsChild = new DataSet($db, 'child');
$dsChild->Description = "Child";
$dsChild->DisplayColumns = array(
	'example' => new DisplayColumn('Child')
);
$dsChild->FieldInputs = array(
	'example' => new FormInput('Example')
);

// dsBoth

$dsBoth = new DataSet($db, 'test');
$dsBoth->Description = "Both Item";
$dsBoth->AddChild(new Relation($dsBoth, 'id', 'parent'));
$dsBoth->DisplayColumns = array(
	'name' => new DisplayColumn('Name'),
	'second' => new DisplayColumn('Second')
);
$dsBoth->FieldInputs = array(
	'name' => new FormInput('Name', 'text'),
	'second' => new FormInput('Second', 'text')
);

$dsBoth->AddChild(new Relation($dsChild, 'id', 'parent'));

// dsForeign

$dsForeign = new DataSet($db, 'test');
$dsForeign->Description = 'Foreign Item';
$dsForeign->DisplayColumns = array(
	'name' => new DisplayColumn('Name'),
	'second' => new DisplayColumn('Second')
);
$dsForeign->FieldInputs = array(
	'name' => new FormInput('Name'),
	'second' => new FormInput('Second')
);
$dsForeign->AddChild(new Relation($dsChild, 'id', 'parent'));

// dsSelf

$dsSelf = new DataSet($db, 'test');
$dsSelf->Description = 'Self Item';
$dsSelf->DisplayColumns = array(
	'name' => new DisplayColumn('Name'),
	'second' => new DisplayColumn('Second')
);
$dsSelf->FieldInputs = array(
	'name' => new FormInput('Name'),
	'second' => new FormInput('Second')
);
$dsSelf->AddChild(new Relation($dsSelf, 'id', 'parent'));

//Preparation

$ca = GetVar('ca');
$ci = GetVar('ci');

$data = null;
$t = new Template($data);

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

$ret = $edSelf->GetUI();
$row[0] = $ret['table'];
foreach ($ret['forms'] as $frm)
	$row[0] .= GetBox("box_{$frm->name}", $frm->State.' '.$frm->Description,
		$frm->Get('action="'.$me.'" method="post"'),
		'templates/box.html');

$ret = $edForeign->GetUI();
$row[1] = $ret['table'];
foreach ($ret['forms'] as $frm)
	$row[1] .= GetBox("box_{$frm->name}", $frm->State.' '.$frm->Description,
		$frm->Get('action="'.$me.'" method="post"'),
		'templates/box.html');

$ret = $edBoth->GetUI();
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

echo $t->ParseFile('t.xml');

?>