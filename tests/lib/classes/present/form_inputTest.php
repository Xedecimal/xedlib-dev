<?php
require_once dirname(__FILE__) . '/../../../../lib/classes/present/form_input.php';

/**
 * Test class for FormInput.
 * Generated by PHPUnit on 2010-11-18 at 20:59:50.
 */
class FormInputTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var FormInput
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->object = new FormInput('Test', 'text', 'name', null,
			'test="value"');
		$this->object = new FormInput('Test', 'state', 'name', 23);
		$this->object = new FormInput('Test', 'fullstate', 'name', 23);
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{

	}

	/**
	 * @todo Implement testAttr().
	 */
	public function testAttr()
	{
		$in = new FormInput('Test', 'text', 'name', null, array('test' => 1));
		$in->attr('href', 'test');
		$this->assertEquals('test', $in->attr('href'));
	}

	/**
	 * @todo Implement testMask_callback().
	 */
	public function testMask_callback()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGet().
	 */
	public function testGet()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetValue().
	 */
	public function testGetValue()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetCleanID().
	 */
	public function testGetCleanID()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetPostValue().
	 */
	public function testGetPostValue()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetData().
	 */
	public function testGetData()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetSelect().
	 */
	public function testGetSelect()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetMonthSelect().
	 */
	public function testGetMonthSelect()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetYear().
	 */
	public function testGetYear()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetDate().
	 */
	public function testGetDate()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetTime().
	 */
	public function testGetTime()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetBoolean().
	 */
	public function testGetBoolean()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetInputState().
	 */
	public function testGetInputState()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testInputToString().
	 */
	public function testInputToString()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}

?>