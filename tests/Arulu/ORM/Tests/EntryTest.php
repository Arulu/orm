<?php

/*
 * Copyright (c) 2012 Arulu Inversiones SL
 * Todos los derechos reservados
 */

use Arulu\ORM\ORM;
use Arulu\ORM\Entry;

/**
 * Test class for Entry
 *
 * @package ORM
 *
 * @author noel <noelgarciamolina@gmail.com>
 */
class EntryTest extends PHPUnit_Framework_TestCase
{

	protected $object;
	protected $parent;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->parent = new ORM(sprintf('mysql:host=%s;dbname=%s', SERVER, DB), USER, PASS);
		$this->object = $this->parent->init("stupidorm_apellidos")->create();
		$this->value_string = 'testValue';
		$this->value_number = 33;
		$this->key = 'Apellido';
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{

	}

	public function testNow()
	{
		$this->assertTrue(is_string($this->object->now()));
		$this->assertTrue(is_integer(strtotime($this->object->now())));
		$this->assertTrue(is_string(Entry::now()));
		$this->assertTrue(is_integer(strtotime(Entry::now())));
	}

	public function testSet()
	{
		$this->assertEquals(0, count($this->object->getData()));
		$this->object->set($this->key, $this->value_string);
		$this->assertEquals(1, count($this->object->getData()));
		$this->assertEquals(1, count($this->object->getDirtyFields()));
		$this->assertEquals($this->value_string, $this->object->{$this->key});
		$this->assertTrue(array_key_exists($this->key, $this->object->getData()));
		$this->assertTrue(is_string($this->object->{$this->key}));
		$this->object->set($this->key, $this->value_number);
		$this->assertTrue(is_integer($this->object->{$this->key}));
		return $this->object;
	}

	public function testUnsetDirty()
	{
		$this->assertEquals(0, count($this->object->getDirtyFields()));
		$this->object->set($this->key, $this->value_string);
		$this->assertEquals(1, count($this->object->getDirtyFields()));
		$this->object->unsetDirty($this->key);
		$this->assertEquals(0, count($this->object->getDirtyFields()));
	}

	public function testIsDirty()
	{
		//$this->object shouldn't be dirty because it's empty
		$this->assertFalse($this->object->isDirty($this->key));
		$this->object->set($this->key, $this->value_string);
		$this->assertTrue($this->object->isDirty($this->key));
	}

	public function test__get()
	{
		$this->assertNull($this->object->{$this->key});
		$this->object->set($this->key, $this->value_string);
		$this->assertNotNull($this->object->{$this->key});
		$this->assertEquals($this->value_string, $this->object->{$this->key});
	}

	public function test__set()
	{
		$this->assertNull($this->object->{$this->key});
		$this->object->{$this->key} = $this->value_string;
		$this->assertNotNull($this->object->{$this->key});
		$this->assertEquals($this->value_string, $this->object->{$this->key});
	}

	public function test__isset()
	{
		$this->assertFalse(isset($this->object->{$this->key}));
		$this->object->{$this->key} = $this->value_string;
		$this->assertTrue(isset($this->object->{$this->key}));
	}

	public function testSave()
	{
		$this->object->{$this->key} = $this->value_string;
		$this->assertTrue($this->object->isNew());
		$counter = clone $this->parent;
		//counting table elements
		$entries = $counter->count();
		//inserting new row
		$this->object->save();
		$this->assertFalse($this->object->isNew());
		//counting again
		$this->assertEquals($entries + 1, $this->parent->count());
	}

	public function testGetDirtyFields()
	{
		$this->assertEmpty($this->object->getDirtyFields());
		$this->object->{$this->key} = $this->value_string;
		$this->assertNotEmpty($this->object->getDirtyFields());
	}

	public function testGetData()
	{
		$this->assertEmpty($this->object->getData());
		$this->object->{$this->key} = $this->value_string;
		$this->assertNotEmpty($this->object->getData());
	}

	public function testSetField()
	{
		$this->assertEmpty($this->object->getDirtyFields());
		$this->assertEmpty($this->object->getData());
		$this->object->setField($this->key, $this->value_string);
		$this->assertEmpty($this->object->getDirtyFields());
		$this->assertNotEmpty($this->object->getData());
	}

	public function testDelete()
	{
		$deleted = clone $this->parent;
		$row = $this->parent->orderByDesc($this->parent->getIDColumn())->fetchOne();
		$id = $row->getID();
		$row->delete();
		$this->assertFalse($deleted->fetchOne($id));
	}

	public function testResetDirty()
	{
		$this->assertEmpty($this->object->getDirtyFields());
		$this->object->{$this->key} = $this->value_string;
		$this->assertNotEmpty($this->object->getDirtyFields());
		$this->object->resetDirty();
		$this->assertEmpty($this->object->getDirtyFields());
	}

	public function testIsNew()
	{
		$this->assertTrue($this->object->isNew());
		$this->object->{$this->key} = $this->value_string;
		$this->object->save();
		$this->assertFalse($this->object->isNew());
		$this->assertFalse($this->parent->fetchOne()->isNew());
	}

	public function testGetID()
	{
		$this->assertNull($this->object->getID());
		$row = $this->parent->fetchOne();
		$this->assertEquals($row->ID, $row->getID());
	}

	public function testUpdateNewStatus()
	{
		$this->assertTrue($this->object->isNew());
		$this->object->updateNewStatus();
		$this->assertTrue(!$this->object->isNew());
	}

	public function testAsArray()
	{
		$row = $this->parent->fetchOne();
		$this->assertEquals(2, count($row->asArray()));
		$this->assertEquals(1, count($row->asArray($this->key)));
		$this->assertTrue(array_key_exists($this->key, $row->asArray($this->key)));
	}

	public function testForceAllDirty()
	{
		$row = $this->parent->fetchOne();
		$this->assertEmpty($row->getDirtyFields());
		$row->set($this->key, $this->value_number);
		$this->assertNotEmpty($row->getDirtyFields());
		$this->assertEquals(1, count($row->getDirtyFields()));
		$row->forceAllDirty();
		$this->assertEquals(count($row->getData()), count($row->getDirtyFields()));
	}
}
