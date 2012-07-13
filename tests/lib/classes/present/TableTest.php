<?php

require_once dirname(__FILE__) . '/../../../../lib/classes/present/table.php';

/**
 * Test class for Table.
 * Generated by PHPUnit on 2011-02-21 at 11:02:56.
 */
class TableTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var Table
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		$this->object = new Table('tbltest', array('col1', 'col2'),
			array('width="100%"'));
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown() {

	}

	/**
	 * @todo Implement testAddRow().
	 */
	public function testAddRow() {
		$this->object->AddRow(array('item1', 'item2'));
		$this->assertEquals($this->object->rows[0][0], 'item1');
	}

	/**
	 * @todo Implement testGet().
	 */
	public function testGet() {
		$this->object->AddRow(array('item1', 'item2'));
		$this->object->AddRow(array('missing two'));
		var_dump($this->object->Get(array('CLASS' => 'test')));
	}

}

?>
