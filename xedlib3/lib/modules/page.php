<?php

Module::RegisterModule('ModPage');

class ModPage extends Module
{
	function Get()
	{
		global $_d;

		$name = @$_d['q'][0];
		$file = "content/{$name}.xml";
		$t = new Template();
		if (file_exists($file)) return $t->ParseFile($file);
	}
}

?>
