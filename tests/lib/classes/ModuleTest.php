<?php

require_once dirname(__FILE__) . '/../../../lib/classes/module.php';
require_once dirname(__FILE__) . '/../../../lib/classes/utility.php';
require_once(__DIR__.'/../../../lib/modules/file_manager/file_manager.php');
require_once(__DIR__.'/../../../h_main.php');

class ModuleTest extends PHPUnit_Framework_TestCase
{
	public function testModule()
	{
		Module::Initialize(realpath(dirname(__FILE__).'../../../'), true);

		Module::Register('FileManager');
		$temp = <<<EOF
<html doctype="5">
<body>
<block name="default" />
</body>
</html>
EOF;
		Module::RunString($temp);
	}
}
