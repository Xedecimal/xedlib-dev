<?php
require_once dirname(__FILE__) . '/../../../lib/classes/U.php';

/**
 * Test class for U.
 * Generated by PHPUnit on 2010-11-18 at 20:23:45.
 */
class UTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var U
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->object = new U;
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
		
	}

	/**
	 * @todo Implement testVarInfo().
	 */
	public function testVarInfo()
	{
		U::VarInfo("Test");
		U::VarInfo(33);
		U::VarInfo(new Form('Test'));
		U::VarInfo(array('Test' => 1, 2 => 3));
	}

	/**
	 * @todo Implement testAsk().
	 */
	public function testAsk()
	{
		$o = 'one';
		$t = array('two' => 'test');
		$this->assertEquals('one', U::Ask($o, 'fail'));
		$this->assertEquals('test', U::Ask($t['two'], 'fail'));
		$this->assertEquals('three', U::Ask($th, 'three'));
	}

	/**
	 * @todo Implement testDBoolCallback().
	 */
	public function testDBoolCallback()
	{
		$res['tbl_bool'] = '1';
		$this->assertEquals(true, U::DBoolcallback(null, $res, 'tbl_bool'));
	}

	/**
	 * @todo Implement testBoolCallback().
	 */
	public function testBoolCallback()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testTSCallback().
	 */
	public function testTSCallback()
	{
		$t = mktime(5, 5, 5, 5, 6, 2010);
		$row['tbl_date'] = $t;
		$this->assertEquals(strftime('%x', $t),
			U::TSCallback(null, $row, 'tbl_date'));
	}

	/**
	 * @todo Implement testDateCallbackD().
	 */
	public function testDateCallbackD()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testDateCallback().
	 */
	public function testDateCallback()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testDateTimeCallbackD().
	 */
	public function testDateTimeCallbackD()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testDateTimeCallback().
	 */
	public function testDateTimeCallback()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testRunCallbacks().
	 */
	public function testRunCallbacks()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testIfset().
	 */
	public function testIfset()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}

?>
