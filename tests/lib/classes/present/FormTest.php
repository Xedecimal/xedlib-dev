<?php
require_once dirname(__FILE__) . '/../../../../lib/classes/present/Form.php';

/**
 * Test class for Form.
 * Generated by PHPUnit on 2010-11-18 at 20:45:01.
 */
class FormTest extends PHPUnit_Framework_TestCase
{
	public function testForm()
	{
		$f = new Form('test');
		$f->AddHidden('testh', 'value');

		$arr = array('name' => 'value1');
		$opts = FormOption::FromArray($arr);
		$fi = new FormInput('Test', 'select', 'test', $opts,
			array('id' => 'sel-test'), 'Help');
		$f->AddInput($fi);

		$f->Get();
	}
}

?>
