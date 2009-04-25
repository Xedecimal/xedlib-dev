<?php

require_once('h_main.php');

$ca = GetVar('ca');
$cf = GetVar('upfile');

if (!isset($ca))
{
	$frm = new Form('send');
	$frm->AddHidden('ca', 'upload');
	$frm->AddInput(new FormInput('File',     'file',     'upfile'));
	$frm->AddInput(new FormInput(null,       'submit',   'butSubmit', 'Upload'));
	$out = '<table><tr><td>Upload Backup';
	$out .= $frm->Get('method="post" enctype="multipart/form-data"');
	$out .= <<<EOF
	</td><td valign="top"><a href="{$me}?ca=download">Download Backup</a></td>
</tr>
EOF;
	die($out);
}

//Download backup.
if ($ca == 'download')
{
	$tname = addslashes(tempnam(null, 'xldatadump'));

	$tables = $db->Query('SHOW TABLES');
	while ($table = mysql_fetch_array($tables))
	{
		echo "Doing one.";
		$db->Query("SELECT * INTO OUTFILE '{$tname}' FROM {$table[0]}");
		echo file_get_contents($tname);
	}
}

function DoItToIt($sql)
{
	global $db;
	$coms = split(';', $sql);
	$rows = 0;
	foreach ($coms as $com)
	{
		if (strlen($com) < 3) continue;
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