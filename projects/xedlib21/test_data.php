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

global $me;

// Data

$editor = GetVar('editor');

$imgError = ' <img src="'.GetRelativePath(dirname(__FILE__)).'/lib/images/error.png" alt="Error" />';

$db = new Database();
$db->Open('mysql://root:ransal@localhost/test');

// dsChild

$dsChild = new DataSet($db, 'child');
$dsChild->Description = "Child";
$dsChild->DisplayColumns = array(
	'example' => new DisplayColumn('Child')
);
$dsChild->FieldInputs = array(
	'example' => new FormInput('Example', 'text')
);

// dsBoth

$dsBoth = new DataSet($db, 'test');
$dsBoth->Description = "Both Item";
$dsBoth->AddChild(new Relation($dsBoth, 'id', 'parent'));
$dsBoth->DisplayColumns = array(
	'name' => new DisplayColumn('Name'),
	'second' => new DisplayColumn('Second Column')
);
$dsBoth->FieldInputs = array(
	'name' => new FormInput('Name', 'text'),
	'second' => new FormInput('Second Column', 'text')
);

$dsBoth->AddChild(new Relation($dsChild, 'id', 'parent'));

// dsForeign

$dsForeign = new DataSet($db, 'test');
$dsForeign->Description = "Foreign Item";
$dsForeign->DisplayColumns = array(
	'name' => new DisplayColumn('Name'),
	'second' => new DisplayColumn('Second Column')
);
$dsForeign->FieldInputs = array(
	'name' => new FormInput('Name', 'text'),
	'second' => new FormInput('Second Column', 'text')
);
$dsForeign->AddChild(new Relation($dsChild, 'id', 'parent'));

// dsSelf

$dsSelf = new DataSet($db, 'test');
$dsSelf->Description = "Self Item";
$dsSelf->DisplayColumns = array(
	'name' => new DisplayColumn('Name'),
	'second' => new DisplayColumn('Second')
);
$dsSelf->FieldInputs = array(
	'name' => new FormInput('Name', 'text'),
	'second' => new FormInput('Second', 'text')
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
	array('<h2>Self</h2><i>No Relation</i>', '<h2>Foreign</h2><i>No Tree</i>', '<h2>Both</h2><i>Tree and Relation</i>'),
	array('valign="top"', 'valign="top"', 'valign="top"')
);

$row[0] = $edSelf->GetUI($me, $ci);
$row[1] = $edForeign->GetUI($me, $ci);
$row[2] = $edBoth->GetUI($me, $ci);

$tbl->AddRow($row);

$page_body .= $tbl->Get();

$page_body .= "Be careful about moving ROOT items in the foreign table. They
actually can re-align if in the correct order, but you will destroy their tree
if they cannot find their parents by their order (a part of manual sorting that
cannot allow us to automatically sort).";

echo $t->Get('template_test.xml');

?>