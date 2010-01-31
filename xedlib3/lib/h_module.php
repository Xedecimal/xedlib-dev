<?php

class Module
{
	static function Initialize()
	{
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
	}

	static function RegisterModule($name)
	{
		global $_d;
		if (!empty($_d['module.disable'][$name])) return;

		if (!empty($_d['module.enable']) && empty($_d['module.enable'][$name]))
			return;

		$GLOBALS['mods'][$name] = new $name(file_exists('settings.txt'));
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
		$t->ReWrite('block', array('Module', 'TagBlock'));

		global $mods;
		if (!empty($mods))
		{
			usort($mods, array('Module', 'cmp_mod'));
			RunCallbacks(@$_d['index.cb.prelink']);
			foreach ($mods as $n => $mod)
				if (!isset($_d['module.disable'][$n])) $mod->PreLink();
			foreach ($mods as $n => $mod)
				if (!isset($_d['module.disable'][$n])) $mod->Link();
			foreach ($mods as $n => $mod)
				if (!isset($_d['module.disable'][$n])) $mod->Prepare();
			foreach ($mods as $n => $mod)
			{
				if (isset($_d['module.disable'][$n])) continue;
				if (array_key_exists($mod->Block, $_d['blocks']))
					$_d['blocks'][$mod->Block] .= $mod->Get();
				else
					$_d['blocks']['default'] .= $mod->Get();
			}
		}

		$t = new Template($_d);
		$t->ReWrite('block', array('Module', 'TagBlock'));
		return $t->ParseFile($template);
	}

	static function cmp_mod($x, $y)
	{
		global $_d;

		return @$_d['module.order'][get_class($x)] <
			@$_d['module.order'][get_class($y)];
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
}

?>
