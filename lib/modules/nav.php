<?php

Module::RegisterModule('ModNav');

class ModNav extends Module
{
	public $Block = 'nav';

	function __construct()
	{
		global $_d;

		$_d['nav.links'] = new TreeNode();
	}

	/**
	* put your comment there...
	*
	* @param TreeNode $link
	* @param int $depth
	*/
	static function GetLinks($link, $depth = -1)
	{
		// We have children to process.

		if (!empty($link->children))
		{
			$ret = null;

			if (!empty($link->data))
				$ret .= '<li><a href="#">'.$link->data."</a><ul>\n";
			else $ret .= '<ul id="nav" class="menu vertical">';
			foreach ($link->children as $c)
				$ret .= ModNav::GetLinks($c, $depth+1);
			if (!empty($link->data)) $ret .= "</ul></li>\n";
			else $ret .= '</ul>';
			return $ret;
		}

		// No children under this link.

		else return "<li><a href=\"{$link->id}\">{$link->data}</a></li>\n";
	}

	function Get()
	{
		global $_d;

		$out = null;
		if (isset($_d['nav.links']))
		{
			$t = new Template();
			$t->ReWrite('link', array($this, 'TagLink'));
			$t->ReWrite('head', array($this, 'TagHead'));
			return ModNav::GetLinks($_d['nav.links']);
		}
	}
}

?>
