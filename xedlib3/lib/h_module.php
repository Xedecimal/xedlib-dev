<?php

class Module
{
	static function Initialize()
	{
		$files = glob('modules/*.php');
		foreach ($files as $file) require_once($file);
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
		global $_d;

		$tprep = new Template();
		$tprep->ReWrite('block', 'Module::TagPrepBlock');
		$tprep->ParseFile($template);

		$t = new Template($_d);
		$t->ReWrite('block', 'Module::TagBlock');

		global $mods;
		RunCallbacks(@$_d['index.cb.prelink']);
		foreach ($mods as $mod) $mod->PreLink();
		foreach ($mods as $mod) $mod->Link();
		foreach ($mods as $mod) $mod->Prepare();
		foreach ($mods as $mod)
			$GLOBALS['_d']['blocks'][$mod->Block] .= $mod->Get();

		return $t->ParseFile($template);
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
