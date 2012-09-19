<?php

/*
 * ORM, PHP minified ORM implementation, based on Idiorm
 *
 * http://github.com/j4mie/idiorm/
 *
 * A single-class super-simple database abstraction layer for PHP.
 * Provides (nearly) zero-configuration object-relational mapping
 * and a fluent interface for building basic, commonly-used queries.
 *
 * BSD Licensed
 *
 * Copyright (c) 2010, Jamie Matthews
 * Copyright (c) 2012 Arulu Inversiones SL
 * All rights reserved
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE
 */

namespace Arulu\ORM;

use Arulu\ORM\Entry;
use Arulu\ORM\Exception\MainException;

/**
 * ORM class
 *
 * A single-class super-simple database abstraction layer for PHP.
 * Provides (nearly) zero-configuration object-relational mapping
 * and a fluent interface for building basic, commonly-used queries.
 *
 * @author Jamie Matthews
 * @author Alberto <albertofem@gmail.com>
 * @author Noel <noelgarciamolina@gmail.com>
 */
class ORM
{
	/**
	 * Class constants
	 */
	private $WHERE_FRAGMENT = 0;
	private $WHERE_VALUES = 1;

	/**
	 * Configuration array
	 */
	private $_config = array(
		'connection_string' => 'sqlite::memory:',
		'id_column' => 'ID',
		'id_column_overrides' => array(),
		'error_mode' => \PDO::ERRMODE_EXCEPTION,
		'username' => null,
		'password' => null,
		'driver_options' => null,
		'identifier_quote_character' => null, // if this is null, will be autodetected
		'logging' => false,
		'caching' => false,
	);

	/**
	 * Instance of \PDO class
	 * @var \PDO
	 */
	private $_db;

	/**
	 * Last query run, only populated if logging is enabled
	 */
	private static $lastQuery;

	/**
	 * Log of all queries run, only populated if logging is enabled
	 */
	private static $_queryLog = array();

	/**
	 * Query cache, only used if query caching is enabled
	 */
	private static $_queryCache = array();

	/**
	 * Table name/alias where the object is currently working on
	 */
	private $_tableName;
	private $_tableAlias = null;

	/**
	 * What columns to fetch from the result, * by default
	 */
	private $_resultColumns = array('*');

	// Are we using the default result column or have these been manually changed?
	private $_usingDefaultResultColumns = true;

	/**
	 * Stores data to write temporally
	 */
	private $_values = array();

	/*
	 * Rest of private members, stores misc data across
	 * the class, depending on which type of query we are doing
	 */
	private $_joinSources = array();
	private $_distinct = false;

	private $_isRawQuery = false;
	private $_rawQuery = '';
	private $_rawParameters = array();

	private $_whereConditions = array();
	private $_limit = null;
	private $_offset = null;
	private $_order_by = array();
	private $_groupBy = array();

	private $_instanceIDColumn = null;

	private $_finalState = false;

	/**
	 * Construct
	 *
	 * Receives a connection string in \PDO format
	 *
	 * @param string $connection_string Connection string
	 */
	public function __construct($connection_string, $username, $password)
	{
		$this->_config['connection_string'] = $connection_string;
		$this->_config['username'] = $username;
		$this->_config['password'] = $password;
	}

	/**
	 * It will change value of $option in config. Available options are:
	 * connection_string, id_column, id_column_overrides, error_mode, username, password, driver_options,
	 * identifier_quote_character, logging, caching
	 *
	 * @param string $option Configuration key
	 * @param mixed $value Value to set key to
	 */
	public function setConfig($option, $value = '')
	{
		$this->_config[$option] = $value;

		return $this;
	}

	/**
	 * Change database schema
	 *
	 * @param string $dbname Database name
	 *
	 * @return ORM ORM current class instance
	 */
	public function forDatabase($database)
	{
		// replace dbname= in connection string
		$this->_config['connection_string'] = preg_replace(
			'/dbname\=(.*)$/',
			'dbname=' .$database. ';',
			$this->_config['connection_string']
		);
		return $this;
	}

	/**
	 * Clones current instance and sets working table on new instance
	 *
	 * @param string $table_name Name of table
	 * @param string $id_key ID PRIMARY field name
	 *
	 * @return ORM current class instance CLON
	 */
	public function inTable($table_name, $id_key = null)
	{
		$new_instance = clone $this;
		$new_instance->init($table_name, $id_key);

		return $new_instance;
	}

	/**
	 * Select a table to work in
	 *
	 * Sets the current working table, and adiccionally
	 * the primary key (defaults to 'id') of the table
	 * It also tries to open the database connection
	 *
	 * @param string $table_name Name of table
	 * @param string $id_key ID PRIMARY field name
	 *
	 * @throws MainException When database connection failed
	 *
	 * @return ORM current class instance
	 */
	public function init($table_name, $id_key = null)
	{
		$this->_tableName = $table_name;

		if(!is_null($id_key))
		{
			$this->_instanceIDColumn = $id_key;
		}

		$this->_dbConnection();
		return $this;
	}

	/**
	 * Init database conection
	 *
	 * This method will try to connect to the database,
	 * if the connection have been already happened
	 * it will return the _db instance
	 *
	 * @throws MainException When it was impossible to open the database conection
	 */
	private function _dbConnection()
	{
		if(!is_object($this->_db))
		{
			$connection_string = $this->_config['connection_string'];
			$username = $this->_config['username'];
			$password = $this->_config['password'];
			$driver_options = $this->_config['driver_options'];

			try
			{
				$this->_db = new \PDO($connection_string, $username, $password);
			}
			catch(Exception $ex)
			{
				throw new MainException($ex->getMessage());
			}

			$this->_db->setAttribute(\PDO::ATTR_ERRMODE, $this->_config['error_mode']);
			$this->_db->exec("SET NAMES utf8;");
		}
	}

	/**
	 * Get \PDO instance
	 *
	 * Returns the \PDO instance used by the the ORM to communicate with
	 * the database. This can be called if any low-level DB access is
	 * required outside the class.
	 *
	 * @return \PDO \PDO class instance
	 *
	 * @throws MainException When connection failed
	 */
	public function getDb()
	{
		// just in case this is called before formal connection
		$this->_dbConnection();

		return $this->_db;
	}

	/**
	 * Gets last query
	 *
	 * Only works if the 'logging' config option is
	 * set to true. Otherwise this will return null.
	 *
	 * @return string Last executed query
	 */
	public static function getLastQuery()
	{
		return self::$lastQuery;
	}

	/**
	 * Get query log
	 *
	 * Get an array containing all the queries run up to
	 * now. Only works if the 'logging' config option is
	 * set to true. Otherwise returned array will be empty.
	 *
	 * @return array Array of strings with executed queries
	 */
	public static function getQueryLog()
	{
		return self::$_queryLog;
	}

	/**
	 * Create new entry in a table
	 *
	 * Returns a new instance of Entry, which
	 * can be used as an ActiveRecord class. Additionally
	 * an array of initial data can be passed to init
	 * the new entry with that data
	 *
	 * @param array $data Init data to pass to the new entry object
	 *
	 * @return Entry Instance with the entry
	 */
	public function create($data = null)
	{
		return new Entry($data, $this, true);
	}

	/**
	 * Sets table's primary key
	 *
	 * Specify the ID column to use for subsequent
	 * operations in the table. Used in SELECT, UPDATE and
	 * INSERT statements as well other operations
	 *
	 * @param string $id_column Field primary key name
	 *
	 * @return ORM ORM current instance
	 */
	public function setPrimaryKey($id_column)
	{
		$this->_instanceIDColumn = $id_column;
		return $this;
	}

	/**
	 * Tell the ORM that you are expecting a single result
	 * back from your query, and execute it. Will return
	 * a single instance of the stupidEntry class, or false if no
	 * rows were returned.
	 * As a shortcut, you may supply an ID as a parameter
	 * to this method. This will perform a primary key
	 * lookup on the table.
	 *
	 * @param mixed $id primary key value to search for
	 *
	 * @return mixed Entry or FALSE if the query has no results
	 *
	 * @throws MainException When it was impossible to run the query because it's already ran
	 */
	public function fetchOne($id = null)
	{
		if($this->_finalState)
			throw new MainException("Cannot execute a fetch when already fetched/counted an statement");

		$this->_finalState = true;

		if(!is_null($id))
		{
			$this->where_id_is($id);
		}

		$this->limit(1);
		$rows = $this->_run();

		if(empty($rows))
		{
			return false;
		}

		return $this->_createResultInstance($rows[0]);
	}

	/**
	 * This function calls fetchOne method and if it returns false creates empty row and returns it
	 *
	 * @param mixed $id
	 *
	 * @return Entry found row or empty one
	 */
	public function fetchOneForce($id = null)
	{
		$object = $this->fetchOne($id);

		if(!$object)
			return $this->create();

		return $object;
	}

	/**
	 * Tell the ORM that you are expecting multiple results
	 * from your query, and execute it. Will return an array
	 * of instances of the Entry class, or an empty array if
	 * no rows were returned.
	 *
	 * @return Entry Array of Entry
	 */
	public function fetchAll()
	{
		if($this->_finalState)
			throw new MainException("Cannot execute a fetch when already fetched/counted an statement");

		$this->_finalState = true;

		$rows = $this->_run();
		return array_map(array($this, '_createResultInstance'), $rows);
	}

	/**
	 * Add a query to the internal query log.
	 *
	 * This works by manually binding the parameters to the query - the
	 * query isn't executed like this (\PDO normally passes the query and
	 * parameters to the database which takes care of the binding) but
	 * doing it this way makes the logged queries more readable.  Only
	 * works if the 'logging' config option is set to true.
	 *
	 * @param string $query Query string
	 * @param array $parameters
	 *
	 * @return bool Status of the operation
	 */
	private function _logQuery($query, $parameters)
	{
		if(!$this->_config['logging'])
			return false;

		if(count($parameters) > 0)
		{
			// escape the parameters
			$parameters = array_map(array($this->_db, 'quote'), $parameters);

			// replace placeholders in the query for vsprintf
			$query = str_replace("?", "%s", $query);

			// replace the question marks in the query with the parameters
			$bound_query = vsprintf($query, $parameters);
		}
		else
		{
			$bound_query = $query;
		}

		self::$lastQuery = $bound_query;
		array_push(self::$_queryLog, $bound_query);

		return true;
	}

	/**
	 * Create Entry from data array
	 *
	 * @param array $data Data to pass to the entries constructors
	 *
	 * @return Entry built from $data
	 */
	private function _createResultInstance($data = array())
	{
		return new Entry($data, $this);
	}

	/**
	 * Tell the ORM that you wish to execute a COUNT query.
	 * Will return an integer representing the number of
	 * rows returned.
	 *
	 * @return int Total number of rows
	 */
	public function count()
	{
		$this->select_expr('COUNT(*)', 'count');
		$result = $this->fetchOne();

		return ($result !== false && isset($result->count)) ?
			(empty($this->_groupBy) ? (int) $result->count : $result->asArray())
		: 0;
	}

	/**
	 * Perform a raw query. The query should contain placeholders,
	 * in either named or question mark style, and the parameters
	 * should be an array of values which will be bound to the
	 * placeholders in the query. If this method is called, all
	 * other query building methods will be ignored.
	 *
	 * @param string $query
	 * @param array $parameters
	 *
	 * @return ORM ORM current instance
	 */
	public function rawQuery($query, $parameters = array())
	{
		$this->_isRawQuery = true;
		$this->_rawQuery = $query;
		$this->_rawParameters = $parameters;

		return $this;
	}

	/**
	 * Add an alias for the main table to be used in SELECT queries
	 *
	 * @param string $alias Alias to add
	 *
	 * @return ORM ORM current instance
	 */
	public function tableAlias($alias)
	{
		$this->_tableAlias = $alias;

		return $this;
	}

	/**
	 * Internal method to add an unquoted expression to the set
	 * of columns returned by the SELECT query. The second optional
	 * argument is the alias to return the expression as.
	 *
	 * @param string $expr Expression to add
	 * @param string $alias Alias
	 *
	 * @return ORM ORM current instance
	 */
	private function _addResultColumn($expr, $alias = null)
	{
		if(!is_null($alias))
		{
			$expr .= " AS " . $this->_quoteIdentifier($alias);
		}

		if($this->_usingDefaultResultColumns)
		{
			$this->_resultColumns = array($expr);
			$this->_usingDefaultResultColumns = false;
		}
		else
		{
			$this->_resultColumns[] = $expr;
		}

		return $this;
	}

	/**
	 * Add a column to the list of columns returned by the SELECT
	 * query. This defaults to '*'. The second optional argument is
	 * the alias to return the column as.
	 *
	 * @param string $column Column to add
	 * @param string $alias Column alias
	 *
	 * @return ORM ORM current instance
	 */
	public function select($column, $alias = null)
	{
		$column = $this->_quoteIdentifier($column);
		return $this->_addResultColumn($column, $alias);
	}

	/**
	 * Add an unquoted expression to the list of columns returned
	 * by the SELECT query. The second optional argument is
	 * the alias to return the column as.
	 *
	 * @param string $expr Expression to add
	 * @param string $alias Expression alias
	 *
	 * @return ORM ORM current instance
	 */
	public function select_expr($expr, $alias = null)
	{
		return $this->_addResultColumn($expr, $alias);
	}

	/**
	 * Add a DISTINCT keyword before the list of
	 * columns in the SELECT query
	 *
	 * @return ORM ORM current instance
	 */
	public function distinct()
	{
		$this->_distinct = true;
		return $this;
	}

	/**
	 * Internal method to add a JOIN source to the query.
	 *
	 * @param string $join should be one of INNER, LEFT OUTER, CROSS etc -
	 * 		this will be prepended to JOIN.
	 * @param string $table should be the name of the table to join to.
	 * @param mixed $constraint may be either a string or an array
	 * 		with three elements. If it is a string, it will be compiled
	 * 	into the query as-is, with no escaping. The recommended way
	 * 	to supply the constraint is as an array with three elements:
	 * 	<pre>
	 * 	first_column, operator, second_column
	 * 	</pre>
	 *
	 * 	Example:
	 * 	<pre>array('user.id', '=', 'profile.user_id')</pre>
	 * 	will compile to
	 * 	<pre>ON `user`.`id` = `profile`.`user_id`</pre>
	 *
	 * @param string $tableAlias Specifies an alias for the joined table.
	 *
	 * @return ORM ORM current instance
	 */
	private function _addJoinSource($join_operator, $table, $constraint, $tableAlias = null)
	{
		$join_operator = trim("{$join_operator} JOIN");

		$table = $this->_quoteIdentifier($table);

		// Add table alias if present
		if(!is_null($tableAlias))
		{
			$tableAlias = $this->_quoteIdentifier($tableAlias);
			$table .= " {$tableAlias}";
		}

		// Build the constraint
		if(is_array($constraint))
		{
			list($first_column, $operator, $second_column) = $constraint;
			$first_column = $this->_quoteIdentifier($first_column);
			$second_column = $this->_quoteIdentifier($second_column);
			$constraint = "{$first_column} {$operator} {$second_column}";
		}

		$this->_joinSources[] = "{$join_operator} {$table} ON {$constraint}";
		return $this;
	}

	/**
	 * Shortcut of _addjoinsource.
	 * Add a simple JOIN source to the query
	 *
	 * @param string $table
	 * @param mixed $constraint may be either a string or an
	 * 		array with three elements. If it is a string, it will
	 * 		be compiled into the query as-is, with no escaping.
	 *
	 * @param string $tableAlias
	 *
	 * @return ORM ORM current instance
	 */
	public function join($table, $constraint, $tableAlias = null)
	{
		return $this->_addJoinSource("", $table, $constraint, $tableAlias);
	}

	/**
	 * Shortcut of _addjoinsource.
	 * Add a simple INNER JOIN source to the query
	 *
	 * @param string $table
	 * @param mixed $constraint may be either a string or an array
	 * 		with three elements. If it is a string, it will be compiled
	 * 		into the query as-is, with no escaping.
	 *
	 * @param string $tableAlias
	 *
	 * @return ORM ORM current instance
	 */
	public function innerJoin($table, $constraint, $tableAlias = null)
	{
		return $this->_addJoinSource("INNER", $table, $constraint, $tableAlias);
	}

	/**
	 * Shortcut of _addjoinsource.
	 * Add a simple LEFT OUTER JOIN source to the query
	 *
	 * @param string $table
	 * @param mixed $constraint may be either a string or an array
	 * 		with three elements. If it is a string, it will be compiled
	 * 		into the query as-is, with no escaping.
	 *
	 * @param string $tableAlias
	 *
	 * @return ORM ORM current instance
	 */
	public function leftOuterJoin($table, $constraint, $tableAlias = null)
	{
		return $this->_addJoinSource("LEFT OUTER", $table, $constraint, $tableAlias);
	}

	/**
	 * Shortcut of _addjoinsource.
	 * Add a simple RIGHT OUTER JOIN source to the query
	 *
	 * @param string $table
	 * @param mixed $constraint may be either a string or an array
	 * 		with three elements. If it is a string, it will be compiled
	 * 		into the query as-is, with no escaping.
	 *
	 * @param string $tableAlias
	 *
	 * @return ORM ORM current instance
	 */
	public function rightOuterJoin($table, $constraint, $tableAlias = null)
	{
		return $this->_addJoinSource("RIGHT OUTER", $table, $constraint, $tableAlias);
	}

	/**
	 * Shortcut of _addjoinsource.
	 * Add a simple FULL OUTER JOIN source to the query
	 *
	 * @param string $table
	 * @param mixed $constraint may be either a string or an array
	 * 		with three elements. If it is a string, it will be compiled
	 * 		into the query as-is, with no escaping.
	 *
	 * @param string $tableAlias
	 *
	 * @return ORM ORM current instance
	 */
	public function fullOuterJoin($table, $constraint, $tableAlias = null)
	{
		return $this->_addJoinSource("FULL OUTER", $table, $constraint, $tableAlias);
	}

	/**
	 * Internal method to add a WHERE condition to the query
	 *
	 * @param string $fragment
	 * @param array $values
	 *
	 * @return ORM ORM current instance
	 */
	private function _addWhere($fragment, $values = array())
	{
		if(!is_array($values))
		{
			$values = array($values);
		}

		$this->_whereConditions[] = array(
			$this->WHERE_FRAGMENT => $fragment,
			$this->WHERE_VALUES => $values,
		);

		return $this;
	}

	/**
	 * Helper method to compile a simple COLUMN SEPARATOR VALUE
	 * style WHERE condition into a string and value ready to
	 * be passed to the _addWhere method. Avoids duplication
	 * of the call to _quoteIdentifier
	 *
	 * @param string $column_name
	 * @param string $separator
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	private function _addSimpleWhere($column_name, $separator, $value)
	{
		$column_name = $this->_quoteIdentifier($column_name);

		return $this->_addWhere("{$column_name} {$separator} ?", $value);
	}

	/**
	 * Return a string containing the given number of question marks,
	 * separated by commas. Eg "?, ?, ?"
	 *
	 * @param int $number_of_placeholders
	 *
	 * @return string
	 */
	private function _createPlaceholders($number_of_placeholders)
	{
		return join(", ", array_fill(0, $number_of_placeholders, "?"));
	}

	/**
	 * Add a WHERE column = value clause to your query. Each time
	 * this is called in the chain, an additional WHERE will be
	 * added, and these will be ANDed together when the final query
	 * is built.
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function where($column_name, $value)
	{
		return $this->where_equal($column_name, $value);
	}

	/**
	 * More explicitly named version of for the where() method. Can be used if preferred.
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function where_equal($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, '=', $value);
	}

	/**
	 * Add a WHERE column != value clause to your query.
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function where_not_equal($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, '!=', $value);
	}

	/**
	 * Special method to query the table by its primary key
	 *
	 * @param mixed $id
	 *
	 * @return ORM ORM current instance
	 */
	public function where_id_is($id)
	{
		return $this->where($this->getIDColumn(), $id);
	}

	/**
	 * Add a WHERE ... LIKE clause to your query.
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function whereLike($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, 'LIKE', $value);
	}

	/**
	 * Add where WHERE ... NOT LIKE clause to your query.
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function whereNotlike($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, 'NOT LIKE', $value);
	}

	/**
	 * Add a WHERE ... > clause to your query
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function where_gt($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, '>', $value);
	}

	/**
	 * Add a WHERE ... < clause to your query
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function where_lt($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, '<', $value);
	}

	/**
	 * Add a WHERE ... >= clause to your query
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function where_gte($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, '>=', $value);
	}

	/**
	 * Add a WHERE ... <= clause to your query
	 *
	 * @param string $column_name
	 * @param mixed $value
	 *
	 * @return ORM ORM current instance
	 */
	public function where_lte($column_name, $value)
	{
		return $this->_addSimpleWhere($column_name, '<=', $value);
	}

	/**
	 * Add a WHERE ... IN clause to your query
	 *
	 * @param string $column_name
	 * @param array $values
	 *
	 * @return ORM ORM current instance
	 *
	 */
	public function whereIn($column_name, $values)
	{
		$column_name = $this->_quoteIdentifier($column_name);
		$placeholders = $this->_createPlaceholders(count($values));
		return $this->_addWhere("{$column_name} IN ({$placeholders})", $values);
	}

	public function whereBetween($column_name, $from, $to)
	{
		$column_name = $this->_quoteIdentifier($column_name);

		return $this->_addWhere("({$column_name} BETWEEN ? AND ?)", array($from, $to));
	}

	/**
	 * Add a WHERE ... NOT IN clause to your query
	 *
	 * @param string $column_name
	 * @param array $values
	 *
	 * @return ORM ORM current instance
	 */
	public function whereNotIn($column_name, $values)
	{
		$column_name = $this->_quoteIdentifier($column_name);
		$placeholders = $this->_createPlaceholders(count($values));
		return $this->_addWhere("{$column_name} NOT IN ({$placeholders})", $values);
	}

	/**
	 * Add a WHERE column IS NULL clause to your query
	 *
	 * @param string $column_name
	 *
	 * @return ORM ORM current instance
	 */
	public function whereNull($column_name)
	{
		$column_name = $this->_quoteIdentifier($column_name);
		return $this->_addWhere("{$column_name} IS NULL");
	}

	/**
	 * Add a WHERE column IS NOT NULL clause to your query
	 *
	 * @param string $column_name
	 *
	 * @return ORM ORM current instance
	 */
	public function whereNotNull($column_name)
	{
		$column_name = $this->_quoteIdentifier($column_name);
		return $this->_addWhere("{$column_name} IS NOT NULL");
	}

	/**
	 * Add a raw WHERE clause to the query. The clause should
	 * contain question mark placeholders, which will be bound
	 * to the parameters supplied in the second argument.
	 *
	 * @param string $clause
	 * @param array $parameters
	 *
	 * @return ORM ORM current instance
	 */
	public function whereRaw($clause, $parameters=array())
	{
		return $this->_addWhere($clause, $parameters);
	}

	/**
	 * Add a LIMIT to the query
	 *
	 * @param int $limit
	 *
	 * @return ORM ORM current instance
	 */
	public function limit($limit)
	{
		$this->_limit = $limit;
		return $this;
	}

	/**
	 * Add an OFFSET to the query
	 * @param int $offset
	 * @return ORM ORM current instance
	 */
	public function offset($offset)
	{
		$this->_offset = $offset;
		return $this;
	}

	/**
	 * Add an ORDER BY clause to the query
	 *
	 * @param string $column_name
	 * @param string $ordering
	 *
	 * @return ORM ORM current instance
	 */
	private function _addOrderBy($column_name, $ordering)
	{
		$column_name = $this->_quoteIdentifier($column_name);
		$this->_order_by[] = "{$column_name} {$ordering}";

		return $this;
	}

	/**
	 * Add an ORDER BY column DESC clause
	 *
	 * @param string $column_name
	 *
	 * @return ORM ORM current instance
	 */
	public function orderByDesc($column_name)
	{
		return $this->_addOrderBy($column_name, 'DESC');
	}

	/**
	 * Add an ORDER BY column ASC clause
	 *
	 * @param string $column_name
	 *
	 * @return ORM ORM current instance
	 */
	public function orderByAsc($column_name)
	{
		return $this->_addOrderBy($column_name, 'ASC');
	}

	/**
	 * Add a column to the list of columns to GROUP BY
	 *
	 * @param string $column_name
	 *
	 * @return ORM ORM current instance
	 */
	public function groupBy($column_name)
	{
		$column_name = $this->_quoteIdentifier($column_name);
		$this->_groupBy[] = $column_name;
		return $this;
	}

	/**
	 * Build a SELECT statement based on the clauses that have
	 * been passed to this instance by chaining method calls.
	 *
	 * @return string full select string
	 */
	private function _buildSelect()
	{
		// If the query is raw, just set the $this->_values to be
		// the raw query parameters and return the raw query
		if($this->_isRawQuery)
		{
			$this->_values = $this->_rawParameters;
			return $this->_rawQuery;
		}

		// Build and return the full SELECT statement by concatenating
		// the results of calling each separate builder method.
		return $this->_joinIfNotEmtpy(" ", array(
			$this->_buildSelectStart(),
			$this->_buildJoin(),
			$this->_buildWhere(),
			$this->_buildGroupBy(),
			$this->_buildOrderBy(),
			$this->_buildLimit(),
			$this->_buildOffset(),
		));
	}

	/**
	 * Build the start of the SELECT statement
	 *
	 * @return string start of select string
	 */
	private function _buildSelectStart()
	{
		$resultColumns = join(', ', $this->_resultColumns);

		if($this->_distinct)
		{
			$resultColumns = 'DISTINCT ' . $resultColumns;
		}

		$fragment = "SELECT {$resultColumns} FROM " . $this->_quoteIdentifier($this->_tableName);

		if(!is_null($this->_tableAlias))
		{
			$fragment .= " " . $this->_quoteIdentifier($this->_tableAlias);
		}
		return $fragment;
	}

	/**
	 * Build the JOIN sources
	 *
	 * @return string JOIN string
	 */
	private function _buildJoin()
	{
		if(count($this->_joinSources) === 0)
		{
			return '';
		}

		return join(" ", $this->_joinSources);
	}

	/**
	 * Build the WHERE clause(s)
	 *
	 * @return string WHERE string
	 */
	private function _buildWhere()
	{
		// If there are no WHERE clauses, return empty string
		if(count($this->_whereConditions) === 0)
		{
			return '';
		}

		$whereConditions = array();

		foreach ($this->_whereConditions as $condition)
		{
			$whereConditions[] = $condition[$this->WHERE_FRAGMENT];
			$this->_values = array_merge($this->_values, $condition[$this->WHERE_VALUES]);
		}

		return "WHERE " . join(" AND ", $whereConditions);
	}

	/**
	 * Build GROUP BY
	 *
	 * @return string GROUP BY string
	 */
	private function _buildGroupBy()
	{
		if(count($this->_groupBy) === 0)
		{
			return '';
		}
		return "GROUP BY " . join(", ", $this->_groupBy);
	}

	/**
	 * Build ORDER BY
	 *
	 * @return string ORDER BY string
	 */
	private function _buildOrderBy()
	{
		if(count($this->_order_by) === 0)
		{
			return '';
		}
		return "ORDER BY " . join(", ", $this->_order_by);
	}

	/**
	 * Build LIMIT
	 *
	 * @return string LIMIT string
	 */
	private function _buildLimit()
	{
		if(!is_null($this->_limit))
		{
			return "LIMIT " . $this->_limit;
		}
		return '';
	}

	/**
	 * Build OFFSET
	 *
	 * @return string OFFSET string
	 */
	private function _buildOffset()
	{
		if(!is_null($this->_offset))
		{
			return "OFFSET " . $this->_offset;
		}
		return '';
	}

	/**
	 * Wrapper around PHP's join function which only adds the pieces if they are not empty.
	 *
	 * @param string $glue
	 * @param array $pieces
	 *
	 * @return string joined resulted string
	 */
	private function _joinIfNotEmtpy($glue, $pieces)
	{
		$filtered_pieces = array();

		foreach ($pieces as $piece)
		{
			if(is_string($piece))
			{
				$piece = trim($piece);
			}

			if(!empty($piece))
			{
				$filtered_pieces[] = $piece;
			}
		}

		return join($glue, $filtered_pieces);
	}

	/**
	 * Quote a string that is used as an identifier (table names, column names etc). This method can
	 * also deal with dot-separated identifiers eg table.column
	 *
	 * @param string $identifier
	 *
	 * @return string quoted string
	 */
	private function _quoteIdentifier($identifier)
	{
		$parts = explode('.', $identifier);
		$parts = array_map(array($this, '_quoteIdentifierPart'), $parts);
		return join('.', $parts);
	}

	/**
	 * This method performs the actual quoting of a single
	 * part of an identifier, using the identifier quote
	 * character specified in the config (or autodetected).
	 *
	 * @param string $part
	 *
	 * @return string Quoted string part
	 */
	private function _quoteIdentifierPart($part)
	{
		if($part === '*')
		{
			return $part;
		}

		$quote_character = $this->_config['identifier_quote_character'];
		return $quote_character . $part . $quote_character;
	}

	/**
	 * Create a cache key for the given query and parameters.
	 *
	 * @param string $query
	 * @param array $parameters
	 *
	 * @return string sha1 encrypted string
	 */
	private function _createCacheKey($query, $parameters)
	{
		$parameter_string = join(',', $parameters);
		$key = $query . ':' . $parameter_string;
		return sha1($key);
	}

	/**
	 * Check the query cache for the given cache key. If a value
	 * is cached for the key, return the value. Otherwise, return false.
	 *
	 * @param string $cache_key
	 *
	 * @return mixed cached value
	 */
	private function _checkQueryCache($cache_key)
	{
		if(isset(self::$_queryCache[$cache_key]))
		{
			return self::$_queryCache[$cache_key];
		}
		return false;
	}

	/**
	 * Clear the query cache
	 */
	public function clearCache()
	{
		self::$_queryCache = array();
	}

	/**
	 * Clear the query log
	 */
	public static function clearLog()
	{
		self::$_queryLog = array();
	}

	/**
	 * Add the given value to the query cache.
	 *
	 * @param string $cache_key
	 * @param array $value
	 */
	private function _cacheQueryResult($cache_key, $value)
	{
		self::$_queryCache[$cache_key] = $value;
	}

	/**
	 * Execute the SELECT query that has been built up by chaining methods
	 * on this class. Return an array of rows as associative arrays.
	 *
	 * @return array associative array of rows
	 *
	 * @throws ORM_Execption
	 */
	private function _run()
	{
		$query = $this->_buildSelect();
		$caching_enabled = $this->_config['caching'];

		if($caching_enabled)
		{
			$cache_key = $this->_createCacheKey($query, $this->_values);
			$cached_result = $this->_checkQueryCache($cache_key);

			if($cached_result !== false)
			{
				return $cached_result;
			}
		}

		$this->_logQuery($query, $this->_values);
		$statement = $this->_db->prepare($query);

		try
		{
			$statement->execute($this->_values);
		}
		catch(Exception $ex)
		{
			throw new MainException($ex->getMessage() . ". Executed query was: " .$query);
		}

		$rows = array();

		while ($row = $statement->fetch(\PDO::FETCH_ASSOC))
		{
			$rows[] = $row;
		}

		if($caching_enabled)
		{
			$this->_cacheQueryResult($cache_key, $rows);
		}

		return $rows;
	}

	/**
	 * Save any fields which have been modified on this object to the database.
	 *
	 * @param Entry $entry
	 * @param bool $force If force is setted to true, save method does a REPLACE query, INSERT query otherwise
	 *
	 * @return bool save success (or not)
	 *
	 * @throws MainException
	 */
	public function save(Entry $entry, $force=false)
	{
		$query = array();
		$values = array_values($entry->getDirtyFields());

		if(!$entry->isNew())
		{
			// if there are no dirty values, do nothing
			if(count($values) == 0)
			{
				return true;
			}

			$query = $this->_buildUpdate($entry->getDirtyFields());
			$values[] = $entry->getID();
		}
		else
		{
			$query = $this->_buildSave($entry->getDirtyFields(), $force);
		}

		$this->_logQuery($query, $values);
		$statement = $this->_db->prepare($query);

		try
		{
			$success = $statement->execute($values);
		}
		catch(Exception $ex)
		{
			throw new MainException($ex->getMessage() . ". Executed query was: " .$query);
		}

		// if we've just inserted a new record, set the ID of this object
		if($entry->isNew())
		{
			$entry->updateNewStatus();

			if(is_null($entry->getID()))
			{
				$entry->setField($this->getIDColumn(), $this->_db->lastInsertId());
			}
		}

		$entry->resetDirty();

		return $success;
	}

	/**
	 * Build an UPDATE query
	 *
	 * @param array $dirty_fields
	 *
	 * @return string UPDATE sentence
	 */
	private function _buildUpdate($dirty_fields)
	{
		$query = array();
		$query[] = "UPDATE {$this->_quoteIdentifier($this->_tableName)} SET";

		$field_list = array();

		foreach ($dirty_fields as $key => $value)
		{
			$field_list[] = "{$this->_quoteIdentifier($key)} = ?";
		}
		$query[] = join(", ", $field_list);
		$query[] = "WHERE";
		$query[] = $this->_quoteIdentifier($this->getIDColumn());
		$query[] = "= ?";

		return join(" ", $query);
	}

	/**
	 * Build an INSERT or REPLACE query.
	 *
	 * @param array $dirty_fields
	 * @param bool $force if its true, builds a replace sentece, insert sentece otherwise
	 *
	 * @return string INSERT sentence
	 */
	private function _buildSave($dirty_fields, $force)
	{
		$query = array();
		$query[] = ($force ? 'REPLACE' : 'INSERT') . " INTO";
		$query[] = $this->_quoteIdentifier($this->_tableName);
		$field_list = array_map(array($this, '_quoteIdentifier'), array_keys($dirty_fields));
		$query[] = "(" . join(", ", $field_list) . ")";
		$query[] = "VALUES";

		$placeholders = $this->_createPlaceholders(count($dirty_fields));
		$query[] = "({$placeholders})";

		return join(" ", $query);
	}

	/**
	 * Delete record(s) from the database. If param passed, deletes that row,
	 * otherwise it builds a complete delete query using pre-established filters/wheres
	 *
	 * @param Entry $entry
	 *
	 * @throws MainException When the entry has no primary key setted
	 *
	 * @return bool DELETE success (or not)
	 */
	public function delete(Entry $entry = null)
	{
		if($entry != null)
		{
			if($entry->getID()===null)
			    throw new MainException("Impossible deletion. No primary key setted for entry.");
			return $this->deleteWhereIdIs($entry->getID());
		}
		else
			return $this->_buildDeleteQuery();
	}

	/**
	 * Delete record from the database attending to the primary key.
	 *
	 * @param mixed $id the primary key value to filter by
	 *
	 * @return bool DELETE success (or not)
	 */
	public function deleteWhereIdIs($id)
	{
		return $this->where_id_is($id)->_buildDeleteQuery();
	}

	/**
	 * build the delete query attending on setted filters.
	 *
	 * @throws MainException When the query fails
	 *
	 * @return bool DELETE success (or not)
	 */
	private function _buildDeleteQuery()
	{
		$this->_values=array();
		$query = $this->_joinIfNotEmtpy(" ", array(
			"DELETE FROM",
			$this->_quoteIdentifier($this->_tableName),
			$this->_buildWhere(),
		));

		$this->_logQuery($query, $this->_values);
		$statement = $this->_db->prepare($query);

		try
		{
			return $statement->execute($this->_values);
		}
		catch(Exception $ex)
		{
			throw new MainException($ex->getMessage() . ". Executed query was: " .$query);
		}
	}

	/**
	 * Return the name of the column in the database table which contains the primary key ID of the row.
	 *
	 * @return string name of primary key
	 */
	public function getIDColumn()
	{
		if(!is_null($this->_instanceIDColumn))
		{
			return $this->_instanceIDColumn;
		}

		if(isset($this->_config['id_column_overrides'][$this->_tableName]))
		{
			return $this->_config['id_column_overrides'][$this->_tableName];
		}
		else
		{
			return $this->_config['id_column'];
		}
	}


	/**
	 * Setup quote character
	 *
	 * Detect and initialise the character used to quote identifiers
	 * (table names, column names etc). If this has been specified
	 * manually using setConfig('identifier_quote_character', 'some-char'),
	 * this will do nothing.
	 */
	private function _setupQuoteCharacter()
	{
		if(is_null($this->_config['quote_character']))
		{
			$this->_config['quote_character'] = $this->_detectQuoteCharacter();
		}
	}

	/**
	 * Detect and get quote character
	 *
	 * Return the correct character used to quote identifiers (table
	 * names, column names etc) by looking at the driver being used by \PDO.
	 *
	 * @return string Quote character
	 */
	private function _detectQuoteCharacter()
	{
		switch($this->_db->getAttribute(\PDO::ATTR_DRIVER_NAME))
		{
			case 'pgsql':
			case 'sqlsrv':
			case 'dblib':
			case 'mssql':
			case 'sybase':
				return '"';
			case 'mysql':
			case 'sqlite':
			case 'sqlite2':
			default:
				return '`';
		}
	}

	/**
	 * Get the primary key ID of this object.
	 *
	 * @param array
	 *
	 * @return mixed primary key value
	 */
	public function id($data)
	{
		return $data[$this->getIDColumn()];
	}
}
