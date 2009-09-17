<?php

Module::RegisterModule('ModNav');

class ModNav extends Module
{
	public $Block = 'nav';

	static function GetLinks($title, $link, $depth = -1)
	{
		// We have children to process.

		if (is_array($link))
		{
			$ret = null;

			if (!empty($title))
				$ret .= "<li><span class=\"nav_header\">{$title}</span><ul>\n";
			else $ret .= '<ul class="nav">';
			foreach ($link as $t => $l)
				$ret .= ModNav::GetLinks($t, $l, $depth+1);
			if (!empty($title)) $ret .= "</ul></li>\n";
			else $ret .= '</ul>';
			return $ret;
		}

		// No children under this link.

		else
		{
			return "<li><a href=\"{$link}\">$title</a></li>\n";
		}
	}

	function Get()
	{
		global $_d;

		$out = null;
		if (isset($_d['nav.links']))
			$out .= ModNav::GetLinks(null, $_d['nav.links']);
		if (strlen($out) > 0) return $out;
	}
}

?>
