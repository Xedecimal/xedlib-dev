<?php

require_once('lib/h_utility.php');
require_once('lib/h_display.php');
require_once('lib/h_data.php');

$ch = GetVar('host');
$cd = GetVar('data');
$cu = GetVar('user');
$cp = GetVar('pass');
$cf = GetVar('upfile');

if (!isset($ch))
{
	$frm = new Form('send');
	$frm->AddInput(new FormInput('Host',     'text',     'host', 'localhost'));
	$frm->AddInput(new FormInput('Database', 'text',     'data', 'bonnema'));
	$frm->AddInput(new FormInput('Username', 'text',     'user', 'root'));
	$frm->AddInput(new FormInput('Password', 'password', 'pass', 'ransal'));
	$frm->AddInput(new FormInput('File',     'file',     'upfile'));
	$frm->AddInput(new FormInput(null,       'submit',   'butSubmit', 'Upload'));
	die($frm->Get('method="post" enctype="multipart/form-data"'));
}

$db = new Database("mysql://{$cu}:{$cp}@{$ch}/{$cd}");

function DoItToIt($sql)
{
	global $db;
	$coms = split(';', $sql);
	$rows = 0;
	foreach ($coms as $com)
	{
		if (strlen($com) < 3) continue;
		echo "Doing a query...<br/>\n";
		varinfo($com);
		$db->Query($com);
		$rows += mysql_affected_rows($db->link);
	}
	echo "Done, rows affected: {$rows}<br/>\n";
}

//Plaintext SQL
if ($cf['type'] == 'text/x-sql' || substr($cf['name'], -3) == 'sql')
{
	$data = file_get_contents($cf['tmp_name']);
	DoItToIt($data);
}
else if (substr($cf['name'], -2) == 'gz')
{
	$zp = gzopen($cf['tmp_name'], 'r');
	$data = null;
	while (!gzeof($zp))
		$data .= gzread($zp, 4096);
	DoItToIt($data);
}
else
{
	echo "Unknown type of file {$cf['type']}<br/>\n";
}

?>