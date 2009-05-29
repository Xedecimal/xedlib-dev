<?php

Module::RegisterModule('ModPage');

class ModPage extends Module
{
	function Get()
	{
		global $_d;

		$file = "content/{$_d['q'][0]}.xml";
		if (file_exists($file)) return file_get_contents($file);
	}
}

?>
