<?php

function p($path)
{
	// Only translate finished paths.
	if (preg_match('/{/', $path)) return $path;

	global $_d;
	$abs = $_d['app_abs'];
	if (substr($path, 0, strlen($abs)) == $abs) return $path;

	$tmp = @$_d['settings']['site_template'];

	// Overloaded Path
	$opath = "$tmp/$path";
	if (file_exists($opath)) return "$abs/$opath";
	// Module Path
	$modpath = "modules/{$path}";
	if (file_exists($modpath)) return "$abs/modules/$path";
	// Xedlib Path
	$xedpath = __DIR__.'/'.$path;
	if (file_exists($xedpath)) return GetRelativePath(__DIR__).'/'.$path;
	return $path;
}

function l($path)
{
	global $_d;

	$ovrpath = @$_d['settings']['site_template'].'/'.$path;
	if (file_exists($ovrpath)) return "{$_d['app_dir']}/{$ovrpath}";
	$modpath = "{$_d['app_dir']}/modules/{$path}";
	if (file_exists($modpath)) return "{$_d['app_dir']}/modules/{$path}";
	$xedpath = __DIR__.'/'.$path;
	if (file_exists($xedpath)) return $xedpath;
	return $path;
}

class Module
{
	static function Initialize($repath = false)
	{
		if (!file_exists('modules')) return;
		$dp = opendir('modules');
		while ($f = readdir($dp))
		{
			$p = 'modules/'.$f;
			if ($f[0] == '.') continue;
			if (is_dir($p) && file_exists($p.'/'.$f.'.php'))
				require_once($p.'/'.$f.'.php');
			else if (fileext($p) == 'php') require_once($p);
		}
		closedir($dp);
		if ($repath)
		{
			global $_d;

			$_d['template.transforms']['link'] = array('Module', 'TransHref');
			$_d['template.transforms']['a'] = array('Module', 'TransHref');
			$_d['template.transforms']['img'] = array('Module', 'TransSrc');
			$_d['template.transforms']['script'] = array('Module', 'TransSrc');
		}
	}

	/**
	* put your comment there...
	*
	* @param string $name Class name of defined module class.
	* @param array $deps Depended modules eg. array('ModName', 'ModName2')
	*/
	static function RegisterModule($name, $deps = null)
	{
		global $_d;
		if (!empty($_d['module.disable'][$name])) return;
		if (!empty($deps))
		foreach ($deps as $dep) if (!empty($_d['module.disable'][$dep])) return;

		if (!empty($_d['module.enable']) && empty($_d['module.enable'][$name]))
			return;

		$GLOBALS['mods'][$name] = new $name(file_exists('settings.ini'));
	}

	static function Run($template)
	{
		require_once('h_utility.php');
		require_once('h_template.php');

		global $_d;

		$tprep = new Template();
		$tprep->ReWrite('block', array('Module', 'TagPrepBlock'));
		$tprep->ParseFile($template);

		$t = new Template($_d);
		$t->ReWrite('head', array($t, 'TagAddHead'));
		$t->ReWrite('block', array('Module', 'TagBlock'));

		global $mods;

		if (!empty($mods))
		{
			if (!empty($_d['module.disable']))
				foreach (array_keys($_d['module.disable']) as $m)
					unset($mods[$m]);

			uksort($mods, array('Module', 'cmp_mod'));
			RunCallbacks(@$_d['index.cb.prelink']);

			foreach ($mods as $n => $mod) $mod->PreLink();
			foreach ($mods as $n => $mod) $mod->Link();
			foreach ($mods as $n => $mod) $mod->Prepare();
			foreach ($mods as $n => $mod)
			{
				if (@array_key_exists($mod->Block, $_d['blocks']))
					$_d['blocks'][$mod->Block] .= $mod->Get();
				else
					@$_d['blocks']['default'] .= $mod->Get();
			}
		}

		$t = new Template($_d);
		$t->ReWrite('block', array('Module', 'TagBlock'));
		return $t->ParseFile($template);
	}

	static function cmp_mod($x, $y)
	{
		global $_d;

		return @$_d['module.order'][$x] >
			@$_d['module.order'][$y];
	}

	static function TagPrepBlock($t, $g, $a)
	{
		global $_d;
		if (!isset($_d['blocks'][$a['NAME']])) $_d['blocks'][$a['NAME']] = null;
	}

	static function TagBlock($t, $g, $a)
	{
		global $_d;
		return $_d['blocks'][$a['NAME']];
	}

	public $Block = 'default';
	/** @var boolean */
	public $Active;

	function DataError($errno)
	{
		global $_d;

		//No such table - Infest this database.
		if ($errno == ER_NO_SUCH_TABLE)
		{
			global $mods;

			echo mysql_error();
			echo '<p style="font-weight: bold">Got no such table.
				Verifying database integrity.
				Expect many errors during this process.</p>';

			foreach ($mods as $name => $mod)
			{
				$mod->Install();
				#preg_match('/^mod(.*)/', strtolower($name), $m);
				#if (!file_exists('modules/'.$m[1].'.sql')) continue;
				#$queries = explode(';', file_get_contents('modules/'.$m[1].'.sql'));

				#foreach ($queries as $q)
				#{
				#	$q = trim($q);
				#	if (!empty($q)) $_d['db']->Query($q);
				#}
			}
			return true;
		}
	}

	function CheckActive($name)
	{
		global $_d;

		if (@$_d['q'][0] == $name)
		{
			@$GLOBALS['me'] .= '/'.array_shift($_d['q']);
			$this->Active = true;
		}
	}

	/**
	* New Availability: Database
	* Overload Responsibility: Link up your datasets, make them available for
	* other modules and any other initial construction.
	*/
	function __construct($installed) { }

	/**
	* Overload Responsibility: Before linkage, do security checks and validation
	* possibly before we move on to preparation so you will be ready to present
	* contextual data.
	*/
	function PreLink() { }

	/**
	* New Availability: DataSet
	* Overload Responsibility: Link your datasets with any other datasets.
	*/
	function Link() { }

	/**
	* New Availability: Links
	* Overload Responsibility: Base functionality, data manipulation,
	* callbacks, etc.
	*/
	function Prepare() { }

	/**
	 * Overload Responsibility: Return the display of your module.
	 */
	function Get() { }

	/**
	 * Overload Responsibility: Create your data set and initial data.
	 */
	function Install() { }

	/**
	 * Overload Responsibility: Return fields for the installer to gather from
	 * the user.
	 */
	function InstallFields(&$frm) { }

	static function TransHref($a)
	{
		if (isset($a['HREF'])) $a['HREF'] = p($a['HREF']);
		return $a;
	}

	static function TransSrc($a)
	{
		if (isset($a['SRC'])) $a['SRC'] = p($a['SRC']);
		return $a;
	}
}

?>
