<?php

$me = GetVar("SCRIPT_NAME");
$xlpath = GetXedlibPath();

function HandleErrors()
{
	ini_set('display_errors', 1);
	$ver = phpversion();
	if ($ver[0] == '5') ini_set('error_reporting', E_ALL | E_STRICT);
	else ini_set('error_reporting', E_ALL);
	set_error_handler("ErrorHandler");
}

//TODO: TraceConfig() and output locations.
function Trace($msg)
{
	global $debug;
	if ($debug) echo $msg;
}

function Error($msg, $level = E_USER_ERROR) { trigger_error($msg, $level); }

//function ErrorHandler($errno, $errmsg, $filename, $linenum, $vars)
function ErrorHandler($errno, $errmsg, $filename, $linenum)
{
	$errortype = array (
		E_ERROR           => "Error",
		E_WARNING         => "Warning",
		E_PARSE           => "Parsing Error",
		E_NOTICE          => "Notice",
		E_CORE_ERROR      => "Core Error",
		E_CORE_WARNING    => "Core Warning",
		E_COMPILE_ERROR   => "Compile Error",
		E_COMPILE_WARNING => "Compile Warning",
		E_USER_ERROR      => "User Error",
		E_USER_WARNING    => "User Warning",
		E_USER_NOTICE     => "User Notice",
	);
	$ver = phpversion();
	if ($ver[0] > 4)  $errortype[E_STRICT] = 'Strict Error';

	$user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);

	$err = "[<b>{$errortype[$errno]}</b>] ";
	$err .= "<b>$errmsg</b> in ";
	$err .= "<b>$filename</b>:<b>$linenum</b>\n";
	$err .= "Call Stack...";

	$err .= "<table><tr><td>File:Line</td><td>Function</td>\n";
	$array = debug_backtrace();
	foreach ($array as $entry)
	{
		$err .= "<tr><td>";
		if (isset($entry['file'])) $err .= "<b>{$entry['file']}</b>";
		if (isset($entry['line'])) $err .= ":<b>{$entry['line']}</b>";
		if (isset($entry['class'])) $err .= "</td><td>{$entry['class']}{$entry['type']}{$entry['function']}";
		else if (isset($entry['function'])) $err .= "</td><td><b>{$entry['function']}</b>";
		$err .= "<td></tr>";
	}
	$err .= "</table><hr size=\"1\">";

	echo $err;
}

function SetVar($name, $value)
{
	global $HTTP_SESSION_VARS;
	if (!session_is_registered($name)) session_register($name);
	if (is_array($_SESSION)) $_SESSION[$name] = $value;
	if (is_array($HTTP_SESSION_VARS)) $HTTP_SESSION_VARS[$name] = $value;
}

/**
 * @return mixed
 * @param name string
 * @param default = NULL mixed
 */
function GetVar($name, $default = null)
{
	global $HTTP_POST_VARS, $HTTP_GET_VARS, $HTTP_SERVER_VARS, $HTTP_SESSION_VARS, $HTTP_COOKIE_VARS;

	if (!empty($_FILES[$name]))   { Trace("GetVar(): $name (File)    -> {$_FILES[$name]}<br/>\n"); return $_FILES[$name]; }
	if (!empty($_POST[$name]))    { Trace("GetVar(): $name (Post)    -> {$_POST[$name]}<br/>\n"); return $_POST[$name]; }
	if (!empty($_GET[$name]))     { Trace("GetVar(): $name (Get)     -> {$_GET[$name]}<br/>\n"); return $_GET[$name]; }
	if (!empty($_SESSION[$name])) { Trace("GetVar(): $name (Session) -> {$_SESSION[$name]}<br/>\n"); return $_SESSION[$name]; }
	if (!empty($_COOKIE[$name]))  { Trace("GetVar(): $name (Cookie)  -> {$_COOKIE[$name]}<br/>\n"); return $_COOKIE[$name]; }
	if (!empty($_SERVER[$name]))  { Trace("GetVar(): $name (Server)  -> {$_SERVER[$name]}<br/>\n"); return $_SERVER[$name]; }

	if (isset($HTTP_POST_FILES[$name]) && strlen($HTTP_POST_FILES[$name]) > 0)
		return $HTTP_POST_FILES[$name];
	if (isset($HTTP_POST_VARS[$name]) && strlen($HTTP_POST_VARS[$name]) > 0)
		return $HTTP_POST_VARS[$name];
	if (isset($HTTP_GET_VARS[$name]) && strlen($HTTP_GET_VARS[$name]) > 0)
		return $HTTP_GET_VARS[$name];
	if (isset($HTTP_SESSION_VARS[$name]) && strlen($HTTP_SESSION_VARS[$name]) > 0)
		return $HTTP_SESSION_VARS[$name];
	if (isset($HTTP_COOKIE_VARS[$name]) && strlen($HTTP_COOKIE_VARS[$name]) > 0)
		return $HTTP_COOKIE_VARS[$name];
	if (isset($HTTP_SERVER_VARS[$name]) && strlen($HTTP_SERVER_VARS[$name]) > 0)
		return $HTTP_SERVER_VARS[$name];

	return $default;
}

function Persist($name, $value)
{
	global $PERSISTS;
	$PERSISTS[$name] = $value;
	return $value;
}

function UnsetVar($name)
{
	if (session_is_registered($name)) session_unregister($name);
	if (isset($_SESSION)) unset($_SESSION[$name]);
	if (isset($HTTP_SESSION_VARS)) unset($HTTP_SESSION_VARS[$name]);
}

function VarInfo($var)
{
	echo "<pre>\n";
	if (!isset($var)) echo "NULL";
	echo str_replace("<", "&lt;", print_r($var, true));
	echo "</pre>\n";
}

//Managing session values.

function FormRequire($name, $arr, $check)
{
	$ret = array();
	$checks = null;
	foreach ($arr as $key => $val)
	{
		$rec = RecurseReq($key, $val, $checks);
		//$ret['errors'] = array_merge($ret['errors'], $rec['errors']);
		if ($check && strlen(GetVar($key)) < 1) $ret['errors'][$key] = $val;
		//$checks .= "\tchk_{$key} = document.getElementById('{$key}')\n";
		//$checks .= "\tif (chk_{$key}.value.length < 1) { alert('{$val}'); chk_{$key}.focus(); return false; }\n";
	}
	$ret['js'] = "function {$name}_check()\n\t{\n\t{$checks}\n\treturn true;\n}\n";
	return $ret;
}

function RecurseReq($key, $val, &$checks)
{
	if (is_array($val))
	{
		foreach ($val as $newkey => $newval)
		{
			$checks .= "\tchk_{$key} = document.getElementById('{$key}')\n";
			$checks .= "\tif (chk_{$key}.value == '{$newkey}')\n\t{\n";
			RecurseReq($newkey, $newval, $checks);
			$checks .= "\t}\n";
		}
	}
	else
	{
		$checks .= "\tchk_{$key} = document.getElementById('{$key}')\n";
		$checks .= "\tif (chk_{$key}.value.length < 1) { alert('{$val}'); chk_{$key}.focus(); return false; }\n";
	}
}

/**
 * Returns a clean URI.
 *
 * @param $url string URL to clean.
 * @param $uri array URI appended on URL and cleaned.
 * @return string Cleaned URI+URL
 */
function MakeURI($url, $uri = null)
{
	$ret = str_replace(' ', '%20', $url);

	if (is_array($uri))
	{
		$start = (strpos($ret, "?") > 0);
		foreach ($uri as $key => $val)
		{
			if (isset($val))
			{
				$ret .= ($start ? '&amp;' : '?')."{$key}={$val}";
				$start = true;
			}
		}
	}

	return $ret;
}

/**
 * Redirect the browser with a cleanly built URI.
 *
 * @param $url string Relative path to script
 * @param $getvars array Array of get variables.
 */
function Redirect($url, $getvars = NULL)
{
	session_write_close();
	$redir = GetVar("cr", $url);
	if (is_array($getvars)) $redir = MakeURI($url, $getvars);
	header("Location: $redir");
	die();
}

function GetMonthSelect($name, $default, $attribs = null)
{
	$ret = "<select name=\"$name\"";
	if ($attribs != null) $ret .= " $attribs";
	$ret .= ">";
	for ($ix = 1; $ix < 13; $ix++)
	{
		$ts = gmmktime(0, 0, 0, $ix);
		if ($ix == $default) $sel = " selected=\"selected\"";
		else $sel = "";
		$ret .= "<option value=\"$ix\"$sel> " . gmdate("F", $ts) . "</option>\n";
	}
	$ret .= "</select>\n";
	return $ret;
}

function GetYearSelect($name, $year)
{
	$ret = "<select name=\"$name\">";
	$ret .= "<option value=\"" . ($year-11) . "\"> &lt;&lt; </option>\n";
	for ($ix = $year-10; $ix < $year+10; $ix++)
	{
		if ($ix == $year) $sel = " selected=\"selected\"";
		else $sel = "";
		$ret .= "<option value=\"$ix\"$sel>$ix</option>\n";
	}
	$ret .= "<option value=\"" . ($year+11) . "\"> &gt;&gt; </option>\n";
	$ret .= "</select>\n";
	return $ret;
}

function GetStateSelect($name, $state)
{
$options = array(
	new SelOption(0, 'Alabama'),
	new SelOption(1, 'Alaska'),
	new SelOption(2, 'Arizona'),
	new SelOption(3, 'Arkansas'),
	new SelOption(4, 'California'),
	new SelOption(5, 'Colorado'),
	new SelOption(6, 'Connecticut'),
	new SelOption(7, 'Delaware'),
	new SelOption(8, 'Florida'),
	new SelOption(9, 'Georgia'),
	new SelOption(10, 'Hawaii'),
	new SelOption(11, 'Idaho'),
	new SelOption(12, 'Illinois'),
	new SelOption(13, 'Indiana'),
	new SelOption(14, 'Iowa'),
	new SelOption(15, 'Kansas'),
	new SelOption(16, 'Kentucky'),
	new SelOption(17, 'Louisiana'),
	new SelOption(18, 'Maine'),
	new SelOption(19, 'Maryland'),
	new SelOption(20, 'Massachusetts'),
	new SelOption(21, 'Michigan'),
	new SelOption(22, 'Minnesota'),
	new SelOption(23, 'Mississippi'),
	new SelOption(24, 'Missouri'),
	new SelOption(25, 'Montana'),
	new SelOption(26, 'Nebraska'),
	new SelOption(27, 'Nevada'),
	new SelOption(28, 'New Hampshire'),
	new SelOption(29, 'New Jersey'),
	new SelOption(30, 'New Mexico'),
	new SelOption(31, 'New York'),
	new SelOption(32, 'North Carolina'),
	new SelOption(33, 'North Dakota'),
	new SelOption(34, 'Ohio'),
	new SelOption(35, 'Oklahoma'),
	new SelOption(36, 'Oregon'),
	new SelOption(37, 'Pennsylvania'),
	new SelOption(38, 'Rhode Island'),
	new SelOption(39, 'South Carolina'),
	new SelOption(40, 'South Dakota'),
	new SelOption(41, 'Tennessee'),
	new SelOption(42, 'Texas'),
	new SelOption(43, 'Utah'),
	new SelOption(44, 'Vermont'),
	new SelOption(45, 'Virginia'),
	new SelOption(46, 'Washington'),
	new SelOption(47, 'West Virginia'),
	new SelOption(48, 'Wisconsin'),
	new SelOption(49, 'Wyoming')
);

return MakeSelect($name, $options, null, $state);
}

function ChompText($text, $length)
{
	if (strlen($text) > $length)
	{
		$ret = substr($text, 0, $length);
		while ($ret[strlen($ret)-1] != ' ' && strlen($ret) > 1) $ret = substr($ret, 0, count($ret)-2);
		return $ret . "...";
	}
	return $text;
}

/**
 * Careful with this sucker.
 */

function DelTree($dir)
{
	if (!file_exists($dir)) return;
	$dh = @opendir($dir);
	if (!$dh) return;
	while (($obj = readdir($dh)))
	{
		if ($obj == '.' || $obj == '..') continue;
		if (!@unlink("{$dir}/{$obj}")) DelTree($dir.'/'.$obj);
	}
	closedir($dh);
	@rmdir($dir);
}

//Date Functions

function TimestampToMySql($ts)
{
	return gmdate("y-m-d h:i:s", $ts);
}

function TimestampToMsSql($ts)
{
	return gmdate("m/d/y h:i:s A", $ts);
}

/**
 * Converts a mysql date to a timestamp.
 *
 * @param $date string MySql Date/DateTime
 * @return int Timestamp
 */
function MyDateTimestamp($date)
{
	return gmmktime(
		substr($date, 11, 2), //h
		substr($date, 14, 2), //i
		substr($date, 17, 2), //s
		substr($date, 5, 2), //m
		substr($date, 8, 2), //d
		substr($date, 0, 4) //y
	);
}

function MyDateStamp($date)
{
	preg_match('/([0-9]+)-([0-9]+)-([0-9]+)/', $date, $match);
	return gmmktime(0, 0, 0,
		$match[2], //m
		$match[3], //d
		$match[1] //y
	);
}

function GetDateOffset($ts)
{
	$ret = '';
	$Y = gmdate('y') - gmdate('y', $ts);
	$M = gmdate('m') - gmdate('m', $ts);
	$D = gmdate('d') - gmdate('d', $ts);
	$HH = gmdate('H') - gmdate('H', $ts);
	$MM = gmdate('i') - gmdate('i', $ts);
	$SS = gmdate('s') - gmdate('s', $ts);

	if ($Y > 0) $ret = "$Y year".($Y != 1 ? 's' : null);
	else if ($M > 0) $ret = "$M month".($M != 1 ? 's' : null);
	else if ($D > 0) $ret = "$D day".($D != 1 ? 's' : null);
	else if ($HH > 0) $ret = "$HH hour".($HH != 1 ? 's' : null);
	else if ($MM > 0) $ret = "$MM minute".($MM != 1 ? 's' : null);
	else $ret = "$SS second".($SS != 1 ? 's' : null);
	return $ret.' ago';
}

function GetMask($array)
{
	$ret = 0;
	foreach ($array as $ix) $ret |= $ix;
	return $ret;
}

function filext($name) { return substr(strrchr($name, '.'), 1); }
function filenoext($name)
{
	$v = strrchr($name, '.');
	if ($v) return substr($name, 0, -strlen($v));
	return $name;
}

function GetXedlibPath()
{
	return 'xedlib';
}

function DataToArray($rows, $idcol)
{
	$ret = array();
	foreach ($rows as $row) $ret[$row[$idcol]] = $row;
	return $ret;
}

?>
