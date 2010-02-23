<?php

/**
 * @package Utility
 *
 */

/**
 * Whether the files should be checked for reformatting.
 * @var bool
 */
global $__checked;

if (!isset($__checked) && substr(phpversion(), 0, 1) != '5')
{
	echo "Library has not been synched, doing so now...<br/>\n";
	$files = glob(dirname(__FILE__).'/*.php');
	$files = array_merge($files, glob(dirname(__FILE__).'/3rd/*.php'));
	foreach ($files as $file)
	{
		echo "Reformatting: {$file}<br/>\n";
		chmod($file, 0666);
		Reformat($file);
	}
}

function null() {}

/**
 * Handles errors and tries to open them up more to strictly find problems.
 *
 * @param string $file Filename to output to silently.
 */
function HandleErrors($file = null)
{
	if (!empty($file)) $GLOBALS['__err_file'] = $file;
	else ini_set('display_errors', 1);
	$ver = phpversion();
	if ($ver[0] == '5') ini_set('error_reporting', E_ALL | E_STRICT);
	else ini_set('error_reporting', E_ALL);
	ini_set('error_log', 'errors_php.txt');
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
	if (!empty($GLOBALS['debug'])) var_dump($msg);
	if (!empty($GLOBALS['__debfile'])) file_put_contents('trace.txt', $msg."\r\n", FILE_APPEND);
}

/**
 * Triggers an error.
 *
 * @param string $msg Message to the user.
 * @param int $level How critical this error is.
 */
function Error($msg, $level = E_USER_ERROR) { trigger_error($msg, $level); }

/**
 * Callback for HandleErrors, used internally.
 *
 * @param int $errno Error number.
 * @param string $errmsg Error message.
 * @param string $filename Source filename of the problem.
 * @param int $linenum Source line of the problem.
 */
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
	if ($ver[0] > 4) $errortype[E_STRICT] = 'Strict Error';
	if ($ver[0] > 4 && $ver[2] > 1)
		$errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error';

	$err = "[{$errortype[$errno]}] ".nl2br($errmsg)."<br/>";
	$err .= "Error seems to be in one of these places...\n";

	if (isset($GLOBALS['_trace']))
		$err .= '<p>Template Trace</p><p>'.$GLOBALS['_trace'].'</p>';

	$err .= GetCallstack($filename, $linenum);

	if (isset($GLOBALS['__err_callback']))
		call_user_func($GLOBALS['__err_callback'], $err);

	if (!empty($GLOBALS['__err_file']))
	{
		$fp = fopen($GLOBALS['__err_file'], 'a+');
		fwrite($fp, $err);
		fclose($fp);
	}
	else echo $err;
}

/**
 * Returns a human readable callstack in html format.
 *
 * @param string $file Source of caller.
 * @param int $line Line of caller.
 * @return string Rendered callstack.
 */
function GetCallstack($file = __FILE__, $line = __LINE__)
{
	$err = "<table><tr><td>File</td><td>#</td><td>Function</td>\n";
	$err .= "<tr>\n\t<td>$file</td>\n\t<td>$line</td>\n";
	$array = debug_backtrace();
	$err .= "\t<td>{$array[1]['function']}</td>\n</tr>";
	foreach ($array as $ix => $entry)
	{
		if ($ix < 1) continue;
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

/**
 * Attempts to set a variable using sessions.
 *
 * @param string $name Name of the value to set.
 * @param string $value Value to set.
 * @return mixed Passed $value
 */
function SetVar($name, $value)
{
	global $HTTP_SESSION_VARS;
	if (!session_is_registered($name)) session_register($name);
	if (is_array($_SESSION)) $_SESSION[$name] = $value;
	if (is_array($HTTP_SESSION_VARS)) $HTTP_SESSION_VARS[$name] = $value;
	return $value;
}

/**
 * Returns a value from files, post, get, session, cookie and finally
 * server in that order.
 *
 * @param string $name Name of the value to get.
 * @param mixed $default Default value to return if not available.
 * @return mixed
 */
function GetVar($name, $default = null)
{
	if (strlen($name) < 1) return $default;

	global $HTTP_POST_FILES, $HTTP_POST_VARS, $HTTP_GET_VARS, $HTTP_SERVER_VARS,
	$HTTP_SESSION_VARS, $HTTP_COOKIE_VARS;

	//This was empty() but that caused blank values that actually are set to
	//fail with null instead of an empty string, the proper return value I
	//believe. Changing it back to isset().
	if (isset($_FILES[$name]))   { Trace("GetVar(): $name (File)    -> {$_FILES[$name]}<br/>\n"); return $_FILES[$name]; }
	if (isset($_POST[$name]))    { Trace("GetVar(): $name (Post)    -> {$_POST[$name]}<br/>\n"); return $_POST[$name]; }
	if (isset($_GET[$name]))     { Trace("GetVar(): $name (Get)     -> {$_GET[$name]}<br/>\n"); return $_GET[$name]; }
	if (isset($_SESSION[$name])) { Trace("GetVar(): $name (Session) -> {$_SESSION[$name]}<br/>\n"); return $_SESSION[$name]; }
	if (isset($_COOKIE[$name]))  { Trace("GetVar(): $name (Cookie)  -> {$_COOKIE[$name]}<br/>\n"); return $_COOKIE[$name]; }
	if (isset($_SERVER[$name]))  { Trace("GetVar(): $name (Server)  -> {$_SERVER[$name]}<br/>\n"); return $_SERVER[$name]; }

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

/**
 * Returns a series of values specified in array form, otherwise $default
 *
 * @param string $name Name example: 'posts[]'
 * @param string $default Default value alternatively.
 * @return mixed
 */
function GetVars($name, $default = null)
{
	$m = null;
	if (preg_match('#([^\[]+)\[([^\]]+)\]#', $name, $m))
	{
		$arg = GetVar($m[1]);

		$ix = 0;
		preg_match_all('/\[([^\[]*)\]/', $name, $m);
		foreach ($m[1] as $step)
		{
			if ($ix == $step) $ix++;
			$arg = @$arg[isset($step) ? $step : $ix++];
		}
		return !empty($arg)?$arg:$default;
	}
	else return GetVar($name, $default);
}

/**
 * Returns a series of posted values that match $match.
 *
 * @param string $match Regular Expression
 * @return array [name] => value
 */
function GetAssocPosts($match)
{
	$ret = array();
	foreach (array_keys($_POST) as $n)
	{
		if (preg_match("/^$match/", $n)) $ret[$n] = FormInput::GetPostValue($n);
	}
	return $ret;
}

/**
 * Just like GetVar but returns only post values.
 *
 * @param string $name Name to retrieve.
 * @param mixed $default Default value if not available.
 * @return mixed Value of $name post variable.
 */
function GetPost($name, $default = null)
{
	global $HTTP_POST_VARS;

	if (!empty($_POST[$name]))
	{
		Trace("GetVar(): $name (Post)    -> {$_POST[$name]}<br/>\n");
		return $_POST[$name];
	}

	if (isset($HTTP_POST_VARS[$name]) && strlen($HTTP_POST_VARS[$name]) > 0)
		return $HTTP_POST_VARS[$name];

	return $default;
}

/**
 * Unsets a var that was possibly set using SetVar or other methods of session
 * setting.
 *
 * @param string $name Name of variable to get rid of.
 */
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

/**
 * Returns information on a given variable in human readable form.
 *
 * @param mixed $var Variable to return information on.
 */
function VarInfo($var)
{
	echo "<div class=\"debug\"><pre>\n";
	if (!isset($var)) echo "[NULL VALUE]";
	else if (is_string($var) && strlen($var) < 1) echo '[EMPTY STRING]';
	echo str_replace("<", "&lt;", print_r($var, true));
	echo "</pre></div>\n";
}

/**
 * Attempts to keep a value persistant across all xedlib editors.
 *
 * @param string $name Name of value to persist.
 * @param mixed $value Value to be persisted.
 * @return mixed The passed $value.
 * @todo This is probably not needed in place of sessions.
 */
function Persist($name, $value)
{
	global $PERSISTS;
	$PERSISTS[$name] = $value;
	return $value;
}

/**
 * Returns a clean URI.
 *
 * @param string $url URL to clean.
 * @param array $uri URI appended on URL and cleaned.
 * @return string Cleaned URI+URL
 */
function URL($url, $uri = null)
{
	$ret = str_replace(' ', '%20', $url);

	global $PERSISTS;
	$nuri = array();
	if (!empty($uri)) $nuri = $uri;
	if (!empty($PERSISTS)) $nuri = array_merge($PERSISTS, $nuri);

	if (!empty($nuri))
	{
		$start = (strpos($ret, "?") < 1);
		foreach ($nuri as $key => $val)
		{
			if (isset($val))
			{
				$ret .= URLParse($key, $val, $start);
				$start = false;
			}
		}
	}
	return $ret;
}

/**
 * Parses an object or array for serialization to a uri.
 *
 * @param string $key Parent key for the current series to iterate.
 * @param mixed $val Object or array to iterate.
 * @param bool $start Whether or not this is the first item being parsed.
 * @return string Rendered url string.
 */
function URLParse($key, $val, $start = false)
{
	$ret = null;
	if (is_array($val))
		foreach ($val as $akey => $aval)
			$ret .= URLParse($key.'['.$akey.']', $aval, $start);
	else
	{
		$nval = str_replace(' ', '%20', $val);
		$ret .= ($start ? '?' : '&amp;')."{$key}={$nval}";
	}
	return $ret;
}

/**
 * Redirect the browser with a cleanly built URI.
 *
 * @param string $url Relative path to script
 * @param array $getvars Array of get variables.
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

/**
 * Converts an integer unix epoch timestamp to a mysql equivalent.
 *
 * @param int $ts Epoch timestamp.
 * @param bool $time Whether or not to include time.
 * @return string MySql formatted date.
 * @todo Move to data.
 */
function TimestampToMySql($ts, $time = true)
{
	if (empty($ts)) return null;
	return date($time ? 'Y-m-d h:i:s' : 'Y-m-d', $ts);
}

/**
 * Converts an integer unix epoch timestamp to a mssql equivalent.
 *
 * @param string $ts MySql time stamp.
 * @return int Timestamp.
 * @todo Move to data.
 */
function TimestampToMsSql($ts)
{
	return date("m/d/y h:i:s A", $ts);
}

/**
 * Converts a mysql date to a timestamp.
 *
 * @param string $date MySql Date/DateTime
 * @param bool $include_time Whether hours, minutes and seconds are included.
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

/**
 * Returns timestamp from a GetDateInput style GetVar value.
 *
 * @param array $value Array of 3 elements for a date returned from a GetVar.
 * @return int Timestamp result from mktime.
 */
function DateInputToTS($value)
{
	return mktime(null, null, null, $value[0], $value[1], $value[2]);
}

/**
 * Returns a string representation of time from $ts to now. Eg. '5 days'
 *
 * @param int $ts Timestamp.
 * @return string English offset.
 */
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

////////////////////////////////////////////////////////////////////////////////
//File
//

/**
 * Returns the filename portion of path without the extension applied.
 *
 * @param string $name Name of the file to return the extension from.
 * @return string File extension.
 */
function fileext($name)
{
	return substr(strrchr($name, '.'), 1);
}

/**
 * Returns a filename without the extension.
 *
 * @param string $name Name to strip the extension off.
 * @return string Stripped filename.
 */
function filenoext($name)
{
	if (strpos($name, '.')) return substr($name, 0, strrpos($name, '.'));
	return $name;
}

/**
 * Careful with this sucker.
 *
 * @param string $dir Directory to obliterate.
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

/**
 * Recursively deletes empty folders in the direction of the parent.
 *
 * @param string $dir Deepest level directory to empty.
 */
function DelEmpty($dir)
{
	$files = glob($dir.'/*');
	if (count($files) < 1) { @rmdir($dir); DelEmpty(dirname($dir)); }
}

/**
 * Reformats a file to be compatible with the current installation of php.
 *
 * @param string $file Filename to reformat.
 */
function Reformat($file)
{
	$c = file_get_contents($file);
	$c = preg_replace("/\tprivate|\tprotected|\tpublic/", "\tvar", $c);
	$c = preg_replace("/\tstatic /", "\t", $c);
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
	$dr = GetVar('DOCUMENT_ROOT'); //Probably Apache situated

	if (empty($dr)) //Probably IIS situated
	{
		//Get the document root from the translated path.
		$pt = str_replace('\\\\', '/', GetVar('PATH_TRANSLATED', GetVar('ORIG_PATH_TRANSLATED')));
		$dr = substr($pt, 0, -strlen(GetVar('SCRIPT_NAME')));
	}

	$dr = str_replace('\\\\', '/', $dr);

	return substr(str_replace('\\', '/', str_replace('\\\\', '/', $path)), strlen($dr));
}

/**
 * Returns an image from the RelativePath location.
 *
 * @param string $img Image filename.
 * @param string $title For the alt/title/alttitle attributes.
 * @param string $attribs Additional attributes.
 * @return string
 */
function GetImg($img, $title = 'unnamed', $attribs = null)
{
	$p = GetRelativePath(dirname(__FILE__));
	return "<img src=\"{$p}/images/{$img}\" alt=\"{$title}\" {$attribs}/>";
}

/**
 * Gets an image button.
 *
 * @param string $target Name of script to attach the anchor to.
 * @param string $img Name of the image, '/images' will already be included.
 * @param string $alt Alternate text for missing image or tooltip.
 * @param string $attribs Attributes to attach to the <img> tag.
 * @return string
 */
function GetButton($target, $img, $alt, $attribs = null)
{
	$path = GetRelativePath(dirname(__FILE__));

	return '<a href="'.URL($target).'">'.
		"<img src=\"{$path}/images/{$img}\"".
		" alt=\"{$alt}\" title=\"{$alt}\"".
		' '.$attribs.' style="vertical-align: text-bottom" /></a>';
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

function DataToTree($rows, $iCol, $pCol)
{
	// Build Flats

	$flats = array();
	if (!empty($rows))
	foreach ($rows as $row)
		$flats[$row[$iCol]] = new TreeNode($row, $row[$iCol]);

	// Build Tree

	$tnRoot = new TreeNode();

	if (!empty($flats))
	foreach ($flats as $tn)
	{
		if (isset($flats[$tn->data[$pCol]]))
			$flats[$tn->data[$pCol]]->AddChild($tn);
		else $tnRoot->AddChild($tn);
	}

	return $tnRoot;
}

/**
 * Returns the last item on the array, without popping it.
 *
 * @param array $array Array to grab the last item off from.
 * @return mixed Last item on the array.
 */
function &array_get($array)
{
	return $array[count($array)-1];
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

/**
 * Runs multiple callbacks with given arguments.
 *
 * @return mixed Returns whatever the callbacks do.
 */
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
		else $ret .= $item;
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
	return str_replace('[', '_', str_replace(']', '', str_replace(' ', '_', $id)));
}

/**
 * Pluralizes a string, eg User -> Users
 *
 * @param string $str String to pluralize.
 * @return string Properly pluralized string.
 */
function Plural($str)
{
	if (strlen($str) < 1) return null;
	if (substr($str, -1) == 'y') return substr($str, 0, -1).'ies';
	if (substr($str, -1) != 's') return "{$str}s";
	return $str;
}

/**
 * Attempts to disable the ability to inject different paths to gain higher
 * level directories in urls or posts.
 *
 * @param string $path Path to secure from url hacks.
 * @return string Properly secured path.
 */
function SecurePath($path)
{
	$ret = preg_replace('#^\.#', '', $path);
	$ret = preg_replace('#^/#', '', $ret);
	return preg_replace('#\.\./#', '', $ret);
}

/**
 * Returns a single flat page.
 *
 * @param array $data Data to trim.
 * @param int $page Page number we are currently on.
 * @param int $count Count of items per page.
 * @return array Poperly sliced array.
 */
function GetFlatPage($data, $page, $count)
{
	return array_splice($data, $count*$page, $count);
}

/**
 * Returns a database related page filter.
 *
 * @todo Move this to Data package.
 * @param int $page Current page.
 * @param int $count Count of items per page.
 * @return array DataSet usable 'filter' argument.
 */
function GetPageFilter($page, $count)
{
	return array(($page-1)*$count, $count);
}

/**
 * Gets html rendered series of pages.
 *
 * @param array $total Total items to paginate.
 * @param int $count Number of items per page.
 * @param array $args Additional uri args.
 * @return string Rendered html page display.
 * @todo Template this.
 */
function GetPages($total, $count, $args = null)
{
	global $me;

	if (!is_numeric($total))
	{
		throw new Exception("Invalid Total Value");
		return;
	}
	if ($total <= $count) return;

	$cp = GetVar('cp');
	$ret = null;
	$page = 0;

	if ($args == null) $args = array();

	if ($cp > 1)
		$ret .= Getbutton(URL($me, array_merge($args, array('cp' => 0))), 'start.png', 'Start')
		.' &ndash; ';
	if ($cp > 0)
		$ret .= GetButton(URL($me, array_merge($args, array('cp' => $cp-1))), 'prev.png', 'Previous').
		' &ndash; ';

	for ($ix = 0; $ix < $total; $ix += $count)
	{
		if ($ix > 0) $ret .= ' &ndash; ';
		$page = $ix / $count;
		$url = URL($me, array_merge(array('cp' => $page), $args));
		if ($page == $cp) $ret .= '<b>'.($page+1).'</b>';
		else $ret .= '<b><a href="'.$url.'">'.($page+1).'</a></b>';
	}

	if ($cp < $page)
		$ret .= ' &ndash; '.
		GetButton(URL($me, array_merge(array('cp' => $cp+1), $args)), 'next.png', 'Next');
	if ($cp < max(0, $page-1))
		$ret .= ' &ndash; '.
		GetButton(URL($me, array_merge(array('cp' => $page), $args)), 'end.png', 'End');

	return $ret;
}

/**
 * Properly strip slashes from the given string depending on the configuration.
 * @param string $str String to strip from.
 * @return string Cleaned string.
 */
function psslash($str)
{
	if (ini_get('magic_quotes_gpc')) return stripslashes($str);
	else return $str;
}

/**
 * Properly add slashes to the given string depending on the configuration.
 * @param string $str String to add slashes to.
 * @return string Cleaned string.
 */
function paslash($str)
{
	if (ini_get('magic_quotes_gpc')) return $str;
	else return addslashes($str);
}

/**
 * Converts a data size string to proper digits.
 *
 * @param string $str String to convert into proper size.
 * @return int String converted to proper size.
 */
function GetStringSize($str)
{
	$num = (int)substr($str, 0, -1);
	switch (strtoupper(substr($str, -1)))
	{
		case 'Y': $num *= 1024;
		case 'Z': $num *= 1024;
		case 'E': $num *= 1024;
		case 'P': $num *= 1024;
		case 'T': $num *= 1024;
		case 'G': $num *= 1024;
		case 'M': $num *= 1024;
		case 'K': $num *= 1024;
	}
	return $num;
}

/**
 * Converts a string from digits to proper data size string.
 *
 * @param int $size Size to convert into proper string.
 * @return string Size converted to a string.
 */
function GetSizeString($size)
{
	$units = explode(' ','B KB MB GB TB');
	for ($i = 0; $size > 1024; $i++) { $size /= 1024; }
	return round($size, 2).' '.$units[$i];
}

/**
 * PHP4 compatible array clone.
 *
 * @param mixed $arr Item to properly clone in php5 without references.
 * @return mixed Cloned copy of whatever you throw at it.
 */
function array_clone($arr)
{
	if (substr(phpversion(), 0, 1) != '5') { $copy = $arr; return $copy; }
	$ret = array();

	foreach ($arr as $id => $val)
	{
		if (is_array($val)) $ret[$id] = array_clone($val);
		else if (is_object($val)) $ret[$id] = clone($val);
		else $ret[$id] = $val;
	}

	return $ret;
}

/**
 * Create a directory recursively supporting php4.
 *
 * @param string $path Complete path to recursively create.
 * @param int $mode Initial mode for linux based filesystems.
 * @return bool Whether the directory creation was successful.
 */
function mkrdir($path, $mode = 0755)
{
	//$path = rtrim(preg_replace(array('#\\#', '#/{2,}#'), '/', $path), '/');
	$e = explode("/", ltrim($path, "/"));
	if (substr($path, 0, 1) == "/") $e[0] = "/".$e[0];
	$c = count($e);
	$cp = $e[0];
	for ($i = 1; $i < $c; $i++)
	{
		if (!is_dir($cp) && !@mkdir($cp, $mode)) return false;
		$cp .= "/".$e[$i];
	}
	return @mkdir($path, $mode);
}

/**
 * Returns var if it is set, otherwise def.
 * @param mixed $var Variable to check and return if exists.
 * @param mixed $def Default to return if $var is not set.
 * @return mixed $var if it is set, otherwise $def.
 */
function ifset($var, $def)
{
	if (isset($var)) return $var; return $def;
}

/**
 * Will possibly be depricated.
 * @param array $tree Stack of linkable items.
 * @param string $target Target script of interaction.
 * @param string $text Text to test.
 * @return string Text with words anchored in maybe html?
 */
function linkup($tree, $target, $text)
{
	require_once('h_template.php');
	$keys = array_keys($tree);
	$cur = null;
	$reps = array();

	$words = preg_split('/\s|\n/s', $text);
	$vp = new VarParser();

	foreach ($words as $word)
	{
		if (isset($cur))
		{
			if (in_array($word, array_keys($cur)))
			{
				$p = $vp->ParseVars($target,
					array('word' => $word));
				$reps[$word] = $p;
				if (isset($cur[$word]))
				{
					$cur = $cur[$word];
				}
				else $cur = null;
			}
		}
		if (in_array($word, $keys))
		{
			$p = $vp->ParseVars($target,
				array('name' => $tree[$word][0], 'word' => $word));
			$reps[$word] = $p;
			$cur = $tree[$word];
		}
	}

	$ret = $text;
	foreach ($reps as $word => $val) $ret = str_replace($word, $val, $ret);
	return $ret;
}

/**
 * Converts an array to a BitMask.
 *
 * @param array $array Array to bitmask.
 * @return int Bitwise combined values.
 */
function GetMask($array)
{
	$ret = 0;
	foreach ($array as $ix) $ret |= $ix;
	return $ret;
}

/**
 * Get a single result of a zip code location and information.
 *
 * @param DataSet $ds Location of zip code data.
 * @param int $zip Zip code to location information on.
 * @return array Single database result of the specified zip code.
 */
function GetZipLocation($ds, $zip)
{
	return $ds->GetOne(array('zip' => $zip));
}

/**
 * Locate the amount of miles between two latitudes and longitudes.
 *
 * @param float $lat1 Latitude Source
 * @param float $lat2 Latitude Destination
 * @param float $lon1 Longitude Source
 * @param float $lon2 Longitude Destination
 * @return float Distance.
 */
function GetMiles($lat1, $lat2, $lon1, $lon2)
{
	$lat1 = deg2rad($lat1);
	$lon1 = deg2rad($lon1);
	$lat2 = deg2rad($lat2);
	$lon2 = deg2rad($lon2);

	$delta_lat = $lat2 - $lat1;
	$delta_lon = $lon2 - $lon1;

	$temp = pow(sin($delta_lat/2.0),2) + cos($lat1) * cos($lat2) * pow(sin($delta_lon/2.0),2);
	$distance = 3956 * 2 * atan2(sqrt($temp),sqrt(1-$temp));
	return $distance;
}

/**
 * Zip code lookup to collect zip codes by mileage using the Great Circle
 * algorithm.
 *
 * @param DataSet $ds Location of zip code data.
 * @param int $zip Source zip code.
 * @param int $range Miles to search for other zip codes.
 * @return array ['dists'][zip] => distance, ['zips'] => zip
 */
function GetZips($ds, $zip, $range)
{
	$details = GetZipLocation($ds, $zip);  // base zip details
	if ($details == false) return null;

	$lat_range = $range / 69.172;
	$lon_range = abs($range / (cos($details['lng']) * 69.172));
	$min_lat = number_format($details['lat'] - $lat_range, "4", ".", "");
	$max_lat = number_format($details['lat'] + $lat_range, "4", ".", "");
	$min_lon = number_format($details['lng'] - $lon_range, "4", ".", "");
	$max_lon = number_format($details['lng'] + $lon_range, "4", ".", "");

	$query = "SELECT zip, lat, lng, name FROM zips
		WHERE lat BETWEEN '{$min_lat}' AND '{$max_lat}'
		AND lng BETWEEN '{$min_lon}' AND '{$max_lon}'";

	$items = $ds->GetCustom($query);

	$return = array();

	foreach ($items as $i)
	{
		$dist = GetMiles($details['lat'], $i['lat'], $details['lng'], $i['lng']);
		if ($dist <= $range)
		{
			$zip = str_pad($i['zip'], 5, "0", STR_PAD_LEFT);
			$return['dists'][$zip] = $dist;
			$return['zips'][] = $zip;
		}
	}

	asort($return['dists']);

	return $return;
}

/**
 * Regular expression matches files located in $path using $pattern and $opts.
 *
 * @param string $pattern Used by preg_match.
 * @param string $path Location to search.
 * @param int $opts Bitwise combination of PREG_FILES and PREG_DIRS.
 * @return array index => filename
 */
function preg_files($pattern, $path = '.', $opts = 3, &$subs = null)
{
	$ret = array();
	$dp = opendir($path);
	while ($file = readdir($dp))
	{
		if (is_file("$path/$file") && $opts & PREG_FILES != PREG_FILES) continue;
		if (is_dir("$path/$file") && $opts & PREG_DIRS != PREG_DIRS) continue;
		$s = null;
		if (preg_match($pattern, $file, $s))
		{
			$subs[$file] = $s;
			$ret[] = $file;
		}
	}
	return $ret;
}

function preg_file($pattern, $path, $opts = 3)
{
	$ret = preg_files($pattern, $path, $opts);
	return !empty($ret) ? $ret[0] : null;
}

/**
 * Returns true if $src path is inside $dst path.
 *
 * @param string $src Source pathname.
 * @param string $dst Destination pathname.
 * @return bool True if the source exists inside the destination.
 */
function is_in($src, $dst)
{
	$rpdst = realpath($dst);
	return substr(realpath($src), 0, strlen($rpdst)) == $rpdst;
}

/**
 * Encrypts strings formatted for htaccess files for windows based Apache
 * installations as they work a bit differently than linux.
 *
 * @param string $plainpasswd Password to encrypt.
 * @return string Encrypted string that can be placed in an htaccess file.
 */
function crypt_apr1_md5($plainpasswd)
{
	$salt = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8);
	$len = strlen($plainpasswd);
	$text = $plainpasswd.'$apr1$'.$salt;
	$bin = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
	for($i = $len; $i > 0; $i -= 16) { $text .= substr($bin, 0, min(16, $i)); }
	for($i = $len; $i > 0; $i >>= 1) { $text .= ($i & 1) ? chr(0) : $plainpasswd{0}; }
	$bin = pack("H32", md5($text));
	for($i = 0; $i < 1000; $i++)
	{
		$new = ($i & 1) ? $plainpasswd : $bin;
		if ($i % 3) $new .= $salt;
		if ($i % 7) $new .= $plainpasswd;
		$new .= ($i & 1) ? $bin : $plainpasswd;
		$bin = pack("H32", md5($new));
	}
	$tmp = '';
	for ($i = 0; $i < 5; $i++)
	{
		$k = $i + 6;
		$j = $i + 12;
		if ($j == 16) $j = 5;
		$tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
	}
	$tmp = chr(0).chr(0).$bin[11].$tmp;
	$tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
	"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
	"./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
	return "\$apr1\$".$salt."$".$tmp;
}

/**
 * Encrypts $text using $key as a passphrase using RIJNDAEL algorithm.
 *
 * @param string $key Key used to encrypt.
 * @param string $text Text to encrypt.
 * @return string Encrypted text.
 */
function RIJ_Encrypt($key, $text)
{
	srand();
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	$enc = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text,
		MCRYPT_MODE_CBC, $iv);
	return array($iv, $enc);
}

/**
 * Decrypts $text using $key and $iv using RIJNDAEL algorithm.
 *
 * @param string $key Used to decrypt $text.
 * @param string $text Ecrypted text.
 * @param string $iv Initialization Vector.
 * @return string Decrypted text.
 */
function RIJ_Decrypt($key, $text, $iv = null)
{
	srand();
	if ($iv == null)
	{
		$ivsize = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($ivsize, MCRYPT_RAND);
	}
	return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key,
		$text, MCRYPT_MODE_CBC, $iv), "\0");
}

/**
 * Returns every entry located in a given Apache style .htpasswd file.
 *
 * @param string $path Location without filename of the .htaccess file.
 * @return array username => password
 */
function get_htpasswd($path)
{
	$ret = array();
	$m = null;
	preg_match_all('/([^\n\r:]+):([^\r\n]+)/m', file_get_contents($path.'/.htpasswd'), $m);
	foreach ($m[1] as $i => $v) $ret[$v] = $m[2][$i];
	return $ret;
}

/**
 * Returns all directories for a given path but not recursively.
 *
 * @param string $path Directory to search.
 * @return array Single level array of directories located.
 */
function dir_get($path = '.')
{
	$ret = array();
	$dp = opendir($path);
	while ($f = readdir($dp))
	{
		if ($f[0] == '.') continue;
		if (is_dir($path.'/'.$f)) $ret[] = $f;
	}
	return $ret;
}

define('OPT_FILES', 1);
define('OPT_DIRS', 2);

/**
 * Returns an array of all files located recursively in a given path, excluding
 * anything matching the regular expression of $exclude.
 *
 * @param string $path Path to recurse.
 * @param string $exclude Passed to preg_match to blacklist files.
 * @return array Series of non-directories that were not excluded.
 */
function Comb($path, $exclude = null, $flags = 3)
{
	// This is a file and unable to recurse.
	if (is_file($path))
	{
		if (OPT_FILES & $flags) return array($path);
		return array();
	}

	else if (is_dir($path))
	{
		// We will return ourselves if we're including directories.
		$ret = ($flags & OPT_DIRS) ? array($path) : array();
		$dp = opendir($path);
		while ($f = readdir($dp))
		{
			if ($f[0] == '.') continue;
			$ret = array_merge($ret, Comb(realpath($path.'/'.$f), $exclude, $flags));
		}

		return $ret;
	}

	return array();
}

/**
 * Converts html style tag attributes into an xml array style.
 *
 * @param string $atrs Attributes to process.
 * @return array String indexed array of attributes.
 */
function ParseAtrs($atrs)
{
	if (empty($atrs)) return;
	$m = null;
	preg_match_all('/([^= ]+)="([^"]+)"/', $atrs, $m);
	for ($ix = 0; $ix < count($m[1]); $ix++) $ret[$m[1][$ix]] = $m[2][$ix];
	return $ret;
}

/**
 * Will set a session variable to $name with the value of GetVar and return it.
 *
 * @param string $name Name of our state object.
 * @return mixed The GetVar value of $name.
 */
function GetState($name, $def = null)
{
	return SetVar($name, GetVar($name, $def));
}

/**
 * Converts an array to a tree using TreeNode objects.
 *
 * @param TreeNode $n Node we are working with.
 * @param array $arr Array items to add to $n.
 * @return TreeNode Root of the tree.
 */
function ArrayToTree($n, $arr)
{
	$root = new TreeNode($n);
	foreach ($arr as $k => $v)
	{
		if (is_array($v)) $n = ArrayToTree($k, $v);
		else $n = new TreeNode($v);
		$root->AddChild($n);
	}
	return $root;
}

if (!function_exists('money_format'))
{
	function money_format($format, $number)
	{
		$regex  = array(
			'/%((?:[\^!\-]|\+|\(|\=.)*)([0-9]+)?(?:#([0-9]+))?',
			'(?:\.([0-9]+))?([in%])/'
		);
		$regex = implode('', $regex);
		if (setlocale(LC_MONETARY, null) == '') setlocale(LC_MONETARY, '');
		$locale = localeconv();
		$number = floatval($number);
		$fmatch = $match = null;
		if (!preg_match($regex, $format, $fmatch))
		{
			trigger_error("No format specified or invalid format", E_USER_WARNING);
			return $number;
		}
		$flags = array(
			'fillchar'  => preg_match('/\=(.)/', $fmatch[1], $match) ? $match[1] : ' ',
			'nogroup'   => preg_match('/\^/', $fmatch[1]) > 0,
			'usesignal' => preg_match('/\+|\(/', $fmatch[1], $match) ? $match[0] : '+',
			'nosimbol'  => preg_match('/\!/', $fmatch[1]) > 0,
			'isleft'    => preg_match('/\-/', $fmatch[1]) > 0
		);
		$width = trim($fmatch[2]) ? (int)$fmatch[2] : 0;
		$left = trim($fmatch[3]) ? (int)$fmatch[3] : 0;
		$right = trim($fmatch[4]) ? (int)$fmatch[4] :
		$locale['int_frac_digits'];
		$conversion = $fmatch[5];
		$positive = true;
		if ($number < 0)
		{
			$positive = false;
			$number *= -1;
		}
		$letter = $positive ? 'p' : 'n';
		$prefix = $suffix = $cprefix = $csuffix = $signal = '';
		if (!$positive)
		{
			$signal = $locale['negative_sign'];
			switch (true)
			{
				case $locale['n_sign_posn'] == 0 || $flags['signal'] == '(':
					$prefix = '(';
					$suffix = ')';
					break;
				case $locale['n_sign_posn'] == 1:
					$prefix = $signal;
					break;
				case $locale['n_sign_posn'] == 2:
					$suffix = $signal;
					break;
				case $locale['n_sign_posn'] == 3:
					$cprefix = $signal;
					break;
				case $locale['n_sign_posn'] == 4:
					$csuffix = $signal;
					break;
			}
		}
		if (!$flags['nosimbol'])
		{
			$currency  = $cprefix;
			$currency .= (
				$conversion == 'i' ?
				$locale['int_curr_symbol'] :
				$locale['currency_symbol']
			);
			$currency .= $csuffix;
		}
		else $currency = '';
		$space = $locale["{$letter}_sep_by_space"] ? ' ' : '';

		$number = number_format($number, $right, $locale['mon_decimal_point'], $flags['nogroup'] ? '' : $locale['mon_thousands_sep']);
		$number = explode($locale['mon_decimal_point'], $number);

		$n = strlen($prefix) + strlen($currency);
		if ($left > 0 && $left > $n)
		{
			if ($flags['isleft'])
			{
				$number[0] .= str_repeat($flags['fillchar'], $left - $n);
			}
			else
			{
				$number[0] = str_repeat($flags['fillchar'], $left - $n) . $number[0];
			}
		}
		$number = implode($locale['mon_decimal_point'], $number);
		if ($locale["{$letter}_cs_precedes"])
			$number = $prefix.$currency.$space.$number.$suffix;
		else $number = $prefix.$number.$space.$currency.$suffix;
		if ($width > 0)
			$number = str_pad($number, $width, $flags['fillchar'],
				$flags['isleft'] ? STR_PAD_RIGHT : STR_PAD_LEFT);
		$format = str_replace($fmatch[0], $number, $format);
		return $format;
	}
}

function get_csv($str)
{
	$arr = preg_split('/,(?=(?:[^"]*"[^"]*")*(?![^"]*"))/', trim($str));
	foreach ($arr as $k => $v) $arr[$k] = trim($v, '"');
	return $arr;
}

/**
 * Will let a variable be set only if it's not already set.
 *
 * @param mixed $var Variable to Let.
 * @param mixed $val Value to set to $var.
 */
function Let(&$var, $val)
{
	if (!isset($var)) $var = $val;
}

function GetMonthName($month)
{
	return date('F', strtotime($month.'/1/'.date('Y')));
}

function strtoval($val, $def)
{
	if (is_numeric($val)) $ret = (int)$val;
	else $ret = @eval('return '.$val.';');
	if (!isset($ret)) return $def;
	return $ret;
}

function SendDownloadStart($filename, $size = null)
{
	//Caching
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

	//Ensure we get a download dialog
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Disposition: attachment; filename=\"$filename\";");
	header("Content-Transfer-Encoding: binary");
	if (isset($size)) header("Content-Length: {$size}");
}

function TagNEmpty($t, $g, $a)
{
	$n = $a['VAR'];
	global $$n;
	if (empty($a['INDEX']) && !empty($$n)) return $g;
	$var = $$n;
	if (!empty($var) && !empty($var[$a['INDEX']])) return $g;
}

function GetQ()
{
	$pi = GetVar('q', '/home');
	if (!empty($pi)) return explode('/', substr($pi, 1));
}

function Pull(&$arr, $key)
{
	$ret = $arr[$key];
	unset($arr[$key]);
	return $ret;
}

function array_lower_keys($array)
{
	if (!is_array($array)) return null;
	$ret = array();
	foreach ($array as $k => $v) $ret[strtolower($k)] = $v;
	return $ret;
}

function preg_rename($glob, $preg_src, $preg_dst)
{
	foreach (glob($glob) as $f)
	{
		if (preg_match($preg_src, $f))
		{
			$dst = preg_replace($preg_src, $preg_dst, $f);
			rename($f, $dst);
		}
	}
}

function get_relative_sizes($arr, $min_size = 12, $max_size = 32)
{
	$max_qty = max(array_values($arr));
	$min_qty = min(array_values($arr));

	$spread = max(1, $max_qty - $min_qty);

	$step = ($max_size - $min_size) / ($spread);

	foreach ($arr as $key => $value)
	{
		$size = round($min_size + (($value - $min_qty) * $step));
		$ret[$key] = $size;
    }
    return $ret;
}

?>
