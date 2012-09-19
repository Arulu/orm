<?php

/*
 * Copyright (c) 2012 Arulu Inversiones SL
 * Todos los derechos reservados
 */

use Arulu\ORM\ORM;
use Arulu\ORM\Entry;

/**
 * Test class for ORM.
 *
 * @package ORM
 *
 * @author noel <noelgarciamolina@gmail.com>
 */
class ORMTest extends PHPUnit_Framework_TestCase
{

	protected $object, $row, $value_string, $value_number, $key;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->object = new ORM('mysql:host=localhost;dbname=stupidorm_test', 'root', 'root');
		$this->row = $this->object->init("stupidorm_apellidos")->create();

		$this->value_string = 'testValue';
		$this->value_number = 33;
		$this->key = 'Apellido';
	}

	public function testSetConfig()
	{
		$object = new ORM(sprintf('mysql:host=%s;dbname=%s', SERVER, DB), USER, 'fakepass');
		$object->setConfig('password', PASS);
		try
		{
			$object->init("tabla");
		}
		catch(Exception $exc)
		{
			$this->assertTrue(false);
		}
	}

	public function testForDatabase()
	{
		$object = new ORM(sprintf('mysql:host=%s;dbname=%s', SERVER, 'fakeDB'), USER, PASS);
		$object->forDatabase(DB);
		try
		{
			$object->init("tabla");
		}
		catch(Exception $exc)
		{
			$this->assertTrue(false);
		}
	}

	public function testInTable()
	{
		$obj = $this->object->inTable("stupidorm_nombres", "fakeKey");
		$this->assertEquals("ID", $this->object->getIDColumn());
		$this->assertEquals("fakeKey", $obj->getIDColumn());

		$row2 = $obj->setPrimaryKey("ID")->fetchOne();
		$row = $this->object->fetchOne();

		$this->assertArrayHasKey("Nombre", $row2->getData());
		$this->assertArrayNotHasKey("Nombre", $row->getData());
	}

	public function testInit()
	{
		$this->object->init("stupidorm_nombres", "fake_key");
		$this->assertEquals("fake_key", $this->object->getIDColumn());
		$this->object->setPrimaryKey('ID');
		$this->assertInstanceOf('Arulu\ORM\Entry', $this->object->fetchOne(1));
	}

	public function testGetDb()
	{
		$this->assertInstanceOf('PDO', $this->object->getDb());
	}

	public function testGetLastQuery()
	{
		$obj = clone $this->object;
		$this->object->count();
		$this->assertNull($this->object->getLastQuery());
		$obj->setConfig("logging", true);
		$obj->fetchAll();
		$this->assertEquals("SELECT * FROM stupidorm_apellidos", $obj->getLastQuery());
		$obj->clearLog();
		$obj->setConfig("logging", false);
	}

	public function testGetQueryLog()
	{
		$obj = clone $this->object;
		$this->assertCount(0, $this->object->getQueryLog());
		$this->object->count();
		$this->assertCount(0, $this->object->getQueryLog());
		$obj->setConfig("logging", true);
		$obj->inTable("stupidorm_apellidos")->count();
		$this->assertCount(1, $obj->getQueryLog());
		$obj->inTable("stupidorm_apellidos")->count();
		$this->assertCount(2, $obj->getQueryLog());
	}

	public function testClearLog()
	{
		$this->object->setConfig("logging", true);
		$this->assertGreaterThan(0, $this->object->getQueryLog());
		$this->object->clearLog();
		$this->assertCount(0, $this->object->getQueryLog());
	}

	public function testCreate()
	{
		$this->assertInstanceOf('Arulu\ORM\Entry', $this->object->create());
	}

	public function testSetPrimaryKey()
	{
		$this->object->setPrimaryKey('fake_column');
		try
		{
			$this->object->fetchOne(1);
		}
		catch(Exception $exc)
		{
			return true;
		}
		$this->assertTrue(false);
	}

	public function testFetchOne()
	{
		$obj = clone $this->object;
		$this->assertInstanceOf('Arulu\ORM\Entry', $this->object->fetchOne());
		$this->assertFalse($obj->fetchOne(2000));
	}

	public function testFetchOneForce()
	{
		$obj = clone $this->object;
		$this->assertInstanceOf('Arulu\ORM\Entry', $this->object->fetchOneForce());
		$this->assertInstanceOf('Arulu\ORM\Entry', $obj->fetchOneForce(2000));
	}

	public function testFetchAll()
	{
		$rows = $this->object->fetchAll();
		$this->assertTrue(is_array($rows));
		$this->assertInstanceOf('Arulu\ORM\Entry', $rows[0]);
	}

	public function testCount()
	{
		$count = $this->object->where_id_is(1)->count();
		$this->assertTrue(is_integer($count));
		$this->assertEquals(1, $count);
	}

	public function testRawQuery()
	{
		$obj = clone $this->object;
		$id2 = $obj->rawQuery('select MAX(ID) as max from stupidorm_apellidos')->fetchOne()->max;
		$id1 = $this->object->select_expr('MAX(ID)', 'max')->fetchOne()->max;
		$this->assertEquals($id2, $id1);
	}

	public function testTableAlias()
	{
		$obj = clone $this->object;
		$this->object->tableAlias('t');
		$this->object->select('t.ID')->fetchAll();

		$obj->tableAlias('dt');
		try
		{
			$obj->select('t.ID')->fetchAll();
		}
		catch(Exception $exc)
		{
			return true;
		}
		$this->assertTrue(false);
	}

	public function testSelect()
	{
		$obj = clone $this->object;
		$this->assertCount(1, $this->object->select('ID')->fetchOne()->getData());
		$this->assertCount(2, $obj->fetchOne()->getData());
	}

	public function testSelect_expr()
	{
		$id1 = $this->object->select_expr('MIN(ID)', 'max')->fetchOne()->max;
		$this->assertEquals(1, $id1);
	}

	public function testDistinct()
	{
		//inserting 2 rows with same value
		$this->row->set($this->key, $this->value_string);
		$row = clone $this->row;
		$this->row->save();
		$row->save();
		//then using distinct result should be 1
		$results = $this->object
				->select($this->key)
				->distinct()
				->where($this->key, $this->value_string)
				->fetchAll();
		$this->assertCount(1, $results);
	}

	public function testJoin()
	{
		$obj = clone $this->object;
		$rows = $this->object->join("stupidorm_nombres", 'stupidorm_apellidos_ID = stupidorm_apellidos.ID')->fetchAll();

		//stupidorm_nombres has 4 rows, so query must return 4 rows because of the join
		$this->assertCount(4, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());

		//same asserts, distinct join params
		$rows = $obj->join("stupidorm_nombres", array('mitabla.stupidorm_apellidos_ID', '=', 'stupidorm_apellidos.ID'), 'mitabla')->fetchAll();

		//stupidorm_nombres has 4 rows, so query must return 4 rows because of the join
		$this->assertCount(4, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());
	}

	public function testInnerJoin()
	{
		$obj = clone $this->object;
		$rows = $this->object->innerJoin("stupidorm_nombres", 'stupidorm_apellidos_ID = stupidorm_apellidos.ID')->fetchAll();

		//stupidorm_nombres has 4 rows, so query must return 4 rows because of the join
		$this->assertCount(4, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());

		//same asserts, distinct join params
		$rows = $obj->innerJoin("stupidorm_nombres", array('mitabla.stupidorm_apellidos_ID', '=', 'stupidorm_apellidos.ID'), 'mitabla')->fetchAll();

		//stupidorm_nombres has 4 rows, so query must return 4 rows because of the join
		$this->assertCount(4, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());
	}

	public function testLeftOuterJoin()
	{
		$obj = clone $this->object;
		$obj2 = clone $this->object;
		$count = $obj2->count() + 2;

		$rows = $this->object->leftOuterJoin("stupidorm_nombres", 'stupidorm_apellidos_ID = stupidorm_apellidos.ID')->fetchAll();

		//stupidorm_nombres has 4 rows and relations with 2 different apellidos rows', so query must return count(apellidos)+2 rows because of the join
		$this->assertCount($count, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());

		//same asserts, distinct join params
		$rows = $obj->leftOuterJoin("stupidorm_nombres", array('mitabla.stupidorm_apellidos_ID', '=', 'stupidorm_apellidos.ID'), 'mitabla')->fetchAll();

		//stupidorm_nombres has 4 rows and relations with 2 different apellidos rows', so query must return count(apellidos)+2 rows because of the join
		$this->assertCount($count, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());
	}

	public function testRightOuterJoin()
	{
		$obj = clone $this->object;
		$obj2 = clone $this->object;
		$count = 4;

		$rows = $this->object->rightOuterJoin("stupidorm_nombres", 'stupidorm_apellidos_ID = stupidorm_apellidos.ID')->fetchAll();

		//stupidorm_nombres has 4 rows, so query must return 4 rows because of the join
		$this->assertCount($count, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());

		//same asserts, distinct join params
		$rows = $obj->rightOuterJoin("stupidorm_nombres", array('mitabla.stupidorm_apellidos_ID', '=', 'stupidorm_apellidos.ID'), 'mitabla')->fetchAll();

		//stupidorm_nombres has 4 rows, so query must return 4 rows because of the join
		$this->assertCount($count, $rows);
		$this->assertArrayHasKey("Nombre", $rows[0]->getData());
	}

	public function testFullOuterJoin()
	{
		//full outer join is not supported by mysql, so it must crash
		try
		{
			$rows = $this->object->fullOuterJoin("stupidorm_nombres", 'stupidorm_apellidos_ID = stupidorm_apellidos.ID')->fetchAll();
		}
		catch(Exception $exc)
		{
			return true;
		}
		$this->assertTrue(false);
	}

	public function testWhere()
	{
		//where is an alias of where_equal
		$this->testWhere_equal();
	}

	public function testWhere_equal()
	{
		$this->assertCount(1, $this->object->where_equal('ID', 1)->fetchAll());
	}

	public function testWhere_not_equal()
	{
		$this->assertCount(3, $this->object->init("stupidorm_nombres")->where_not_equal('Nombre', 'pedro')->fetchAll());
	}

	public function testWhere_id_is()
	{
		$this->assertCount(1, $this->object->where_id_is(1)->fetchAll());
	}

	public function testWhereLike()
	{
		$obj = clone $this->object;
		$obj2 = clone $this->object;
		$this->assertCount(1, $this->object->init("stupidorm_nombres")->whereLike('Nombre', 'pedro')->fetchAll());
		$this->assertCount(2, $obj->init("stupidorm_nombres")->whereLike('Nombre', 'p%')->fetchAll());
		$this->assertCount(2, $obj2->init("stupidorm_nombres")->whereLike('Nombre', '%o')->fetchAll());
	}

	public function testWhereNotlike()
	{
		$obj = clone $this->object;
		$obj2 = clone $this->object;
		$this->assertCount(3, $this->object->init("stupidorm_nombres")->whereNotLike('Nombre', 'pedro')->fetchAll());
		$this->assertCount(2, $obj->init("stupidorm_nombres")->whereNotLike('Nombre', 'p%')->fetchAll());
		$this->assertCount(2, $obj2->init("stupidorm_nombres")->whereNotLike('Nombre', '%o')->fetchAll());
	}

	public function testWhere_gt()
	{
		$this->assertCount(2, $this->object->init("stupidorm_nombres")->where_gt('ID', 2)->fetchAll());
	}

	public function testWhere_lt()
	{
		$this->assertCount(1, $this->object->init("stupidorm_nombres")->where_lt('ID', 2)->fetchAll());
	}

	public function testWhere_gte()
	{
		$this->assertCount(3, $this->object->init("stupidorm_nombres")->where_gte('ID', 2)->fetchAll());
	}

	public function testWhere_lte()
	{
		$this->assertCount(2, $this->object->init("stupidorm_nombres")->where_lte('ID', 2)->fetchAll());
	}

	public function testWhereIN()
	{
		$this->assertCount(2, $this->object->init("stupidorm_nombres")->whereIN('Nombre', array('Pablo', 'Pedro'))->fetchAll());
	}

	public function testWhereNotIN()
	{
		$this->assertCount(2, $this->object->init("stupidorm_nombres")->whereNotIN('Nombre', array('Pablo', 'Pedro'))->fetchAll());
	}

	public function testWhereNull()
	{
		$this->assertCount(0, $this->object->init("stupidorm_nombres")->whereNull('Nombre')->fetchAll());
	}

	public function testWhereNotNull()
	{
		$this->assertCount(4, $this->object->init("stupidorm_nombres")->whereNotNull('Nombre')->fetchAll());
	}

	public function testWhereRaw()
	{
		$obj = clone $this->object;
		$this->assertCount(1, $this->object->init("stupidorm_nombres")->whereRaw('Nombre="pedro"')->fetchAll());
		$this->assertCount(2, $obj->init("stupidorm_nombres")->whereRaw('Nombre like "p%"')->fetchAll());
	}

	public function testLimit()
	{
		$rows = $this->object->init("stupidorm_nombres")->limit(1)->fetchAll();
		$this->assertCount(1, $rows);
		$this->assertEquals(1, $rows[0]->ID);
	}

	public function testOffset()
	{
		//offset=3 must return only last row
		$rows = $this->object->init("stupidorm_nombres")->limit(200)->offset(3)->fetchAll();
		$this->assertCount(1, $rows);
		$this->assertEquals(4, $rows[0]->ID);
	}

	public function testOrderByDesc()
	{
		$row = $this->object->init("stupidorm_nombres")->orderByDesc('ID')->fetchOne();
		$this->assertEquals(4, $row->ID);
	}

	public function testOrderByAsc()
	{
		$row = $this->object->init("stupidorm_nombres")->orderByAsc('Nombre')->fetchOne();
		$this->assertEquals(3, $row->ID);
	}

	public function testGroupBy()
	{
		$rows = $this->object->Join('stupidorm_nombres', 'stupidorm_apellidos_ID=stupidorm_apellidos.ID')->groupBy('Apellido')->fetchAll();
		$this->assertEquals(2, count($rows));
	}

	public function testClearCache()
	{
		$this->object->setConfig("caching", true);
		$this->object->fetchAll();
		$this->object->clearCache();
	}

	public function testSave()
	{
		$row = $this->object->init("stupidorm_nombres")->create();
		$row->Nombre = "Carmen";
		$row->save();
		$this->assertEquals(1, $this->object->where("Nombre", "Carmen")->count());
	}

	public function testDelete()
	{
		$this->object->init("stupidorm_nombres");
		$row=new Entry(array("Nombre"=>"testDelete"), $this->object->inTable("stupidorm_nombres"), true);
		$row2=new Entry(array("Nombre"=>"testDelete2"), $this->object->inTable("stupidorm_nombres"), true);
		$row->save();
		$row2->save();
		$row->delete();
		$deleteResult=$this->object->inTable("stupidorm_nombres")->delete($row);
		$deleteResult2=$this->object->inTable("stupidorm_nombres")->where("Nombre","testDelete2")->delete();
		$this->assertEquals(true, $deleteResult);
		$this->assertEquals(true, $deleteResult2);
		$this->assertEquals(0, $this->object->inTable("stupidorm_nombres")->where("Nombre", "testDelete")->count());
		$this->assertEquals(0, $this->object->inTable("stupidorm_nombres")->where("Nombre", "testDelete2")->count());

		//test with previous fetching
		$row3=new Entry(array("Nombre"=>"testDelete3"), $this->object->inTable("stupidorm_nombres"), true);
		$row3->save();

		$this->object->inTable("stupidorm_nombres")->fetchOne($row3->getID())->delete();

		//test with previous with select. It must crash
		$row3=new Entry(array("Nombre"=>"testDelete3"), $this->object->inTable("stupidorm_nombres"), true);
		$row3->save();
		try
		{
			$this->object->inTable("stupidorm_nombres")->select("Nombre")->fetchOne($row3->getID())->delete();
		}
		catch(Exception $exc)
		{
			return true;
		}
		$this->assertTrue(false);
	}

	public function testDeleteWhereIdIs()
	{
		$obj=$this->object->inTable("stupidorm_apellidos");
		$deleteResult=$obj->deleteWhereIdIs(1);
		$this->assertEquals(true, $deleteResult);
		$this->assertEquals(false, $this->object->inTable("stupidorm_apellidos")->fetchOne(1));
	}

	public function testGetIDColumn()
	{
		$this->assertEquals("ID", $this->object->getIDColumn());
	}

	public function testId()
	{
		$this->assertEquals('qwer', $this->object->id(array("ID" => "qwer", "ddd" => "jjj")));
	}
}
