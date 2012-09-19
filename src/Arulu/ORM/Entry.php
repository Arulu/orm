<?php

/*
 * Copyright (c) 2012 Arulu Inversiones SL
 * Todos los derechos reservados
 */

namespace Arulu\ORM;

/**
 * Database entry for ORM
 *
 * @package ORM
 *
 * @author Alberto <albertofem@gmail.com>
 * @author Noel <noelgarciamolina@gmail.com>
 */
class Entry
{
	/**
	 * Entry data
	 */
	private $_data;

	/**
	 * Parent ORM instance
	 * @var ORM
	 */
	private $_parent;

	/**
	 * Tells if we have a new entry or not
	 */
	private $_newEntry = false;

	/**
	 * Fields that have been modified during the lifetime of the object
	 */
	private $_dirtyFields = array();

	/**
	 * Name of primary key field
	 */
	private $_idField;

	/**
	 * Creates new database entry
	 *
	 * @param array $data The database fields to hydrate this entry
	 * @param ORM $parent ORM link
	 * @param type $is_new indicates If this is a new database entry or not
	 */
	public function __construct($data, ORM $parent, $is_new = false)
	{
		$this->_data = $data;
		$this->_parent = $parent;

		$this->_newEntry = $is_new;
		$this->_idField = $parent->getIDColumn();

		//for new entry every field is dirty
		if($is_new)
		{
			$this->forceAllDirty();
		}
	}

	/**
	 * PHP implementation of mysql NOW() function
	 *
	 * @return string mysql TIMESTAMP formatted time()
	 */
	public static function now()
	{
		return date("Y-m-d H:i:s");
	}

	/**
	 * Set a property to a particular value on this object.
	 * Flags that property as 'dirty' so it will be saved to the
	 * database when save() is called.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		$this->_data[$key] = $value;
		$this->_dirtyFields[$key] = $value;
	}

	/**
	 * Unsets dirty flag of passed column
	 *
	 * @param string $key
	 */
	public function unsetDirty($key)
	{
		unset($this->_dirtyFields[$key]);
	}

	/**
	 * Check whether the given field has been changed
	 * since this object was saved.
	 *
	 * @param string $key
	 *
	 * @return bool If there are dirty fields
	 */
	public function isDirty($key)
	{
		return isset($this->_dirtyFields[$key]);
	}

	/**
	 * Implementation of magic method __get
	 *
	 * @param string $key
	 *
	 * @return mixed $key Value requested
	 */
	public function __get($key)
	{
		return $this->_data[$key];
	}

	/**
	 * Implementation of magic method __set
	 *
	 * @param string $key
	 */
	public function __set($key, $value)
	{
		$this->set($key, $value);
	}


	/**
	 * Implementation of magic method __isset
	 *
	 * @param string $key
	 *
	 * @return bool exists $key in $this->_data or not
	 */
	public function __isset($key)
	{
		return isset($this->_data[$key]);
	}

	/**
	 * Saves entry into database
	 *
	 * @param bool $force If it's true mades a REPLACE query, INSERT otherwise
	 *
	 * @return bool If the request succeded
	 */
	public function save($force=false)
	{
		return $this->_parent->save($this, $force);
	}

	/**
	 * Gets changed unsaved row fields
	 *
	 * @return array Associative array of dirty fields
	 */
	public function getDirtyFields()
	{
		return $this->_dirtyFields;
	}

	/**
	 * Gets row fields
	 *
	 * @return array Associative array of data
	 */
	public function getData()
	{
		return $this->_data;
	}

	/**
	 * Set field value only in _data array
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function setField($key, $value)
	{
		$this->_data[$key] = $value;
	}

	/**
	 * Delete entry from database
	 *
	 * @return bool If request succeded or not
	 */
	public function delete()
	{
		return $this->_parent->delete($this);
	}

	/**
	 * Emptys dirtyfields array
	 */
	public function resetDirty()
	{
		$this->_dirtyFields = array();
	}

	/**
	 * Check if entry is new or not
	 *
	 * @return bool Entry is new or not
	 */
	public function isNew()
	{
		return $this->_newEntry;
	}

	/**
	 * Gets primary key value if it is known, null otherwise
	 *
	 * @return mixed value of entry primary key
	 */
	public function getID()
	{
		return isset($this->_data[$this->_idField]) ? $this->_data[$this->_idField] : null;
	}

	/**
	 * Switch newEntry property
	 */
	public function updateNewStatus()
	{
		$this->_newEntry = !$this->_newEntry;
	}


	/**
	 * Return the raw data wrapped by this ORM
	 * instance as an associative array. Column
	 * names may optionally be supplied as arguments,
	 * if so, only those keys will be returned.
	 *
	 * @param string $fieldName1, $fieldName2, ... Fields to fetch
	 *
	 * @return array associative array with passed keys
	 */
	public function asArray()
	{
		if(func_num_args() === 0)
		{
			return $this->_data;
		}

		$args = func_get_args();

		return array_intersect_key($this->_data, array_flip($args));
	}

	/**
	 * Force the ORM to flag all the fields in the $data array
	 * as "dirty" and therefore update them when save() is called.
	 *
	 * @return ORM_Entry This entry instance
	 */
	public function forceAllDirty()
	{
		$this->_dirtyFields = $this->_data;

		return $this;
	}
}
