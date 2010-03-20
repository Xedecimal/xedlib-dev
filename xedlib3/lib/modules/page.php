<?php

Module::Register('ModPage');

class ModPage extends Module
{
	function Get()
	{
		global $_d;

		$name = @$_d['q'][0];
		$file = "content/{$name}.xml";

		$content = @file_get_contents($file);
		return '<div class="page_content">'.$content.'</div>';
	}
}

?>
