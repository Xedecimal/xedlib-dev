<?php

require_once dirname(__FILE__) . '/../../../lib/classes/module.php';
require_once dirname(__FILE__) . '/../../../lib/classes/utility.php';
require_once(__DIR__.'/../../../lib/modules/file_manager/file_manager.php');
require_once(__DIR__.'/../../../h_main.php');

class ModuleTest extends PHPUnit_Framework_TestCase
{
	protected function setUp()
	{
		Module::Initialize(realpath(dirname(__FILE__).'../../../'), true);
	}

	public function testModule()
	{
		Module::Register('TestModule');

		$this->assertEquals('test_something', $GLOBALS['mods']['TestModule']->GetName(true));

		$temp = <<<EOF
<html doctype="5">
<link rel="stylesheet" type="text/css" href="{{app_abs}}/css.css" />
<body>
<block name="default" />
</body>
</html>
EOF;
		Module::RunString($temp);
	}

	public function testP()
	{
		$this->assertEquals(__DIR__, Module::P(__DIR__));
	}

	public function testL()
	{
		$this->assertEquals(__DIR__, Module::L(__DIR__));
	}
}

class TestModule extends Module
{
	public $Name = 'test/something';

	function __construct()
	{
		parent::__construct();
	}

	function Get()
	{
	}
}
