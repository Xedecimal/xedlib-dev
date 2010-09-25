<?php

class ModPage extends Module
{
	function Get()
	{
		global $_d;

		$name = @$_d['q'][0];

		if ($name == 'part') $name = $_d['q'][1];
		$file = "content/{$name}.xml";

		$content = @file_get_contents($file);
		if ($_d['q'][0] == 'part') die($content);
		return '<div class="page_content">'.$content.'</div>';
	}
}

Module::Register('ModPage');

?>
