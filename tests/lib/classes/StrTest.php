<?php

require_once dirname(__FILE__) . '/../../../lib/classes/Str.php';

/**
 * Test class for Str.
 * Generated by PHPUnit on 2010-11-18 at 20:12:06.
 */
class StrTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var Str
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->object = new Str;
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
		
	}

	/**
	 * @todo Implement testPlural().
	 */
	public function testPlural()
	{
		$this->assertEquals('Users', Str::Plural('User'));
		$this->assertEquals('Useries', Str::Plural('Usery'));
		$this->assertEquals(null, Str::Plural(''));
	}

	/**
	 * @todo Implement testSizeString().
	 */
	public function testSizeString()
	{
		$this->assertEquals('64 KB', Str::SizeString(65536));
		$this->assertEquals('32 MB', Str::SizeString(33552432));
		$this->assertEquals('976.32 MB', Str::SizeString(1023741824));
	}

	/**
	 * @todo Implement testGetStringSize().
	 */
	public function testGetStringSize()
	{
		$this->assertEquals(204800, Str::GetStringSize('200K'));
	}

	/**
	 * @todo Implement testChomp().
	 */
	public function testChomp()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testRandomString().
	 */
	public function testRandomString()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}

?>