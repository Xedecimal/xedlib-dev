<?php

//Configuration

$page_title = 'Storage Tests';
$page_body = '';

//Requirements

require_once('h_main.php');
require_once('lib/classes/module.php');
require_once('lib/classes/file.php');
require_once('lib/classes/data/database.php');
require_once('lib/classes/data/data_set.php');
require_once('lib/modules/editor_data/editor_data.php');
require_once('lib/classes/present/form_input.php');
require_once('lib/classes/present/template.php');
require_once('lib/classes/present/table.php');
Server::HandleErrors();

Module::Initialize(dirname(__FILE__), true);

// Data

$editor = Server::GetVar('editor');

$imgError = ' <img src="'.Server::GetRelativePath(dirname(__FILE__))
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
$dsBoth->AddJoin(new Join($dsBoth, 'id=parent'));
$dsBoth->DisplayColumns = array(
	'name' => new DisplayColumn('Name'),
	'second' => new DisplayColumn('Second')
);
$dsBoth->FieldInputs = array(
	'name' => new FormInput('Name', 'text'),
	'second' => new FormInput('Second', 'text')
);

$dsBoth->AddJoin(new Join($dsChild, 'id=parent'));

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
$dsForeign->AddJoin(new Join($dsChild, 'id=parent'));

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
$dsSelf->AddJoin(new Join($dsSelf, 'id=parent'));

//Preparation

$ca = Server::GetVar('ca');
$ci = Server::GetVar('ci');

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

$row[0] = $edSelf->Get();
$row[1] = $edForeign->Get();
$row[2] = $edBoth->Get();
$tbl->AddRow($row);

$page_body .= $tbl->Get();

$page_body .= "Be careful about moving ROOT items in the foreign table. They
actually can re-align if in the correct order, but you will destroy their tree
if they cannot find their parents by their order (a part of manual sorting that
cannot allow us to automatically sort).";

die($page_body);

?>