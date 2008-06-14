<?php

require_once('h_main.php');

global $me;

$items = $dsUser->GetCustom("DESCRIBE `{$dsUser->table}`");

if (GetVar('ca') == 'unlock')
{
    $res = $dsUser->Add(array(
        GetVar('usercol') => GetVar('user'),
        GetVar('passcol') => GetVar('pass')
    ));
    echo "Unlocked? {$res}";
}

echo <<<EOF
<form method="post" action="{$me}">
<input type="hidden" name="ca" value="unlock" />
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

?>