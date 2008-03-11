<?php

/**
 * on our way to the storage phase! We shall support the following!
 *
 * sqlite: client data stored in filesystem
 * firebird: database engine storage
 * mssql: database engine storage
 * mysql: database engine storage
 * postgresql: database engine storage
 *
 * odbc: proxy for many of the above
 *
 * @package Data
 */

/**
 * Associative get, returns arrays as $item['column'] instead of $item[index].
 * @todo Don't use defines, defines aren't redeclareable.
 */
define("GET_ASSOC", 1);
/**
 * Both get, returns arrays as $item['column'] as well as $item[index] instead
 * of $item[index].
 * @todo Don't use defines, defines aren't redeclareable.
 */
define("GET_BOTH", 3);

define('DB_MY', 0);
define('DB_OD', 1);

define('ER_NO_SUCH_TABLE', 1146);

/**
 * A generic database interface, currently only supports MySQL apparently.
 */
class Database
{
	/**
	 * A link returned from mysql_connect(), don't worry about it.
	 * @var resource
	 */
	public $link;

	/**
	 * Name of this database, set from constructor.
	 * @var string
	 */
	public $name;

	/**
	 * Type of database this is attached to.
	 * @var int
	 */
	public $type;

	/**
	 * Left quote compatible with whatever database we're sitting on.
	 * @var char
	 */
	public $lq;

	/**
	 * Right quote compatible with whatever server we are sitting on.
	 * @var char
	 */
	public $rq;

	/**
	 * A series of handlers that will be called on when specific actions
	 * take place.
	 * @var array
	 */
	public $Handlers;

	/**
	 * The error handler for when something goes wrong.
	 * @var callback
	 */
	public $ErrorHandler;

	/**
	 * Checks for a mysql error.
	 * @param string $query Query that was attempted.
	 * @param callback $handler Handler to take care of this problem.
	 */
	function CheckMyError($query, $handler)
	{
		if (mysql_errno())
		{
			if (isset($handler))
				if (call_user_func($handler, mysql_errno())) return;
			if (isset($this->Handlers[mysql_errno()]))
				if (call_user_func($this->Handlers[mysql_errno()])) return;
			Error("MySQL Error [".mysql_errno().']: '.mysql_error().
				"<br/>\nQuery: {$query}<br/>\n");
		}
	}

	/**
	 * Checks for an handles an ODBC error generically.
	 * @param string $query Query that was attempted.
	 * @param callback $handler Handler used to process this error.
	 */
	function CheckODBCError($query, $handler)
	{
	}

	/**
	 * Instantiates a new Database object.
	 */
	function Database()
	{
	}

	/**
	 * Opens a connection to a database.
	 * @param string $url Example: mysql://user:pass@host/database
	 */
	function Open($url)
	{
		if (!preg_match('#([^:]+)://([^:]*):(.*)@([^/]*)/(.*)#', $url, $m))
			Error("Invalid url for database.");
		switch ($m[1])
		{
			case 'mysql':
				$this->ErrorHandler = array($this, 'CheckMyError');
				$this->link = mysql_connect($m[4], $m[2], $m[3]);
				if (!$this->link)
					Error("Unable to connect to mysql at {$m[4]}.<br/>\n");
				mysql_select_db($m[5], $this->link);
				$this->type = DB_MY;
				$this->lq = $this->rq = '`';
				break;
			case 'odbc':
				$this->ErrorHandler = array($this, 'CheckODBCError');
				$this->link = odbc_connect($m[5], $m[2], $m[3]);
				if (!$this->link) die("ODBC Error on connect: ".
					odbc_errormsg($this->link));
				$this->type = DB_OD;
				$this->lq = '[';
				$this->rq = ']';
				break;
			default:
				Error("Invalid database on Database creation.");
				break;
		}
		call_user_func($this->ErrorHandler, null, null);
		$this->name = $m[5];
	}

	/**
	 * Perform a manual query on the associated database, try to use this sparingly because
	 * we will be moving to abstract database support so it'll be a load parsing the sql-like
	 * query and translating.
	 * @param string $query The actual SQL formatted query.
	 * @param bool $silent Should we return an error?
	 * @param callback $handler Handler in case something goes wrong.
	 * @return resource Query result object.
	 */
	function Query($query, $silent = false, $handler = null)
	{
		if (!isset($this->type)) Error("Database has not been opened.");
		if (isset($GLOBALS['debug'])) varinfo($query);
		switch ($this->type)
		{
			case DB_MY:
				$res = mysql_query($query, $this->link);
				call_user_func($this->ErrorHandler, $query, $handler);
				break;
			case DB_OD:
				$res = odbc_exec($this->link, $query);
				if (odbc_error())
					Error("Query: $query<br/>\nODBC Error: ".odbc_error());
				break;
		}
		return $res;
	}

	/**
	* Quickly create this database
	*/
	function Create()
	{
		mysql_query("CREATE DATABASE {$this->name}", $this->link);
		if (mysql_error()) echo "Create(): " . mysql_error() . "<br>\n";
	}

	/**
	* Drop a child table.
	* @param string $name The name of the table to make a boo boo to.
	*/
	function DropTable($name)
	{
		$this->Query("DROP TABLE $name");
	}

	/**
	* Drop this whole database, I suggest you stay away from this command unless you really
	* mean it cause it doesn't kid around, and mysql is pretty obedient.
	*/
	function Drop()
	{
		mysql_query("DROP DATABASE {$this->name}");
		if (mysql_error()) echo "Drop(): " . mysql_error() . "<br>\n";
	}

	/**
	 * Ensure this database exists, according to it's specified schema.
	 *
	 */
	function CheckInstall()
	{
		mysql_select_db($this->name, $this->link);
		if (mysql_error())
		{
			echo "Database: Could not locate database, installing...<br/>\n";
			mysql_query("CREATE DATABASE {$this->name}");
		}
	}

	/**
	 * Returns the last unique ID that was inserted.
	 *
	 * @return mixed
	 */
	function GetLastInsertID()
	{
		if ($this->type == DB_MY) return mysql_insert_id($this->link);
		return 0;
	}
}

/**
 * Removes quoting from a database field to perform functions and
 * such.
 * @param string $data Information that will not be quited.
 * @return array specifying that this string shouldn't be quoted.
 */
function DeString($data) { return array("destring", $data); }
/**
 * Returns the proper format for DataSet to generate the current time.
 * @return array This column will get translated into the current time.
 */
function DBNow() { return array("now"); }

/**
 * Enter description here...
 *
 */
class Relation
{
	/**
	 * Associated dataset.
	 * @var DataSet
	 */
	public $ds;

	/**
	 * Name of the column that is the primary key for the parent of this
	 * relation.
	 * @var string
	 */
	public $parent_key;

	/**
	 * Name of the column that is the primary key for the child of this
	 * relation.
	 * @var string
	 */
	public $child_key;

	/**
	 * Prepares a relation for database association.
	 *
	 * @param DataSet $ds DataSet for this child.
	 * @param string $parent_key Column name of the parent key of $ds.
	 * @param string $child_key Column that references the parent.
	 * @example doc\examples\dataset.php
	 */
	function Relation($ds, $parent_key, $child_key)
	{
		$this->ds = $ds;
		$this->parent_key = $parent_key;
		$this->child_key = $child_key;
	}
}

/**
 * A join specified singly or as an array to objects or methods like
 * DataSet.Get.
 *
 * @see DataSet.Get
 */
class Join
{
	/**
	 * The associated dataset in this join.
	 *
	 * @see DataSet.Get
	 * @var DataSet
	 */
	public $DataSet;

	/**
	 * The condition of this join, For example: 'child.parent = parent.id'.
	 *
	 * @see DataSet.Get
	 * @var string
	 */
	public $Condition;

	/**
	 * The type of this join, off hand I can think of three, 'LEFT JOIN', 'INNER JOIN'
	 * and 'JOIN'.
	 *
	 * @see DataSet.Get
	 * @var string
	 */
	public $Type;

	/**
	 * Unique identifier for this join to associate all the columns.
	 * @var string
	 */
	public $Shortcut;

	/**
	 * Creates a new Join object that will allow DataSet to identify the type
	 * and context of where, when and how to use a join when it is needed. This
	 * is used when you call DataSet.Get().
	 *
	 * @param DataSet $dataset Target DataSet to join.
	 * @param string $condition Context of the join.
	 * @param string $type Type of join, 'JOIN', 'LEFT JOIN' or 'INNER JOIN'.
	 * @param string $shortcut Specify an easier name for this joining table.
	 * @see DataSet.Get
	 */
	function Join($dataset, $condition, $type = 'JOIN', $shortcut = null)
	{
		$this->DataSet = $dataset;
		$this->Condition = $condition;
		$this->Type = $type;
		$this->Shortcut = $shortcut;
	}
}

/**
 * A general dataset, good for binding to a database's table.
 * Used to generically retrieve, store, update and delete
 * data quickly and easily, given the table matches a few
 * general guidelines, for one it must have an auto_increment
 * primary key for it's first field named 'id' so it can
 * easily locate fields.
 *
 * @example doc\examples\dataset.php
 */
class DataSet
{
	/**
	 * Associated database.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Name of the table that this DataSet is associated with.
	 *
	 * @var string
	 */
	public $table;
	/**
	 * Array of Relation objects that make up associated children of the table
	 * this DataSet is associated with.
	 *
	 * @var array
	 */
	public $children;
	/**
	 * Name of the column that holds the primary key of the table that this
	 * DataSet is associated with.
	 *
	 * @var string
	 */
	public $id;
	/**
	 * A shortcut name for this dataset for use in SQL queries.
	 * Eg. Translating AReallyLongName to arln for later using a join
	 * clause suggesting arln.parent = parent.id.
	 *
	 * @var string
	 */
	public $Shortcut;

	//Display Related

	/**
	 * Array of DisplayColumn objects that this DataSet is associated with.
	 *
	 * @var array
	 * @see EditorData
	 */
	public $DisplayColumns;

	/**
	 * Array of field information that this DataSet is associated with.
	 *
	 * @var array
	 * @see EditorData
	 */
	public $FieldInputs;

	/**
	 * A single or mutliple validations for the associated form.
	 *
	 * @var mixed
	 */
	public $Validation;

	/**
	 * Readable description of this dataset (eg. User instead of mydb_user)
	 *
	 * @var string
	 */
	public $Description;

	/**
	 * Handler for errors in case something goes wrong.
	 * @var callback
	 */
	public $ErrorHandler;

	/**
	 * Which function is used to actually fetch the data, depending on the
	 * type of the underlying database.
	 *
	 * @var string
	 */
	private $func_fetch;

	/**
	 * Initialize a new CDataSet binded to $table in $db.
	 * @param Database $db Database A Database object to bind to.
	 * @param string $table Specifies the name of the table in $db to bind to.
	 * @param string $id Name of the column with the primary key on this table.
	 */
	function DataSet($db, $table, $id = 'id')
	{
		switch ($db->type)
		{
			case DB_OD:
				$this->func_fetch = 'odbc_fetch_array';
				$this->func_rows = 'odbc_num_rows';
				break;
			case DB_MY:
				$this->func_fetch = 'mysql_fetch_array';
				$this->func_rows = 'mysql_affected_rows';
				break;
		}
		$this->database = $db;
		$this->table = $table;
		$this->id = $id;
	}

	/**
	 * Adds a child relation to this DataSet for recursion.
	 *
	 * @param Relation $relation
	 * @see EditorData
	 */
	function AddChild($relation)
	{
		$this->children[] = $relation;
	}

	/**
	 * Gets associated SQL text for a clause naming specific columns.
	 *
	 * @param array $cols
	 * @return string
	 * @access private
	 */
	function ColsClause($cols)
	{
		if (!empty($cols))
		{
			$ret = '';
			$ix = 0;
			foreach ($cols as $name => $val)
			{
				if ($ix++ > 0) $ret .= ",\n";
				if (is_array($val) && $val[0] = 'destring')
					$ret .= ' '.$val[1];
				else
					$ret .= ' '.$this->QuoteTable($val);
				if (!is_numeric($name)) $ret .= ' AS '.$this->QuoteTable($name);
			}
			return $ret;
		}
		return ' *';
	}

	/**
	 * Quotes a table properly depending on the data source.
	 *
	 * @param string $name
	 * @return string Quoted name.
	 * @todo Rename this to QuoteName
	 */
	function QuoteTable($name)
	{
		$lq = $this->database->lq;
		$rq = $this->database->rq;
		if (strpos($name, '.') > -1)
			return preg_replace('#([^(]+)\.([^ )]+)#',
			"{$lq}\\1{$rq}.{$lq}\\2{$rq}", $name);
		return "{$lq}{$name}{$rq}";
	}

	/**
	 * Gets a WHERE clause in SQL format.
	 *
	 * @param array $match
	 * @return string
	 */
	function WhereClause($match, $start = ' WHERE')
	{
		if (isset($match))
		{
			if (is_array($match))
			{
				$ret = "\n".$start;
				$ix = 0;
				foreach ($match as $col => $val)
				{
					if ($ix++ > 0) $ret .= ' AND';
					if (is_string($col)) //array('col' => 'value')
					{
						if (is_array($val) && $val[0] = 'destring')
							$ret .= ' '.$this->QuoteTable($col)." = {$val[1]}";
						else
							$ret .= ' '.$this->QuoteTable($col)." = '{$val}'";
					}
					else //"col = 'value'"
					{
						$ret .= " {$val}";
					}
				}
				return $ret;
			}
			else return "\n".$start.' '.$match;
		}
		return null;
	}

	/**
	 * Gets a JOIN clause in SQL format.
	 *
	 * @param array $joining
	 * @return string
	 */
	static function JoinClause($joining)
	{
		if (isset($joining))
		{
			$ret = '';
			if (is_array($joining))
			{
				foreach ($joining as $table => $on)
				{
					if (is_object($on))
					{
						$ret .= "\n {$on->Type} `{$on->DataSet->table}`";
						if (isset($on->Shortcut)) $ret .= " {$on->Shortcut}";
						else if (isset($on->DataSet->Shortcut)) $ret .= " {$on->DataSet->Shortcut}";
						if (is_array($on->Condition))
							$ret .= " ON ({$on->Condition[0]} = {$on->Condition[1]})";
						else $ret .= " ON {$on->Condition}";
					}
					else
						$ret .= "\n LEFT JOIN `{$table}` ON({$on})";
				}
			}
			return $ret;
		}
		return null;
	}

	/**
	 * Gets an ORDER BY clause in SQL format depending on the datasource.
	 *
	 * @param array $sorting
	 * @return string
	 */
	function OrderClause($sorting)
	{
		if (isset($sorting))
		{
			$ret = "\n ORDER BY";
			if (is_array($sorting))
			{
				$ix = 0;
				foreach ($sorting as $col => $dir)
				{
					if ($ix++ > 0) $ret .= ',';
					$ret .= " {$col} {$dir}";
				}
			}
			else $ret .= " $sorting";
			return $ret;
		}
		return null;
	}

	/**
	 * Gets a LIMIT clause in SQL format depending on data source.
	 *
	 * @param array $amount
	 * @return string
	 */
	static function AmountClause($amount)
	{
		if (isset($amount))
		{
			$ret = ' LIMIT';
			if (is_array($amount)) $ret .= " {$amount[0]}, {$amount[1]}";
			else $ret .= " 0, {$amount}";
			return $ret;
		}
		return null;
	}

	/**
	 * Gets a GROUP BY clause for grouping sets of items into a single row and unlocks
	 * the almighty magical aggrigation functions.
	 *
	 * @param string $group Name of table and column to group by.
	 * @return string
	 */
	function GroupClause($group)
	{
		if (isset($group))
		return "\n GROUP BY {$group}";
		return null;
	}

	/**
	 * Returns a series of name to value pairs in SQL format depending on the
	 * data source.
	 *
	 * @param array $values eg: array('col1' => 'val1')
	 * @return string Proper set.
	 */
	function GetSetString($values)
	{
		$ret = null;
		if (!empty($values))
		{
			$x = 0;
			foreach ($values as $key => $val)
			{
				if (empty($val)) continue;
				if ($x++ > 0) $ret .= ", ";
				if (is_array($val))
				{
					if ($val[0] == "destring")
						$ret .= $this->QuoteTable($key)." = {$val[1]}";
				}
				else $ret .= $this->QuoteTable($key)." = '".mysql_real_escape_string($val)."'";
			}
		}
		return $ret;
	}

	/**
	 * Inserts a row into the associated table with the passed array.
	 * @param array $columns An array of columns. If you wish to use functions
	 * @param bool $update_existing Whether to update the existing values
	 * by unique keys, or just to add ignoring keys otherwise.
	 * @return int ID of added row.
	 */
	function Add($columns, $update_existing = false)
	{
		$lq = $this->database->lq;
		$rq = $this->database->rq;

		$query = "INSERT INTO {$lq}{$this->table}{$rq} (";
		foreach (array_keys($columns) as $ix => $key)
		{
			if (!empty($columns[$key]))
			{
				if ($ix != 0) $query .= ", ";
				$query .= $this->QuoteTable($key);
			}
		}
		$query .= ") VALUES(";
		$ix = 0;
		foreach ($columns as $key => $val)
		{
			//destring('value') for functions and such.
			if (empty($val)) continue;
			if ($ix > 0) $query .= ', ';
			if (is_array($val))
			{
				if ($val[0] == "destring") $query .= $val[1];
			}
			else $query .= "'".addslashes($val)."'";
			$ix++;
		}
		$query .= ")";
		if ($update_existing)
		{
			$query .= " ON DUPLICATE KEY UPDATE ".$this->GetSetString($columns);
		}
		$this->database->Query($query);
		return $this->database->GetLastInsertID();
	}

	/**
	 * Quick way to add a series of values without associating or knowing the
	 * column names.
	 * @param array $columns Values to insert, eg. array(null, DBNow(),
	 * 'Hello');
	 * @param bool $update_existing Whether or not to update if duplicate
	 * keys exist.
	 * @return mixed Identifier of the last inserted item.
	 */
	function AddSeries($columns, $update_existing = false)
	{
		$lq = $this->database->lq;
		$rq = $this->database->rq;

		$query = "INSERT INTO {$lq}{$this->table}{$rq} VALUES(";
		$ix = 0;
		foreach ($columns as $key => $val)
		{
			//destring('value') for functions and such.
			if (is_array($val))
			{
				if ($val[0] == "destring") $query .= $val[1];
			}
			else if (empty($val)) $query .= 'NULL';
			else $query .= "'".paslash($val)."'";
			if ($ix < count($columns)-1) $query .= ", ";
			$ix++;
		}
		$query .= ")";
		$this->database->Query($query);
		return $this->database->GetLastInsertID();
	}

	/**
	 * Get a set of data given the specific arguments.
	 * @param array $match Column to Value to match for in a WHERE statement.
	 * @param array $sort Column to Direction array.
	 * @param array $filter Column to Value array.
	 * @param array $joins Table to ON Expression values.
	 * @param array $columns Specific columns to return: array('col1', 'col2' => 'c2');
	 * @param string $group Grouping, eg: 'product'.
	 * @param int $args passed to mysql_fetch_array.
	 * @return array Array of items selected.
	 */
	function Get(
		$match = null,
		$sort = null,
		$filter = null,
		$joins = null,
		$columns = null,
		$group = null,
		$args = GET_BOTH)
	{
		//Error handling
		if (!isset($this->database))
		{
			Error("<br />What: The database is not set.
			<br />Who: DataSet::Get() on {$this->table}
			<br />Why: You may have specified an incorrect database in
			construction.");
		}

		$lq = $this->database->lq;
		$rq = $this->database->rq;

		//Prepare Query
		$query = 'SELECT';
		$query .= $this->ColsClause($columns);
		$query .= "\n FROM {$lq}{$this->table}{$rq}";
		if (isset($this->Shortcut)) $query .= " `{$this->Shortcut}`";
		$query .= $this->JoinClause($joins);
		$query .= $this->WhereClause($match);
		$query .= $this->GroupClause($group);
		$query .= $this->OrderClause($sort);
		$query .= $this->AmountClause($filter);

		//Execute Query
		$rows = $this->database->Query($query, !empty($this->ErrorHandler),
			$this->ErrorHandler);

		//Prepare Data
		//if (mysql_affected_rows() < 1) return null;
		$items = null;

		$a = null;
		if ($this->database->type == 'mysql')
		{
			$a = MYSQL_BOTH;
			if ($args == GET_ASSOC) $a = MYSQL_ASSOC;
		}

		while (($row = call_user_func($this->func_fetch, $rows, $a)))
		{
			$newrow = array();
			foreach ($row as $key => $val)
			{
				$newrow[$key] = psslash($val);
			}
			$items[] = $newrow;
		}
		return $items;
	}

	/**
	 * Return a single item from this dataset
	 * @param array $match Passed on to WhereClause.
	 * @param array $joins Passed on to JoinClause.
	 * @param int $args Arguments passed to fetch_array.
	 * @return array A single serialized row matching $match or null if not found.
	 */
	function GetOne($match, $joins = null, $cols = null, $group = null, $args = GET_BOTH)
	{
		$data = $this->Get($match, null, null, $joins, $cols, $group, $args);
		if (isset($data)) return $data[0];
		return $data;
	}

	/**
	 * Returns the specified column from the first result of the specified query.
	 *
	 * @param array $match
	 * @param string $col
	 * @return mixed
	 */
	function GetScalar($match, $col)
	{
		if (is_array($col) && $col[0] == 'destring') { $lq = $rq = ''; $col = $col[1]; }
		else $lq = $rq = '`';
		$query = "SELECT $lq$col$rq FROM `{$this->table}`".$this->WhereClause($match);
		$cols = $this->database->Query($query);
		$data = mysql_fetch_array($cols);
		return $data[0];
	}

	/**
	 * Returns every row unless limit is specified without a specific sort column or order
	 * @param int $limit Maximum amount of rows to return.
	 * @param int $args Arguments to be passed to mysql_fetch_array (defaults to MYSQL_NUM)
	 * @return array An array of results from the query specified.
	 */
	function GetAll($limit = NULL, $args = GET_BOTH)
	{
		return $this->Get(null, null, $limit, null, $args);
	}

	/**
	 * Returns every database  row from the database sorting by the given column
	 * @param string $column Name of the table column to sort by.
	 * @param string $order Either "ASC" or "DESC" I believe.
	 * @param int $start Result index to being at.
	 * @param int $amount Result count to return.
	 * @return array An array of results from the query specified or null if none.
	 */
	function GetAllSort($column, $order = "ASC", $start = NULL,
		$amount = NULL)
	{
		$query = "SELECT * FROM {$this->table} ORDER BY $column $order";
		if (isset($start))
		{
			$query .= " LIMIT $start";
			if (isset($amount)) $query .= ", $amount";
		}
		$rows = $this->database->Query($query);
		if (mysql_affected_rows() < 1) return null;
		$this->items = array();
		while (($row = mysql_fetch_array($rows)))
		{
			$this->items[] = $row;
		}
		return $this->items;
	}

	/**
	 * Returns all rows that match $columns LIKE $phrase
	 *
	 * @param array $columns
	 * @param string $phrase
	 * @param int $args
	 * @param int $start Where to start results for pagination.
	 * @param int $limit Limit of items to return for pagination.
	 * @return array
	 */
	function GetSearch($columns, $phrase, $start = 0, $limit = null,
		$sort = null, $filter = null)
	{
		$newphrase = str_replace("'", '%', stripslashes($phrase));
		$newphrase = str_replace(' ', '%', $newphrase);
		$query = "SELECT ".$this->ColsClause($columns)." FROM `{$this->table}`";
		$ix = 0;
		if (!empty($columns))
		{
			$query .=  ' WHERE (';
			foreach ($columns as $col)
			{
				if ($ix++ > 0) $query .= " OR";
				$query .= ' '.$this->QuoteTable($col)." LIKE '%{$newphrase}%'";
			}
			$query .= ')';
		}
		if ($filter != null) $query .= $this->WhereClause($filter, ' AND');
		if ($sort != null) $query .= DataSet::OrderClause($sort);
		if ($limit != null) $query .= " LIMIT {$start}, {$limit}";

		return $this->GetCustom($query);
	}

	/**
	 * Query the database manually, not suggested to be used as it will be
	 * very cpu intensive when it evaluates the query itself.
	 * @param string $query The query to perform, including the table name and everything.
	 * @param bool $silent Should we output an error?
	 * @param int $args Arguments to pass to the fetch.
	 * @return array The serialized database rows.
	 */
	function GetCustom($query, $silent = false, $args = GET_BOTH)
	{
		$rows = $this->database->Query($query, $silent, $this->ErrorHandler);
		if (!is_resource($rows)) return $rows;
		$ret = array();
		$f = $this->func_fetch;
		while (($row = $f($rows)))
		{
			$newrow = array();
			foreach ($row as $key => $val)
			{
				$newrow[$key] = stripslashes($val);
			}
			$ret[] = $newrow;
		}
		return $ret;
	}

	/**
	 * Return the total amount of rows in this associated table.
	 * @param array $match Passed off to WhereClause.
	 * @param bool $silent Good for checking an installation.
	 * @return int The actual numeric count.
	 */
	function GetCount($match = null, $silent = false)
	{
		$query = "SELECT COUNT(*) FROM `{$this->table}`";
		$query .= $this->WhereClause($match);
		$val = $this->GetCustom($query, $silent);
		return $val[0][0];
	}

	/**
	 * Updates the column in the associated table with the given id.
	 * @param array $match Passed on to WhereClause.
	 * @param array $values Data to be updated.
	 * values to update using array("column1" => "value", "column2"
	 * => "value2").
	 */
	function Update($match, $values)
	{
		$query = "UPDATE {$this->table} SET ".$this->GetSetString($values);
		$this->database->Query($query.$this->WhereClause($match));
	}

	/**
	 * Swaps two items in the database.
	 *
	 * @param array $smatch
	 * @param array $dmatch
	 * @param mixed $pkey
	 */
	function Swap($smatch, $dmatch, $pkey)
	{
		//Grab all the source items that are going to be swapped.
		$sitems = $this->Get($smatch, null, null, null, null, null, GET_ASSOC);
		$sitem = $sitems[0];
		//$sitems = $this->database->query("SELECT * FROM {$this->table}".$this->WhereClause($smatch));
		//$sitem = call_user_func($this->func_fetch, $sitems);

		//Grab all the destination items that are going to be swapped.
		//$ditems = $this->database->query("SELECT * FROM {$this->table}".$this->WhereClause($dmatch));
		//$ditem = call_user_func($this->func_fetch, $ditems);
		$ditems = $this->Get($dmatch, null, null, null, null, null, GET_ASSOC);
		$ditem = $ditems[0];

		//If we have children relations, it suddenly gets complicated.
		if (!empty($this->children))
		{
			//Create a temporary table to store the remainder children ids for
			//moving back to the source after the destination is copied.
			$this->database->query('CREATE TEMPORARY TABLE IF NOT EXISTS __TEMP (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY);');

			foreach ($this->children as $child)
			{
				//Copy Source children into Temp
				$query = "INSERT INTO `__TEMP`
					SELECT `{$child->ds->id}`
					FROM `{$child->ds->table}`
					WHERE `{$child->child_key}` = {$smatch[$child->parent_key]}";
				$child->ds->database->query($query);

				//Move Destination children into Source parent.
				$child->ds->Update(
					array($child->child_key => $ditem[$child->parent_key]),
					array($child->child_key => $sitem[$child->parent_key]));

				//Move Temp children into Destination parent.
				$query = "UPDATE `{$child->ds->table}`
					JOIN `__TEMP`
					SET {$child->child_key} = {$ditem[$child->parent_key]}
					WHERE `__TEMP`.`{$child->ds->id}` = `{$child->ds->table}`.`{$child->parent_key}`";
				$child->ds->database->query($query);
			}

			$child->ds->database->query("DROP TABLE IF EXISTS `__TEMP`");
		}

		//Pop off the ids, so they don't get changed, never want to change
		//these for some reason according to mysql people.
		unset($sitem[$this->id], $ditem[$this->id]);

		//Update source with dest and vice versa.
		$this->Update($smatch, $ditem);
		$this->Update($dmatch, $sitem);
	}

	/**
	* Removes all items that match the specified value in the specified column in the
	* associated table.
	* @param array $match Passed on to WhereClause to decide deleted data.
	*/
	function Remove($match)
	{
		$matches = array();
		$query = "DELETE FROM {$this->table}";
		$query .= $this->WhereClause($match);
		$this->database->Query($query);

		//Prune off all children...
		if (!empty($this->children))
		foreach ($this->children as $ix => $child)
		{
			$query = "DELETE c FROM {$child->ds->table} c LEFT JOIN {$this->table} p ON(c.{$child->child_key} = p.{$child->parent_key}) WHERE c.{$child->child_key} != 0 AND p.{$child->parent_key} IS NULL";
			$this->database->Query($query);
		}
	}

	function Truncate()
	{
		$this->database->Query('TRUNCATE '.$this->table);
	}
}

function BuildTree($items, $parent, $assoc = null)
{
	$flats = LinkList($items, $parent, $assoc);

	foreach ($flats as $id => $f)
	{
		$p = $f->data[$assoc];
		if (isset($flats[$p]))
		{
			$flats[$p]->children = $f;
			$f->parents[$p] = $flats[$p];
		}
		else $tree[$f->id] = $f;
	}
	return $tree;
}

function LinkList($items, $parent, $assoc = null)
{
	//Build Flat
	foreach ($items as $i)
	{
		$tn = new TreeNode($i);
		$tn->id = $i[$parent];
		$ret[$i[$parent]] = $tn;
	}
	foreach ($ret as $id => $i)
	{
		$p = $i->data[$assoc];
		echo "Parent: {$p}<br/>\n";
		if (isset($ret[$p]))
		{
			$ret[$p]->children[$id] = $i;
			$i->parent = $ret[$p];
		}
	}
	return $ret;
}

?>