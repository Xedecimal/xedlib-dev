<?php

Module::RegisterModule('ModNav');

class ModNav extends Module
{
	public $Block = 'nav';

	function __construct()
	{
		global $_d;
	}

	/**
	* put your comment there...
	*
	* @param TreeNode $link
	* @param int $depth
	*/
	static function GetLinks($t, $l)
	{
		// We have children to process.

		if (is_array($l))
		{
			$ret = null;

			if (!empty($t))
				$ret .= '<li><a href="#">'.$t."</a><ul>\n";
			else $ret .= '<ul class="nav">';
			foreach ($l as $t => $c)
				$ret .= ModNav::GetLinks($t, $c);
			$ret .= '</ul>';
			return $ret;
		}

		// No children under this link.

		else return "<li><a href=\"{$l}\">{$t}</a></li>\n";
	}

	function Get()
	{
		global $_d;

		if (!empty($_d['nav.links']))
			return ModNav::GetLinks(null, $_d['nav.links']);
	}
}

?>
