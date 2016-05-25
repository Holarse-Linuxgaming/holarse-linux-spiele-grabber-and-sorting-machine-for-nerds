<?php
/**
 * MySQLi Database Class
 *
 * @category  Database Access
 * @package   Database
 * @author    Vivek V <vivekv@vivekv.com>
 * @copyright Copyright (c) 2014
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version   1.5.1
 **/

class Database
{

	protected static $_instance;

	/**
	 * MySQLi instance
	 */
	protected $_mysqli;

	/**
	 * The SQL Query
	 */
	private $_query;

	/**
	 * Affected rows after a select/update/delete query
	 */
	var $affected_rows = 0;
	/**
	 * Limit and offset
	 */
	private $_limit;
	private $_offset;
	private $_result;
	var $error = '';
	var $debug = TRUE;
	var $die_on_error = TRUE;
	// Script execution will stop if set to TRUE. Default is TRUE ;
	private $_last_query = '';
	private $_executed = FALSE;
	private $_delete = FALSE;
	private $_distinct = FALSE;
	protected $table_prefix;
	private $_dryrun = FALSE;
    private $_parenthesis = '';

	/**
	 * The table name used as FROM
	 */
	var $_fromTable;

	/**
	 * Arrays
	 */
	var $array_where = array();
	var $array_select = array();
	var $array_wherein = array();
	var $array_groupby = array();
	var $array_having = array();
	var $array_orderby = array();
	var $array_join = array();

	public function __construct($host, $username, $password, $db, $port = NULL)
	{
		// Get the default port number if not given.
		if ($port == NULL)
			$port = ini_get('mysqli.default_port');
		$this -> _mysqli = @new mysqli($host, $username, $password, $db, $port);
		if (!$this -> _mysqli)
			die($this -> oops('There was a problem connecting to the database'));
		$this -> _mysqli -> set_charset('utf8');
		self::$_instance = $this;
	}

	/**
	 * Close connection
	 */
	public function __destruct()
	{
		@$this -> _mysqli -> close();
	}

	/**
	 * Get the instance of the class.
	 *
	 * @uses $db = Database:getInstance();
	 *
	 * @return object Returns the current instance
	 */

	public static function getInstance()
	{
		return self::$_instance;
	}

	/**
	 * Reset function after execution
	 *
	 */
	private function reset()
	{
		unset($this -> _query);
		unset($this -> _limit);
		unset($this -> _offset);
		$this -> _delete = FALSE;
		$this -> _distinct = FALSE;
		$this -> _dryrun = FALSE;
		$this -> array_where = array();
		$this -> array_select = array();
		$this -> array_wherein = array();
		$this -> array_groupby = array();
		$this -> array_having = array();
		$this -> array_orderby = array();
		$this -> array_join = array();
	}

	/**
	 * Sets a limit and offset clause. Offset is optional
	 *
	 * @uses $db->limit(0,12); // Will list the first 12 rows
	 * @uses $db->limit(1); // Will list the first 1 row.
	 */

	public function limit($limit, $offset = null)
	{
		$this -> _limit = (int)$limit;
		if ($offset)
			$this -> _offset = (int)$offset;

		return $this;
	}

	/**
	 * Executes raw sql query.
	 *
	 * @param $query string The raw query
	 * @param $sanitize boolean If true is provided, the query will be sanitized.
	 * Default is False
	 *
	 * @return object Returns the object. Use $db->fetch() to get the results array
	 */
	public function query($query, $sanitize = FALSE)
	{
		if ($sanitize == TRUE)
			$this -> _query = filter_var($query, FILTER_SANITIZE_STRING);
		else
			$this -> _query = $query;
		$this -> _executed = FALSE;
		/*
		 * Issue #7 bugfix. If the user entered a custom SQL Query, then we set executed
		 * as FALSE always, so that the second query can be executed
		 * https://bitbucket.org/getvivekv/php-mysqli-class/issue/7/error-in-fetch-after-an-executed-sql-query
		 * Thanks NoXPhasma!
		 *
		 **/

		return $this;
	}

	/**
	 * Executes a raw query. This is same as query() function but it returns only the
	 * first row as result.
	 * @uses $db->query_first("SELECT * FROM table"); // Will product "SELECT * FROM
	 * table LIMIT 1"
	 * @return object Returns the object. Use $db->fetch() to get the results array
	 */

	public function query_first($query)
	{
		$this -> limit(1) -> query($query);
		return $this;
	}

	/**
	 * Sets the WHERE clause
	 * Multiple instances are joined by AND
	 * @param $key array Can either be string or array.
	 * @param $value string Optional. Need only if $key is a string..
	 *
     * @return Database
	 */

	public function where($key, $value = null)
	{
		return $this -> _where($key, $value, 'AND ');
	}

	/**
	 * Sets the OR WHERE clause
	 * This function is identical to where() function except that multiple instances
	 * are joined by OR
	 * @param $key array Can either be string or array.
	 * @param $value string Optional. Need only if $key is a string..
	 *
     * * @return Database
	 */

	public function or_where($key, $value = null)
	{
		return $this -> _where($key, $value, 'OR ');
	}

	/**
	 * Tests whether the string has an SQL operator
	 *
	 * @param	string
	 * @return	bool
	 */
	function _has_operator($str)
	{
		$str = trim($str);
		if (!preg_match("/(\s|<|>|!|=|is null|is not null)/i", $str))
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Save WHERE as array for building the query
	 */

	protected function _where($key, $value, $type = 'AND ')
	{
		/**
		 * If user provided custom where() clauses then we do not need to process it
		 */

		if (!is_array($key) AND is_null($value))
		{
			$this -> array_where[0] = $key;
			return $this;
		}
		/**
		 * If the WHERE key is an array then we process the array
		 */

		if (is_array($key) AND is_null($value))
		{
			foreach ($key as $wkey => $wval)
			{
				$this -> _where($wkey, $wval, $type);
			}
		}
		else
		{
			$prefix = (count($this -> array_where) == 0) ? '' : $type;
			$value = $this -> escape($value);
			if ($this -> _has_operator($key))
			{
				if ($this -> isReservedWord($key) == true)
                {
                    if(empty($this->_parenthesis))
                        $this -> array_where[] = "$prefix`$key` '$value'";
                    else
                    {
                        if($this->_parenthesis == ')')
                            $this -> array_where[] = "$this->_parenthesis $prefix `$key` '$value'";
                        else
                            $this -> array_where[] = "$prefix $this->_parenthesis `$key` '$value'";
                        $this -> _parenthesis = '';
                    }

                }

				else
					$this -> array_where[] = "$prefix$key '$value'";
			}

			else
			{
				if ($this -> isReservedWord($key) == true)
                {
                    if(empty($this->_parenthesis))
                        $this -> array_where[] = "$prefix`$key` = '$value'";
                    else
                    {
                        if($this->_parenthesis == ')')
                            $this -> array_where[] = "$this->_parenthesis $prefix `$key` = '$value'";
                        else
                            $this -> array_where[] = "$prefix $this->_parenthesis `$key` = '$value'";
                        $this -> _parenthesis = '';
                    }
                }

				else
                {
                    if(empty($this->_parenthesis))
                        $this -> array_where[] = "$prefix$key = '$value'";
                    else
                    {
                        if($this->_parenthesis == ')')
                            $this -> array_where[] = "$this->_parenthesis $prefix $key = '$value'";
                        else
                            $this -> array_where[] = "$prefix $this->_parenthesis $key = '$value'";
                        $this -> _parenthesis = '';
                    }
                }

			}
		}
		return $this;

	}

    function open_where()
    {
        $this -> _parenthesis = '(';
    }
    function close_where()
    {
        $this -> _parenthesis = ')';
    }

	/**
	 * The SELECT portion of the query.
	 *
	 * @param $select Can either be a string or an array containing the columns to be
	 * selected. If none provided, * will be assigned by default
	 * @uses $db->select("id, email, password") ;
	 * @uses $db->select(array('id', 'email', 'password')) ;
     * * @return Database
	 */

	public function select($select = '*')
	{
		if (is_string($select))
		{
			$select = explode(',', $select);
		}
		foreach ($select as $val)
		{
			$val = trim($val);

			if ($val != '')
			{
				if ($this -> isReservedWord($val))
					$this -> array_select[] = "`$val`";
				else
					$this -> array_select[] = "$val";

			}
		}
		return $this;
	}

	/**
	 * Sets the FROM portion of the query.
	 *
	 * @param $table string Name of the table.
     * @return Database
	 */
	public function from($table)
	{
		if (isset($this -> table_prefix))
			$this -> _fromTable = $this -> table_prefix . $table;
		else
			$this -> _fromTable = $table;
		return $this;
	}

	/**
	 * Build the query string
	 */

	private function prepare()
	{

		/**
		 * We need to process $this->_query only if the user has not given a _query
		 * string.
		 */

		if (!isset($this -> _query))
		{
			// Write the "SELECT" portion of the query
			if (!empty($this -> array_select))
			{
				$this -> _query = (!$this -> _distinct) ? 'SELECT ' : 'SELECT DISTINCT ';
				if ($this -> array_select == '*' OR count($this -> array_select) == 0)
				{
					$this -> _query .= '*';
				}
				else
				{
					$this -> _query .= implode(",", $this -> array_select);
				}
			}

			// If delete() is set, then the function is a delete function.

			if ($this -> _delete == TRUE)
			{
				// If the query is to delete row(s), make sure we have the table name.
				if ($this -> _fromTable == null)
				{
					$this -> oops('Table Name is required for delete function');
				}
				$this -> _query = 'DELETE';
			}

			// If select() is not called but the call is a SELECT statement
			if ($this -> _delete == FALSE && empty($this -> array_select))
			{
				$this -> _query = (!$this -> _distinct) ? 'SELECT * ' : 'SELECT DISTINCT * ';
			}

			$this -> _delete = FALSE;
			// unset delete flag

			// Write the "FROM" portion of the query
			if (isset($this -> _fromTable))
			{
				if ($this -> isReservedWord($this -> _fromTable))
					$this -> _query .= " FROM `$this->_fromTable` ";
				else
					$this -> _query .= " FROM $this->_fromTable ";
			}

			// Write the "JOIN" portion of the query

			if (count($this -> array_join) > 0)
			{
				$this -> _query .= " ";
				$this -> _query .= implode("\n", $this -> array_join);
			}

			// Write the "WHERE" portion of the query
			if (count($this -> array_where) > 0)
			{
				/*
				 * Bugfix #17. If nothing is provided as the where value then we assign it as no
				 * value
				 */
				for ($i = 0; $i < count($this -> array_where); $i++)
				{
					if (!$this -> _has_operator($this -> array_where[$i]))
					{
						$this -> array_where[$i] = $this -> array_where[$i] . " = ''";
					}
				}
				$this -> _query .= " WHERE ";
				$this -> _query .= implode("\n", $this -> array_where);
                if($this -> _parenthesis)
                {
                    $this -> _query .= $this -> _parenthesis;
                    $this -> _parenthesis = '';
                }

			}

		}

		// Write the "GROUP BY" portion of the query

		if (!empty($this -> array_groupby))
		{
			$this -> _query .= " GROUP BY ";
			$this -> _query .= implode(', ', $this -> array_groupby);
		}

		// Write the "HAVING" portion of the query

		if (!empty($this -> array_having))
		{
			$this -> _query .= " HAVING ";
			$this -> _query .= implode("\n", $this -> array_having);
		}

		// Write the "ORDER BY" portion of the query

		if (!empty($this -> array_orderby))
		{
			$this -> _query .= " ORDER BY ";
			$this -> _query .= implode(', ', $this -> array_orderby);
		}

		// Write the "LIMIT" portion of the query
		if (isset($this -> _limit))
		{
			$this -> _query .= ' LIMIT ' . $this -> _limit;
		}

		// Write the "OFFSET" portion of the query
		if (isset($this -> _limit) && isset($this -> _offset))
		{
			$this -> _query .= ' OFFSET ' . $this -> _offset;
		}

		return $this;

	}

	/**
	 * Dry Run function allows the developer to view the full query before its
	 * execution.
	 */

	public function dryrun()
	{
		$this -> _dryrun = TRUE;
		return $this;
	}

	/**
	 * Execute the query. This function returns the object. For getting the result of
	 * the execution use fetch();
	 */

	public function execute()
	{
		$this -> prepare();
		if ($this -> _dryrun == TRUE)
		{
			$q = $this -> _query;
			$this -> reset();
			$this -> _query = $q;
			$this -> _dryrun = true;
			return $this;
		}

		$this -> _result = $this -> _mysqli -> query($this -> _query);
		if (!$this -> _result)
			$this -> oops();

		$this -> affected_rows = $this -> _mysqli -> affected_rows;
		$this -> _last_query = $this -> _query;

		$this -> reset();
		$this -> _executed = TRUE;
		return $this;
	}

	/**
	 * Fetches the result of an execution.
	 *
	 * @return array Returns an Associate Array of results.
	 */
	public function fetch()
	{
		if ($this -> _executed == FALSE || !isset($this -> _query))
			$this -> execute();

		if (is_object($this -> _result))
		{
			$this -> _executed = FALSE;
			// Checks whether fetch_all method is available. It is available only with MySQL
			// Native Driver.
			if (method_exists('mysqli_result', 'fetch_all'))
			{
				$results = $this -> _result -> fetch_all(MYSQLI_ASSOC);
			}
			else
			{
				for ($results = array(); $tmp = $this -> _result -> fetch_array(MYSQLI_ASSOC); )
					$results[] = $tmp;
			}
			return $results;
		}
		else
		{
			$this -> oops('Unable to perform fetch()');
		}

	}

	/**
	 * Fetches the first row of the result
	 */
	public function fetch_first()
	{
		if ($this -> _executed == FALSE || !isset($this -> _query))
			$this -> execute();

		if (is_object($this -> _result))
		{
			$this -> _executed = FALSE;
			$results = $this -> _result -> fetch_array(MYSQLI_ASSOC);
			return $results;
		}
		else
		{
			$this -> oops('Unable to perform fetch_first()');
		}
	}

	public function fetch_array()
	{
		return $this -> fetch_first();
	}

	/**
	 * This function returns the last build query. Useful for troubleshooting the
	 * code.
	 *
	 * @return string Last query, exmaple : "SELECT * FROM table"
	 */
	public function last_query()
	{
		if ($this -> _dryrun == TRUE)
			return $this -> _query;
		else
			return $this -> _last_query;
	}

	/**
	 * Remove dangerous input
	 *
	 * @param string $string The string needs to be sanitized
	 * @return string Returns the sanitized string
	 */
	public function escape($string)
	{
		if (get_magic_quotes_runtime())
			$string = stripslashes($string);
		return @$this -> _mysqli -> real_escape_string($string);
	}

	/**
	 * Inserts data into table.
	 *
	 * @param string $table Name of the table
	 * @param array $data The array which contains the coulumn name and values to be
	 * inserted.
	 *
	 * @return integer Returns the inserted id. ( mysqli->insert_id)
	 */

	public function insert($table, $data)
	{
		if (isset($this -> table_prefixfix))
			$table = $this -> table_prefix . $table;

		foreach ($data as $key => $value)
		{
			$keys[] = "`$key`";
			if (strpos($value, '()') == true)
				$values[] = "$value";
			else
				$values[] = "'$value'";
		}
		$this -> _query = "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");";
		$this -> execute();
		return $this -> _mysqli -> insert_id;
	}

	/**
	 * Update query. Use where() if needed. Call execute() to execute the query
	 *
	 * @param $table string Name of the table
	 * @param $data string Array containing the data to be updated
     * @return Database
	 *
	 */

	public function update($table, $data)
	{
		if (isset($this -> table_prefix))
			$table = $this -> table_prefix . $table;

		foreach ($data as $key => $val)
		{
			if (strpos($val, '()') == true)
				$valstr[] = "`$key`" . " = $val";
			else
				$valstr[] = "`$key`" . " = '$val'";
		}

		$this -> _query = "UPDATE " . $table . " SET " . implode(', ', $valstr);
		if (count($this -> array_where) > 0)
		{
			$this -> _query .= " WHERE ";
			$this -> _query .= implode(" ", $this -> array_where);
		}
		$this -> execute();
		return $this;
	}

	/**
	 * Permits to write the LIKE portion of the query using the connector AND
	 *
	 * @param $title string or array Can either be a string or array. This is the
	 * title portion of LIKE
	 * @param $match string Required only if $title is a string. This is the matching
	 * portion
	 * @param $place string This enables you to control where the wildcard (%) is
	 * placed. Options are "both", "before", and "after". Default is "both"
     * @return Database
	 */

	public function like($title, $match = null, $place = 'both')
	{
		$this -> _like($title, $match, $place, 'AND ');
		return $this;

	}

	/**
	 * Permits to write the LIKE portion of the query using the connector OR
	 *
	 * @param $title string or array Can either be a string or array. This is the
	 * title portion of LIKE
	 * @param $match string Required only if $title is a string. This is the matching
	 * portion
	 * @param $place string This enables you to control where the wildcard (%) is
	 * placed. Options are "both", "before", and "after". Default is "both"
     * @return Database
	 */

	public function or_like($title, $match = null, $place = 'both')
	{
		$this -> _like($title, $match, $place, 'OR ');
		return $this;
	}

	/**
	 * Builds _like
	 */

	protected function _like($title, $match, $place = 'both', $type)
	{
		// If $title is an array, we need to process it

		if (is_array($title))
		{
			foreach ($title as $key => $value)
			{
				$this -> _like($key, $value, $place, $type);
			}
		}
		else
		{
 			$prefix = (count($this -> array_where) == 0) ? '' : $type;
			$match = $this -> escape($match);

			if ($place == 'both')
			{
				if ($this -> isReservedWord($title))
					$this -> array_where[] = "$prefix`$title` LIKE '%$match%'";
				else
					$this -> array_where[] = "$prefix$title LIKE '%$match%'";
			}

			if ($place == 'before')
			{
				if ($this -> isReservedWord($title))
					$this -> array_where[] = "$prefix`$title` LIKE '%$match'";
				else
					$this -> array_where[] = "$prefix$title LIKE '%$match'";
			}

			if ($place == 'after')
			{
				if ($this -> isReservedWord($title))
					$this -> array_where[] = "$prefix`$title` LIKE '$match%'";
				else
					$this -> array_where[] = "$prefix$title LIKE '$match%'";
			}

			if ($place == 'none')
			{
				if ($this -> isReservedWord($title))
					$this -> array_where[] = "$prefix`$title` LIKE '$match'";
				else
					$this -> array_where[] = "$prefix$title LIKE '$match'";
			}

			return $this;

		}

	}

	private function oops($msg = null)
	{
		// If debug is not enabled, do not proceed
		if (!$this -> debug)
			return;

		if (!$msg)
		{
			$msg = 'MySQL Error has occured';
		}
		$this -> error = mysqli_error($this -> _mysqli);

		echo '<table align="center" border="1" cellspacing="0" style="background:white;color:black;width:80%;">
		<tr><th colspan=2>Database Error</th></tr>
		<tr><td align="right" valign="top">Message:</td><td> ' . $msg . '</td></tr> ';

		if (!empty($this -> error))
			echo '<tr><td align="right" valign="top" nowrap>MySQL Error:</td><td>' . $this -> error . '</td></tr>';
		echo '<tr><td align="right">Date:</td><td>' . date("l, F j, Y \a\\t g:i:s A") . '</td></tr>';
		if (!empty($this -> _query))
			echo '<tr><td align="right">Query:</td><td>' . $this -> _query . '</td></tr>';

		$debug = array_reverse(debug_backtrace());
		echo '<tr><td align="right">Trace:</td><td>';
		foreach ($debug as $issues)
		{
			echo $issues['file'] . ' at line ' . $issues['line'] . '<br>';
		}
		echo '</td></tr>';
		echo '</table>';

		unset($this -> error);
		if ($this -> die_on_error == TRUE)
			die();
	}

	/**
	 * SELECT_MAX Portion of the query
	 *
	 * Writes a "SELECT MAX(field)" portion for your query. You can optionally
	 * include a second parameter to rename the resulting field.
	 */

	public function select_max($field, $name = null)
	{
		if ($name == null)
			$name = $field;
		if ($this -> isReservedWord($field))
			$this -> array_select[0] = "MAX(`$field`) AS $name ";
		else
			$this -> array_select[0] = "MAX($field) AS $name ";
		return $this;
	}

	/**
	 * SELECT_MIN Portion of the query
	 *
	 * Writes a "SELECT MIN(field)" portion for your query. You can optionally
	 * include a second parameter to rename the resulting field.
	 */

	public function select_min($field, $name = null)
	{
		if ($name == null)
			$name = $field;
		if ($this -> isReservedWord($field))
			$this -> array_select[0] = "MIN(`$field`) AS $name ";
		else
			$this -> array_select[0] = "MIN($field) AS $name ";
		return $this;

	}

	/**
	 * SELECT_AVG Portion of the query
	 *
	 * Writes a "SELECT AVG(field)" portion for your query. You can optionally
	 * include a second parameter to rename the resulting field.
	 */

	public function select_avg($field, $name = null)
	{
		if ($name == null)
			$name = $field;
		if ($this -> isReservedWord($field))
			$this -> array_select[0] = "AVG(`$field`) AS $name ";
		else
			$this -> array_select[0] = "AVG($field) AS $name ";
		return $this;

	}

	/**
	 * SELECT_SUM Portion of the query
	 *
	 * Writes a "SELECT SUM(field)" portion for your query. You can optionally
	 * include a second parameter to rename the resulting field.
	 */

	public function select_sum($field, $name = null)
	{
		if ($name == null)
			$name = $field;
		if ($this -> isReservedWord($field))

			$this -> array_select[0] = "SUM(`$field`) AS $name ";
		else
			$this -> array_select[0] = "SUM($field) AS $name ";
		return $this;

	}

	/**
	 * WHERE IN
	 */

	public function where_in($key = NULL, $values = NULL)
	{
		$this -> _where_in($key, $values);

	}

	/**
	 * WHERE OR
	 */

	public function or_where_in($key = NULL, $values = NULL)
	{
		$this -> _where_in($key, $values, FALSE, 'OR ');
		return $this;
	}

	/**
	 * WHERE NOT IN
	 */

	public function where_not_in($key = NULL, $values = NULL)
	{
		$this -> _where_in($key, $values, TRUE);
		return $this;
	}

	/**
	 * WHERE NOT IN OR
	 */
	public function or_where_not_in($key = NULL, $values = NULL)
	{
		$this -> _where_in($key, $values, TRUE, 'OR ');
		return $this;
	}

	/**
	 * WHERE IN process
	 *
	 * Called by where_in, where_in_or, where_not_in, where_not_in_or
	 */
	protected function _where_in($key = NULL, $values = NULL, $not = FALSE, $type = 'AND ')
	{
		if ($key === NULL OR $values === NULL)
		{
			return;
		}
		if (!is_array($values))
		{
			$values = array($values);
		}
		$not = ($not) ? ' NOT' : '';
		foreach ($values as $value)
		{
			$this -> array_wherein[] = "'" . $this -> escape($value) . "'";
		}
		$prefix = (count($this -> array_where) == 0) ? '' : $type;

		if ($this -> isReservedWord($key))
			$where_in = $prefix . "`$key`" . $not . " IN (" . implode(", ", $this -> array_wherein) . ") ";
		else
			$where_in = $prefix . "$key" . $not . " IN (" . implode(", ", $this -> array_wherein) . ") ";
		$this -> array_where[] = $where_in;
		$this -> array_wherein = array();
		return $this;
	}

	/**
	 * Group by
	 *
	 * @param string or array $by Either an arry
     * @return Database
	 */

	public function group_by($by)
	{
		if (is_string($by))
		{
			$by = explode(',', $by);
		}

		foreach ($by as $val)
		{
			$val = trim($val);

			if ($val != '')
			{
				if ($this -> isReservedWord($val))
					$this -> array_groupby[] = "`$val`";
				else
					$this -> array_groupby[] = "$val";
			}
		}
		return $this;

	}

	/**
	 * Sets the HAVING value
	 *
	 * Separates multiple calls with AND
	 *
	 * @param	string
	 * @param	string
	 * @return	object
	 */
	public function having($key, $value = '')
	{
		return $this -> _having($key, $value, 'AND ');
	}

	// --------------------------------------------------------------------

	/**
	 * Sets the OR HAVING value
	 *
	 * Separates multiple calls with OR
	 *
	 * @param	string
	 * @param	string
	 * @return	object
	 */
	public function or_having($key, $value = '')
	{
		return $this -> _having($key, $value, 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * Sets the HAVING values
	 *
	 * Called by having() or or_having()
	 *
	 * @param	string
	 * @param	string
	 * @return	object
	 */
	protected function _having($key, $value = '', $type = 'AND ')
	{
		if (!is_array($key))
		{
			$key = array($key => $value);
		}
		foreach ($key as $k => $v)
		{
			$prefix = (count($this -> array_having) == 0) ? '' : $type;

			if ($v != '')
			{
				$v = " = '" . $this -> escape($v) . "'";
			}
			if ($this -> isReservedWord($k))
				$this -> array_having[] = $prefix . "`$k`" . $v;
			else
				$this -> array_having[] = $prefix . "$k" . $v;
		}
		return $this;
	}

	/**
	 * ORDER By clause
	 */

	public function order_by($orderby, $direction = null)
	{
		// If custom order by is given
		if (!is_array($orderby) AND is_null($direction))
		{
			$this -> array_orderby[0] = $orderby;
			return $this;
		}
		// If $orderby is an array the we ignore the value of $direction

		if (is_array($orderby))
		{
			foreach ($orderby as $key => $value)
			{
				$this -> order_by($key, $value);
			}
		}
		else
		{
			$direction = strtoupper($direction);
			if ($this -> isReservedWord($orderby))
				$this -> array_orderby[] = "`$orderby` $direction";
			else
				$this -> array_orderby[] = "$orderby $direction";
		}
		return $this;

	}

	/**
	 * Delete function
	 *
	 * @param string $table Name of the table from where the values to be deleted. It
	 * is optional. If value is not given then the value set by from() will be taken
     * @return Database
	 */

	public function delete($table = null)
	{
		if ($table)
			$this -> from($table);
		$this -> _delete = TRUE;
		return $this;

	}

	/**
	 * Set table prefix
	 *
	 * @param string $prefix The prefix of the table. For eg. tbl_
     * @return Database
	 */

	public function set_table_prefix($prefix)
	{
		if ($prefix)
			$this -> table_prefix = $prefix;

		return $this;
	}

	/**
	 * Join
	 *
	 * Generates the JOIN portion of the query
	 *
	 * @param	string $table Table for joining
	 * @param	string $condition Condition of join
	 * @param	string $type Type of join. Example 'LEFT', 'RIGHT', 'OUTER', 'INNER',
	 * 'LEFT OUTER', 'RIGHT OUTER'
     * @return Database

	 */
	public function join($table, $condition, $type = null)
	{
		if ($type == null)
			$type = 'LEFT';
		// Default is left join
		$type = strtoupper($type);
		$join = $type . ' JOIN ' . $table . ' ON ' . $condition;
		$this -> array_join[] = $join;
		return $this;
	}

	/**
	 * Set a flag for DISTINCT keyword
	 */

	public function distinct()
	{
		$this -> _distinct = TRUE;
		return $this;
	}

	/**
	 * FIND IN SET
	 * This function is used to generate a FIND_IN_SET query
	 *
	 * Generates the FIND_IN_SET portion of the query
	 *
	 * @param string $search The search parameter
	 * @param string $column The name of the column
	 * @param string $type The connection keyword, AND or OR. Default is AND
     * @return Database
	 */
	function find_in_set($search, $column, $type = 'AND ')
	{
		$prefix = (count($this -> array_where) == 0) ? '' : $type;
		$this -> array_where[] = "$prefix FIND_IN_SET ('$search', $column) ";
		return $this;
	}

	/**
	 * BETWEEN
	 *
	 * This function is used to generate a BETWEEN condition.
	 *
	 * @param string $experssion Expression parameter
	 * @param string $value1 First value
	 * @param string $value2 Second value
	 * @param string $type Optional parameter. AND or OR
	 *
     * @return Database
	 */
	function between($expression, $value1, $value2, $type = 'AND ')
	{
		$prefix = (count($this -> array_where) == 0) ? '' : $type;
		$this -> array_where[] = "$prefix $expression BETWEEN '$value1' AND  '$value2'";
		return $this;
	}

	private function isReservedWord($word)
	{
		$words = array(
			"ACCESSIBLE",
			"ADD",
			"ALL",
			"ALTER",
			"ANALYZE",
			"AND",
			"AS",
			"ASC",
			"ASENSITIVE",
			"BEFORE",
			"BETWEEN",
			"BIGINT",
			"BINARY",
			"BLOB",
			"BOTH",
			"BY",
			"CALL",
			"CASCADE",
			"CASE",
			"CHANGE",
			"CHAR",
			"CHARACTER",
			"CHECK",
			"COLLATE",
			"COLUMN",
			"CONDITION",
			"CONSTRAINT",
			"CONTINUE",
			"CONVERT",
			"CREATE",
			"CROSS",
			"CURRENT_DATE",
			"CURRENT_TIME",
			"CURRENT_TIMESTAMP",
			"CURRENT_USER",
			"CURSOR",
			"DATABASE",
			"DATABASES",
			"DAY_HOUR",
			"DAY_MICROSECOND",
			"DAY_MINUTE",
			"DAY_SECOND",
			"DEC",
			"DECIMAL",
			"DECLARE",
			"DEFAULT",
			"DELAYED",
			"DELETE",
			"DESC",
			"DESCRIBE",
			"DETERMINISTIC",
			"DISTINCT",
			"DISTINCTROW",
			"DIV",
			"DOUBLE",
			"DROP",
			"DUAL",
			"EACH",
			"ELSE",
			"ELSEIF",
			"ENCLOSED",
			"ESCAPED",
			"EXISTS",
			"EXIT",
			"EXPLAIN",
			"FALSE",
			"FETCH",
			"FLOAT",
			"FLOAT4",
			"FLOAT8",
			"FOR",
			"FORCE",
			"FOREIGN",
			"FROM",
			"FULLTEXT",
			"GENERAL[a]",
			"GRANT",
			"GROUP",
			"HAVING",
			"HIGH_PRIORITY",
			"HOUR_MICROSECOND",
			"HOUR_MINUTE",
			"HOUR_SECOND",
			"IF",
			"IGNORE",
			"IGNORE_SERVER_IDS[b]",
			"IN",
			"INDEX",
			"INFILE",
			"INNER",
			"INOUT",
			"INSENSITIVE",
			"INSERT",
			"INT",
			"INT1",
			"INT2",
			"INT3",
			"INT4",
			"INT8",
			"INTEGER",
			"INTERVAL",
			"INTO",
			"IS",
			"ITERATE",
			"JOIN",
			"KEY",
			"KEYS",
			"KILL",
			"LEADING",
			"LEAVE",
			"LEFT",
			"LIKE",
			"LIMIT",
			"LINEAR",
			"LINES",
			"LOAD",
			"LOCALTIME",
			"LOCALTIMESTAMP",
			"LOCK",
			"LONG",
			"LONGBLOB",
			"LONGTEXT",
			"LOOP",
			"LOW_PRIORITY",
			"MASTER_HEARTBEAT_PERIOD[c]",
			"MASTER_SSL_VERIFY_SERVER_CERT",
			"MATCH",
			"MAXVALUE",
			"MEDIUMBLOB",
			"MEDIUMINT",
			"MEDIUMTEXT",
			"MIDDLEINT",
			"MINUTE_MICROSECOND",
			"MINUTE_SECOND",
			"MOD",
			"MODIFIES",
			"NATURAL",
			"NOT",
			"NO_WRITE_TO_BINLOG",
			"NULL",
			"NUMERIC",
			"ON",
			"OPTIMIZE",
			"OPTION",
			"OPTIONALLY",
			"OR",
			"ORDER",
			"OUT",
			"OUTER",
			"OUTFILE",
			"PRECISION",
			"PRIMARY",
			"PROCEDURE",
			"PURGE",
			"RANGE",
			"READ",
			"READS",
			"READ_WRITE",
			"REAL",
			"REFERENCES",
			"REGEXP",
			"RELEASE",
			"RENAME",
			"REPEAT",
			"REPLACE",
			"REQUIRE",
			"RESIGNAL",
			"RESTRICT",
			"RETURN",
			"REVOKE",
			"RIGHT",
			"RLIKE",
			"SCHEMA",
			"SCHEMAS",
			"SECOND_MICROSECOND",
			"SELECT",
			"SENSITIVE",
			"SEPARATOR",
			"SET",
			"SHOW",
			"SIGNAL",
			"SLOW[d]",
			"SMALLINT",
			"SPATIAL",
			"SPECIFIC",
			"SQL",
			"SQLEXCEPTION",
			"SQLSTATE",
			"SQLWARNING",
			"SQL_BIG_RESULT",
			"SQL_CALC_FOUND_ROWS",
			"SQL_SMALL_RESULT",
			"SSL",
			"STARTING",
			"STRAIGHT_JOIN",
			"TABLE",
			"TERMINATED",
			"THEN",
			"TINYBLOB",
			"TINYINT",
			"TINYTEXT",
			"TO",
			"TRAILING",
			"TRIGGER",
			"TRUE",
			"UNDO",
			"UNION",
			"UNIQUE",
			"UNLOCK",
			"UNSIGNED",
			"UPDATE",
			"USAGE",
			"USE",
			"USING",
			"UTC_DATE",
			"UTC_TIME",
			"UTC_TIMESTAMP",
			"VALUES",
			"VARBINARY",
			"VARCHAR",
			"VARCHARACTER",
			"VARYING",
			"WHEN",
			"WHERE",
			"WHILE",
			"WITH",
			"WRITE",
			"XOR",
			"YEAR_MONTH",
			"ZEROFILL"
		);

		$word = strtoupper(trim($word));
		if (in_array($word, $words))
			return TRUE;
		else
			return FALSE;
	}

}
