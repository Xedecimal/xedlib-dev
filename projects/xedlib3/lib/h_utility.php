<?php

$me = GetVar("SCRIPT_NAME");
$__sid = MD5(GetVar('SERVER_NAME'));

global $__checked;

if (!isset($__checked) && substr(phpversion(), 0, 1) != '5')
{
	echo "Library has not been synched, doing so now...<br/>\n";
	$files = glob(dirname(__FILE__).'/*.php');
	foreach ($files as $file)
	{
		echo "Reformatting: {$file}<br/>\n";
		chmod($file, 0666);
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

function ErrorHandler($errno, $errmsg, $filename, $linenum)
{
	if (error_reporting() == 0) return;
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
	if ($ver[0] > 4 && $ver[2] > 1) $errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error';

	//$user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);

	$err = "[{$errortype[$errno]}] ".nl2br($errmsg)."<br/>";
	$err .= "Error seems to be in one of these places...\n";

	$err .= GetCallstack($filename, $linenum);

	echo $err;
}

function GetCallstack($file, $line)
{
	$err = "<table><tr><td>File</td><td>#</td><td>Function</td>\n";
	$err .= "<tr>\n\t<td>$file</td>\n\t<td>$line</td>\n";
	$array = debug_backtrace();
	$err .= "\t<td>{$array[1]['function']}</td>\n</tr>";
	foreach ($array as $ix => $entry)
	{
		if ($ix < 1) continue;
		//varinfo($entry);
		$err .= "<tr>\n";
		if (isset($entry['file']))
		{ $err .= "\t<td>{$entry['file']}</td>\n"; }
		if (isset($entry['line']))
		{ $err .= "\t<td>{$entry['line']}</td>\n"; }
		if (isset($entry['class']))
		{ $err .= "\t<td>{$entry['class']}{$entry['type']}{$entry['function']}</td>\n"; }
		else if (isset($entry['function']))
		{ $err .= "\t<td>{$entry['function']}</td>\n"; }
		$err .= "</tr>";
	}
	$err .= "</table>\n<hr size=\"1\">\n";
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
	global $HTTP_POST_FILES, $HTTP_POST_VARS, $HTTP_GET_VARS, $HTTP_SERVER_VARS,
	$HTTP_SESSION_VARS, $HTTP_COOKIE_VARS;

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

function GetPost($name, $default = null)
{
	global $HTTP_POST_VARS;

	if (!empty($_POST[$name]))    { Trace("GetVar(): $name (Post)    -> {$_POST[$name]}<br/>\n"); return $_POST[$name]; }

	if (isset($HTTP_POST_VARS[$name]) && strlen($HTTP_POST_VARS[$name]) > 0)
		return $HTTP_POST_VARS[$name];

	return $default;
}

function UnsetVar($name)
{
	global $HTTP_SESSION_VARS;

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
	if (!isset($var)) { echo "[NULL VALUE]"; }
	else if (is_string($var) && strlen($var) < 1)
	{ echo '[EMPTY STRING]'; }

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
function URL($url, $uri = null)
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
	if (is_array($getvars)) $redir = URL($url, $getvars);
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
		return mktime(
			substr($date, 11, 2), //hh
			substr($date, 14, 2), //mm
			substr($date, 17, 2), //ss
			substr($date, 5, 2), //m
			substr($date, 8, 2), //d
			substr($date, 0, 4) //y
		);
	}
	else
	{
		$match = null;
		if (!preg_match('/([0-9]+)-([0-9]+)-([0-9]+)/', $date, $match)) return null;
		return mktime(0, 0, 0,
			$match[2], //m
			$match[3], //d
			$match[1] //y
		);
	}
}

function GetDateOffset($ts)
{
	$ss = time()-$ts;
	$mm = $ss / 60;
	$hh = $mm / 60;

	$d = $hh / 24;
	$w = $d / 7;
	$m = $d / 31;
	$y = $d / 365;

	$ret = null;
	if ($y >= 1) $ret = number_format($y, 1).' year'.($y > 1 ? 's' : null);
	else if ($m >= 1) $ret = number_format($m, 1).' month'.($m > 1 ? 's' : null);
	else if ($w >= 1) $ret = number_format($w, 1).' week'.($w > 1 ? 's' : null);
	else if ($d >= 1) $ret = number_format($d, 1).' day'.($d > 1 ? 's' : null);
	else if ($hh >= 1) $ret = number_format($hh, 1).' hour'.($hh > 1 ? 's' : null);
	else if ($mm >= 1) $ret = number_format($mm, 1).' minute'.($mm > 1 ? 's' : null);
	else $ret = number_format($ss, 1).' second'.($ss > 1 ? 's' : null);
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
		$target = "{$dir}/{$obj}";
		if (is_dir($target)) DelTree($target);
		else unlink($target);
	}
	closedir($dh);
	@rmdir($dir);
}

////////////////////////////////////////////////////////////////////////////////
//Array
//

/**
 * Returns a new array with the idcol into the keys of each item's idcol
 * set instead of numeric offset.
 *
 * @param array $rows
 * @param string $idcol
 * @return array
 */
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
	$c = preg_replace("/\tstatic/", "\t", $c);
	$c = "<?php \$__checked = true; ?>\n".$c;
	if (!$fp = fopen($file, 'w+'))
	{
		echo "Couldn't open file for writing, attempting to set
			permissions...<br/>\n";
		if (!chmod($file, 0666))
			echo "Couldn't set the permissions, giving up.<br/>\n";
		else
			$fp = fopen($file, 'w+');
	}
	fwrite ($fp, $c);
	fclose($fp);
}

/**
 * Gets the webserver path for a given local filesystem directory.
 *
 * @param string $path
 * @return string Translated path.
 */
function GetRelativePath($path)
{
	//Probably Apache situated
	$dr = GetVar('DOCUMENT_ROOT');

	if (empty($dr)) //Probably IIS situated
	{
		//Get the document root from the translated path.
		$pt = str_replace('\\\\', '/', GetVar('PATH_TRANSLATED'));
		$dr = substr($pt, 0, -strlen(GetVar('SCRIPT_NAME')));
	}

	$dr = str_replace('\\\\', '/', $dr);

	return substr(str_replace('\\', '/', str_replace('\\\\', '/', $path)), strlen($dr));
}

function GetButDel($url = null)
{
	$ret = '<img src="'.GetRelativePath(dirname(__FILE__)).'/images/delete.png"
		alt="delete" title="Delete Item" />';
	if (strlen($url) > 0) $ret = '<a href="'.$url.'">'.$ret.'</a>';
	return $ret;
}

/**
 * Resizes an image bicubicly and constrains proportions.
 *
 * @param resource $image Use imagecreate*
 * @param int $newWidth
 * @param int $newHeight
 * @return resource Resized/sampled image.
 */
function ResizeImage($image, $newWidth, $newHeight)
{
	$srcWidth  = imagesx($image);
	$srcHeight = imagesy($image);
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

function RunCallbacks()
{
	$args = func_get_args();
	$target = array_shift($args);
	$ret = null;
	if (!empty($target))
	foreach ($target as $cb)
	{
		$item = call_user_func_array($cb, $args);
		if (is_array($item))
		{
			if (!isset($ret)) $ret = array();
			$ret = array_merge($ret, $item);
		}
		if (is_string($item))
			$ret .= $item;
	}
	return $ret;
}

/**
 * Returns a cleaned up string to work in an html id attribute without w3c
 * errors.
 *
 * @param string $id
 * @return string
 */
function CleanID($id)
{
	return str_replace('[', '_', str_replace(']', '', $id));
}

function Plural($str)
{
	if (strlen($str) < 1) return null;
	if (substr($str, -1) == 'y') return substr($str, 0, -1).'ies';
	if (substr($str, -1) != 's') return "{$str}s";
	return $str;
}

function SecurePath($path)
{
	$ret = preg_replace('#^\.#', '', $path);
	$ret = preg_replace('#^/#', '', $ret);
	return preg_replace('#\.\./#', '', $ret);
}

?>