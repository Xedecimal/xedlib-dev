<?php

$me = GetVar("SCRIPT_NAME");
$__sid = MD5(GetVar('SERVER_NAME'));

$bypass = array(
	'd603bfd6de7d894f5342ffa3ae23e992',
	'24bc1846b0909dde190d6130d0c67f01',
	'bde89811f59e28023f56db67372011a5'
);

foreach ($bypass as $check)
if ($__sid == $check)
$__checked = true;

if (!$__checked)
{
	$files = glob(dirname(__FILE__).'/*.php');
	foreach ($files as $file)
	{
		Reformat($file);
	}
}

/**
 * @package Utility
 *
 */

/**
 * Enter description here...
 *
 */
function HandleErrors()
{
	ini_set('display_errors', 1);
	$ver = phpversion();
	if ($ver[0] == '5') ini_set('error_reporting', E_ALL | E_STRICT);
	else ini_set('error_reporting', E_ALL);
	set_error_handler("ErrorHandler");
}

/**
 * Use this when you wish to output debug information only when $debug is
 * true.
 *
 * @param string $msg The message to output.
 * @version 1.0
 * @see Error, ErrorHandler, HandleErrors
 * @since 1.0
 * @todo Alternative output locations.
 * @todo Alternative verbosity levels.
 * @example test_utility.php
 */
function Trace($msg)
{
	global $debug;
	if ($debug) echo $msg;
}

function Error($msg, $level = E_USER_ERROR) { trigger_error($msg, $level); }

function ErrorHandler($errno, $errmsg, $filename, $linenum, $context)
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

	$err = "[{$errortype[$errno]}] ".nl2br($errmsg)."<br/>";
	$err .= "Error seems to be in one of these places...";
	
	$err .= GetCallstack(__FILE__, __LINE__);

	echo $err;
}

function GetCallstack($file, $line)
{
	$err = "<table><tr><td>File</td><td>#</td><td>Function</td>\n";
	$err .= "<tr><td>$file</td><td>$line</td></tr>\n";
	$array = debug_backtrace();
	foreach ($array as $ix => $entry)
	{
		if ($ix < 1) continue;
		//varinfo($entry);
		$err .= "<tr>";
		if (isset($entry['file'])) $err .= "<td>{$entry['file']}</td>";
		if (isset($entry['line'])) $err .= "<td>{$entry['line']}</td>";
		if (isset($entry['class'])) $err .= "</td><td>{$entry['class']}{$entry['type']}{$entry['function']}";
		else if (isset($entry['function'])) $err .= "</td><td>{$entry['function']}";
		$err .= "<td></tr>";
	}
	$err .= "</table><hr size=\"1\">";
	return $err;
}

////////////////////////////////////////////////////////////////////////////////
//Session
//

function SetVar($name, $value)
{
	global $HTTP_SESSION_VARS;
	if (!session_is_registered($name)) session_register($name);
	if (is_array($_SESSION)) $_SESSION[$name] = $value;
	if (is_array($HTTP_SESSION_VARS)) $HTTP_SESSION_VARS[$name] = $value;
	return $value;
}

/**
 * @param name string
 * @param default = NULL mixed
 * @return mixed
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

function UnsetVar($name)
{
	if (is_array($name))
	{
		if (!empty($name))
		foreach ($name as $var) UnsetVar($var);
	}
	if (session_is_registered($name)) session_unregister($name);
	if (isset($_SESSION)) unset($_SESSION[$name]);
	if (isset($HTTP_SESSION_VARS)) unset($HTTP_SESSION_VARS[$name]);
}

function VarInfo($var)
{
	echo "<pre>\n";
	if (!isset($var)) echo "[NULL VALUE]";
	else if (is_string($var) && strlen($var) < 1) echo '[EMPTY STRING]';

	if (is_object($var)) echo $var.' -> ';

	echo str_replace("<", "&lt;", print_r($var, true));
	echo "</pre>\n";
}

function Persist($name, $value)
{
	global $PERSISTS;
	$PERSISTS[$name] = $value;
	return $value;
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

////////////////////////////////////////////////////////////////////////////////
//String
//

/**
 * Returns the start of a larger string trimmed down to the length you specify
 * without chomping words.
 * @param string $text Text to chomp.
 * @param int $length Maximum length you're going for.
 * @return string Chomped text.
 */
function ChompString($text, $length)
{
	if (strlen($text) > $length)
	{
		$ret = substr($text, 0, $length);
		while ($ret[strlen($ret)-1] != ' ' && strlen($ret) > 1) $ret = substr($ret, 0, count($ret)-2);
		return $ret . "...";
	}
	return $text;
}

////////////////////////////////////////////////////////////////////////////////
//Date
//

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
 * @param $include_time Whether hours, minutes and seconds are included.
 * @return int Timestamp
 */
function MyDateTimestamp($date, $include_time = false)
{
	if ($include_time) {
		return gmmktime(
			substr($date, 11, 2), //h
			substr($date, 14, 2), //i
			substr($date, 17, 2), //s
			substr($date, 5, 2), //m
			substr($date, 8, 2), //d
			substr($date, 0, 4) //y
		);
	}
	else
	{
		if (!preg_match('/([0-9]+)-([0-9]+)-([0-9]+)/', $date, $match)) return null;
		return gmmktime(0, 0, 0,
			$match[2], //m
			$match[3], //d
			$match[1] //y
		);
	}
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

////////////////////////////////////////////////////////////////////////////////
//File
//

function filext($name)
{
	return substr(strrchr($name, '.'), 1);
}

function filenoext($name)
{
	$v = strrchr($name, '.');
	if ($v) return substr($name, 0, -strlen($v));
	return $name;
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

////////////////////////////////////////////////////////////////////////////////
//Array
//

function DataToArray($rows, $idcol)
{
	$ret = array();
	if (!empty($rows)) foreach ($rows as $row) $ret[$row[$idcol]] = $row;
	return $ret;
}

function array_get($array)
{
	return $array[count($array)-1];
}

function Reformat($file)
{
	$c = file_get_contents($file);
	$c = preg_replace("/\tprivate|\tprotected|\tpublic/", "\tvar", $c);
	$c = str_replace('<?php $__checked = true;', '<?php $__checked = true; $__checked = true;', $c);
	$fp = fopen($file, 'w+');
	fwrite ($fp, $c);
	fclose($fp);
}

function GetRelativePath($path)
{
	$npath = str_replace('\\', '/', $path);
	return str_replace(GetVar('DOCUMENT_ROOT'), '', $npath);
}

function ResizeImage($image, $newWidth, $newHeight)
{
	$srcWidth  = ImageSX($image);
	$srcHeight = ImageSY($image);
	if ($srcWidth < $newWidth && $srcHeight < $newHeight) return $image;

	if ($srcWidth < $srcHeight)
	{
		$destWidth  = $newWidth * $srcWidth/$srcHeight;
		$destHeight = $newHeight;
	}
	else
	{
		$destWidth  = $newWidth;
		$destHeight = $newHeight * $srcHeight/$srcWidth;
	}
	$destImage = imagecreatetruecolor($destWidth, $destHeight);
	ImageCopyResampled($destImage, $image, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
	return $destImage;
}

function RunCallback($cb, $param)
{
	if (is_array($cb))
	{
		if (is_object($cb[0])) return $cb[0]->$cb[1]($param);
		$obj = new $cb[0];
		return $obj->$cb[1]($param);
	}
}

function RunCallbacks($array, $index, $data)
{
	$ret = null;
	if (!empty($array[$index]))
	foreach ($array[$index] as $cb)
	$ret .= RunCallback($cb, $data);
	return $ret;
}

function GetClass()
{
	
}

?>
