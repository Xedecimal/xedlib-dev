<?php

class ModNav extends Module
{
	public $Block = 'nav';

	/**
	*
	*
	* @param TreeNode $link
	* @param int $depth
	*/
	static function GetLinks($link, $depth = -1)
	{
		$ret = null;

		// We have children to process.

		if (!empty($link->data))
		{
			if (is_array($link->data))
			{
				$dat = $link->data;
				$text = $dat['TEXT'];
				unset($dat['TEXT']);
				$atrs = GetAttribs($dat);
				$ret .= '<li><a href="'.$link->id.'"'.$atrs.'>'.$text.
					"</a>\n";
			}
			else $ret .= '<li><a href="'.$link->id.'">'.$link->data.
				"</a>\n";
		}

		if (!empty($link->children))
		{
			$ret = null;

			if (!empty($link->data))
				$ret .= '<li><a href="#">'.$link->data."</a><ul>\n";
			else $ret .= '<ul class="nav menu vertical">';
			foreach ($link->children as $c)
				$ret .= ModNav::GetLinks($c, $depth+1);
			$ret .= '</ul>';
		}

		if (!empty($link->data)) $ret .= '</li>';

		return $ret;
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

Module::Register('ModNav');

?>
