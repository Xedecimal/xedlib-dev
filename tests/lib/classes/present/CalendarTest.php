<?php

require_once dirname(__FILE__) . '/../../../../lib/classes/present/Calendar.php';

/**
 * Test class for Calendar.
 * Generated by PHPUnit on 2010-11-18 at 05:05:42.
 */
class CalendarTest extends PHPUnit_Framework_TestCase
{
	public function testCalendar()
	{
		$cal = new Calendar;
		$tsfrom = time();
		$tsto = strtotime('2 days');
		$cal->AddItem($tsfrom, $tsto, 'Testing');
		$cal->Get();
		$cal->GetVert();
	}

	/**
	 * @todo Implement testTagMonth().
	 */
	public function testTagMonth()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testTagWeek().
	 */
	public function testTagWeek()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testTagDay().
	 */
	public function testTagDay()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testTagEvent().
	 */
	public function testTagEvent()
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
	 * @todo Implement testGetVert().
	 */
	public function testGetVert()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}

?>