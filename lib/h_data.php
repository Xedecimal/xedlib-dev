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

define('DB_MY', 0); //MySQL
define('DB_MI', 1); //MySQLi
define('DB_OD', 2); //ODBC
define('DB_SL', 3); //SQLite

define('ER_NO_SUCH_TABLE', 1146);
define('ER_INVALID_LOGIN', 9999);

define('SQLOPT_NONE', 0);
define('SQLOPT_QUOTE', 1);
define('SQLOPT_TQUOTE', 2);
define('SQLOPT_UNQUOTE', 4);

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

	function CheckMiError($query, $handler)
	{
		if (mysqli_errno($this->link))
		{
			if (!empty($handler))
				if (call_user_func($handler, mysqli_errno($this->link))) return;
			if (isset($this->Handlers[mysqli_errno($this->link)]))
				if (call_user_func($this->Handlers[mysqli_errno($this->link)])) return;
			Error('MySQLi Error ['.mysqli_errno($this->link).']: '
				.mysqli_error($this->link)."<br />\nQuery: {$query}<br/>\n");
		}
	}

	/**
	 * Checks for a mysql error.
	 * @param string $query Query that was attempted.
	 * @param callback $handler Handler to take care of this problem.
	 */
	function CheckMyError($query, $handler)
	{
		if (mysql_errno($this->link))
		{
			if (!empty($handler))
				if (call_user_func($handler, mysql_errno($this->link))) return;
			if (isset($this->Handlers[mysql_errno($this->link)]))
				if (call_user_func($this->Handlers[mysql_errno($this->link)])) return;

			Error('MySQL Error ['.mysql_errno($this->link).']: '.mysql_error($this->link).
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

	function CheckSQLiteError($query, $handler)
	{
		if (sqlite_last_error($this->link))
		{
			echo "Sqlite Error on: {$query}";
		}
	}

	/**
	 * Instantiates a new Database object.
	 */
	function Database()
	{
	}

	function Escape($val)
	{
		switch ($this->type)
		{
			case DB_MI:
				return mysqli_real_escape_string($this->link, $val);
			case DB_MY:
				return mysql_real_escape_string($val, $this->link);
			case DB_SL:
				return sqlite_escape_string($val);
			default:
				return addslashes($val);
		}
	}

	/**
	 * Opens a connection to a database.
	 * @param string $url Example: mysql://user:pass@host/database
	 */
	function Open($url)
	{
		$m = null;
		if (!preg_match('#([^:]+)://(([^:]*):*(.*)@|)([^/]*)/*(.*)#', $url, $m))
			Error("Invalid url for database.");

		switch ($m[1])
		{
			case 'mysqli':
				$this->ErrorHandler = array($this, 'CheckMiError');
				$this->func_aff = 'mysqli_affected_rows';
				$this->link = mysqli_connect($m[5], $m[3], $m[4]);
				mysqli_select_db($this->link, $m[6]);
				$this->type = DB_MI;
				$this->lq = $this->rq = '`';
				break;
			case 'mysql':
				$this->ErrorHandler = array($this, 'CheckMyError');
				$this->func_aff = 'mysql_affected_rows';
				if (!$this->link = mysql_connect($m[5], $m[3], $m[4], true))
					return false;
				mysql_select_db($m[6], $this->link);
				$this->type = DB_MY;
				$this->lq = $this->rq = '`';
				break;
			case 'odbc':
				$this->ErrorHandler = array($this, 'CheckODBCError');
				$this->link = odbc_connect($m[5], $m[3], $m[4]);
				$this->type = DB_OD;
				$this->lq = '[';
				$this->rq = ']';
				break;
			case 'sqlite':
				$this->ErrorHandler = array($this, 'CheckSQLiteError');
				$this->func_aff = 'sqlite_num_rows';
				$this->link = sqlite_open($m[5]);
				$this->type = DB_SL;
				break;
			default:
				Error("Invalid database type.");
				break;
		}
		call_user_func($this->ErrorHandler, null, null);
		$this->name = $m[6];
	}

	/**
	 * Perform a manual query on the associated database, try to use this sparingly because
	 * we will be moving to abstract database support so it'll be a load parsing the sql-like
	 * query and translating.
	 * @param string $query The actual SQL formatted query.
	 * @param callback $handler Handler in case something goes wrong.
	 * @return resource Query result object.
	 */
	function Query($query, $handler = null)
	{
		if (!isset($this->type)) Error("Database has not been opened.");
		Trace($query);
		switch ($this->type)
		{
			case DB_MI:
				$res = mysqli_query($this->link, $query);
				break;
			case DB_MY:
				$res = mysql_query($query, $this->link);
				break;
			case DB_OD:
				$res = odbc_exec($this->link, $query);
				break;
			case DB_SL:
				$res = sqlite_query($this->link, $query);
				break;
		}
		call_user_func($this->ErrorHandler, $query, $handler);
		return $res;
	}

	function Queries($query)
	{
		foreach (explode(';', $query) as $q) $this->Query($q);
	}

	/**
	* Quickly create this database
	*/
	function Create()
	{
		mysql_query("CREATE DATABASE {$this->name}", $this->link);
		if (mysql_error($this->link)) echo "Create(): " . mysql_error($this->link) . "<br>\n";
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
		mysql_query("DROP DATABASE {$this->name}", $this->link);
		if (mysql_error($this->link)) echo "Drop(): " . mysql_error($this->link) . "<br>\n";
	}

	/**
	 * Ensure this database exists, according to it's specified schema.
	 *
	 */
	function CheckInstall()
	{
		mysql_select_db($this->name, $this->link);
		if (mysql_error($this->link))
		{
			echo "Database: Could not locate database, installing...<br/>\n";
			mysql_query("CREATE DATABASE {$this->name}", $this->link);
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
		if ($this->type == DB_MI) return mysqli_insert_id($this->link);
		if ($this->type == DB_SL) return sqlite_last_insert_rowid($this->link);
		return 0;
	}
}

/**
 * Removes quoting from a database field to perform functions and
 * such.
 * @param string $data Information that will not be quited.
 * @return array specifying that this string shouldn't be quoted.
 */
function SqlUnquote($data) { return array('val' => $data, 'opt' => SQLOPT_UNQUOTE); }
function SqlBetween($from, $to) { return array('cmp' => 'BETWEEN', 'opt' => SQLOPT_UNQUOTE, 'val' => "'$from' AND '$to'"); }
function SqlIs($val) { return array('cmp' => 'IS', 'opt' => SQLOPT_UNQUOTE, 'val' => $val); }
function SqlNot($val) { return array('cmp' => '!=', 'val' => $val); }
function SqlAnd($val) { return array('inc' => 'AND', 'val' => $val); }
function SqlOr($val)
{
	if (is_array($val)) { $val['inc'] = 'OR'; return $val; }
	else return array('inc' => 'OR', 'val' => $val);
}
function SqlLess($val) { return array('cmp' => '<', 'val' => $val); }
function SqlDistinct($val) { return array('cmp' => 'DISTINCT', 'val' => $val); }
function SqlCount($val) { return array('val' => 'COUNT('.$val.')', 'opt' => SQLOPT_UNQUOTE); }
function SqlLike($val) { return array('val' => $val, 'cmp' => 'LIKE'); }
function SqlIn($vals) { return array('val' => 'IN('.$vals.')', 'opt' => SQLOPT_UNQUOTE, 'cmp' => ''); }

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

	public $joins;

	/**
	 * Initialize a new CDataSet binded to $table in $db.
	 * @param Database $db Database A Database object to bind to.
	 * @param string $table Specifies the name of the table in $db to bind to.
	 * @param string $id Name of the column with the primary key on this table.
	 */
	function DataSet($db, $table, $id = 'id')
	{
		if (empty($db)) return;
		switch ($db->type)
		{
			case DB_MI:
				$this->func_fetch = 'mysqli_fetch_array';
				$this->func_rows = 'mysqli_affected_rows';
				break;
			case DB_MY:
				$this->func_fetch = 'mysql_fetch_array';
				$this->func_rows = 'mysql_affected_rows';
				break;
			case DB_SL:
				$this->func_fetch = 'sqlite_fetch_array';
				$this->func_rows = 'sqlite_num_rows';
				break;
			case DB_OD:
				$this->func_fetch = 'odbc_fetch_array';
				$this->func_rows = 'odbc_num_rows';
				break;
		}
		$this->database = $db;
		$this->table = $table;
		$this->id = $id;
		$this->joins = array();
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

	function AddJoin($join)
	{
		$this->joins[] = $join;
	}

	/**
	 * Gets a WHERE clause in SQL format.
	 *
	 * @param array $match
	 * @return string
	 */
	function WhereClause($match, $start = ' WHERE ')
	{
		$ix = 0;
		$ret = null;

		if (!empty($match))
		{
			if (is_array($match))
			{
				foreach ($match as $col => $val)
				{
					if ($ix++ > 0)
					{
						if (is_array($val) && isset($val['inc']))
							$ret .= ' '.$val['inc'].' ';
						else
							$ret .= ' AND ';
					}

					//array('col' => 'value')

					if (is_string($col))
					{
						if (is_array($val))
						{
							$ret .= $this->QuoteTable($col);
							$ret .= isset($val['cmp'])?' '.$val['cmp'].' ':' = ';
							$ret .= $this->ProcessVal($val);
						}
						else
							$ret .= "{$col} = ".$this->ProcessVal($val);
					}

					// array('val')

					else //"col = 'value'"
						$ret .= " {$val}";

					$skip = false;
				}
				return "\n".$start.' '.$ret;
			}
			else return "\n".$start.' '.$match;
		}
	}

	function SetClause($values, $start = ' SET ')
	{
		if (empty($values)) return;

		$ret = '';
		$ix = 0;
		foreach ($values as $k => $v)
		{
			$ret .= $ix++ > 0?', ':null;
			$ret .= $this->QuoteTable($k).' = '.$this->ProcessVal($v);
		}
		return $start.$ret;
	}

	/**
	 * Gets a JOIN clause in SQL format.
	 *
	 * @param array $joining
	 * @return string
	 */
	static function JoinClause($joining, $add = null)
	{
		if (isset($joining) || !empty($add))
		{
			$ret = '';

			if (!empty($add))
			{
				if (is_array($joining))
					$joining = array_merge($add, $joining);
				else $joining = $add;
			}

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
		if (!empty($sorting))
		{
			$ret = "\n ORDER BY";
			if (is_array($sorting))
			{
				$ix = 0;
				foreach ($sorting as $col => $dir)
				{
					if ($ix++ > 0) $ret .= ',';
					if (!is_numeric($col)) $ret .= " {$col} {$dir}";
					else $ret .= " $dir";
				}
			}
			else $ret .= " $sorting";
			return $ret;
		}
		else if (!empty($this->order)) return $this->OrderClause($this->order);
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
		if (!empty($amount))
		{
			$ret = "\n LIMIT";
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
	function GetColVals($values,
		$default = null,
		$start = ' SET ',
		$cvsep = ' = ',
		$vsep = ', ',
		$opts = SQLOPT_QUOTE)
	{
		if (!empty($values))
		{
			$found = false;
			$ret = $start;
			$x = 0;
			foreach ($values as $key => $val)
			{
				if (!isset($val)) continue;
				$found = true;
				if ($x++ > 0) $ret .= isset($val['inc'])?$val['inc']:$vsep;
				if (is_numeric($key))
				{
					$ret .= $this->QuoteTable($val).$vsep;
					continue;
				}
				else $ret .= $this->QuoteTable($key);
				$ret .= $cvsep;

				$ret .= $this->GetVal($val, $opts);
				continue;

				if ($opts & SQLOPT_QUOTE) $ret .= "'";
				$ret .= $this->database->Escape($val);
				if ($opts & SQLOPT_QUOTE) $ret .= "'";
			}
			if ($found) return $ret;
		}
		return $default;
	}

	function GetColumnString($cols, $tables = true)
	{
		if ($cols == null) return '*';
		$ret = null;
		$ix = 0;
		if (!empty($cols))
		{
			foreach ($cols as $key => $col)
			{
				if (!is_numeric($key))
				{
					$ret .= ($ix++?', ':null);
					$ret .= is_array($col) && isset($col['cmp'])?$col['cmp'].' ':null;
					$ret .= $this->ProcessVal($col, true);
					$ret .= ' '.($tables?$key:$this->StripTable($key));
				}
				else $ret .= ($ix++?', ':null).$this->QuoteTable($col);
			}
		}
		return $ret;
	}

	function ProcessVal($val, $tbl = false)
	{
		$lq = $tbl?$this->database->lq:"'";
		$rq = $tbl?$this->database->rq:"'";

		if (is_array($val))
		{
			if (isset($val['val']))
			{
				if (isset($val['opt']) && $val['opt'] == SQLOPT_UNQUOTE)
					return $val['val'];
				else return $lq.$this->database->Escape($val['val']).$rq;
			}
			else { Error('Arrays are not allowed here.'); varinfo($val); }
		}
		else
		{
			return $tbl
			? $this->QuoteTable($val)
			: $lq.$this->database->Escape($val).$rq;
		}
	}

	function GetVal($val, $opts)
	{
		if (is_array($val)) return "'".$this->database->Escape($val['val'])."'";
		if ($opts & SQLOPT_TQUOTE)
			return $this->QuoteTable($val);
		return "'".$this->database->Escape($val)."'";
	}

	function GetColumnsFromValues($vals)
	{
		if (!isset($vals)) return ' *';
		$ret = null;
		foreach (array_keys($vals) as $ix => $key)
		{
			if (!is_numeric($vals[$key]) && !isset($vals[$key])) continue;
			$ret .= ($ix>0?',':null).$this->QuoteTable($key);
		}
		return $ret;
	}

	function GetSingleValueString($vals, $sep = ',')
	{
		$ret = null;
		$ix = 0;
		foreach ($vals as $col => $val)
		{
			if ($ix++ > 0) $ret .= $sep;
			$ret .= $this->GetValue($val);
		}
		return $ret;
	}

	function GetValueString($vals, $multi = false, $sep = ',')
	{
		if ($multi)
		{
			$ret = null;
			foreach ($vals as $ix => $v)
				$ret .= ($ix?',':null).	'('.$this->GetSingleValueString($v, $sep).')';
			return $ret;
		}
		return '('.$this->GetSingleValueString($vals,$sep).')';
	}

	/**
	 * Quotes a table properly depending on the data source.
	 *
	 * @param string $name
	 * @return string Quoted name.
	 * @todo Rename this to QuoteName
	 */
	function QuoteTable($name = null, $sc = null)
	{
		if ($name == null) { $name = $this->table; $sc = $this->Shortcut; }
		if ($name == '*') return $name;
		if (is_array($name))
		{
			if ($name['opt'] == SQLOPT_UNQUOTE)
			return $name['val'];
		}
		$lq = $this->database->lq;
		$rq = $this->database->rq;
		if (strpos($name, '.') > -1)
			return preg_replace('#([^(]+)\.([^ )]+)#',
				"{$lq}\\1{$rq}.{$lq}\\2{$rq}", $name);
		return "{$lq}{$name}{$rq}".(!empty($sc) ? " $lq$sc$rq" : null);
	}

	function StripTable($name)
	{
		return (!strpos($name, '.')?$name:substr($name, strpos($name, '.')+1));
	}

	/**
	 * Inserts a row into the associated table with the passed array.
	 * @param array $columns An array of columns. If you wish to use functions
	 * @param bool $update_existing Whether to update the existing values
	 * by unique keys, or just to add ignoring keys otherwise.
	 * @return int ID of added row.
	 */
	function Add($columns, $update_existing = false, $multi = false)
	{
		$query = 'INSERT';
		$query .= ' INTO '.$this->QuoteTable($this->table).' (';
		$ix = 0;
		foreach (array_keys($columns) as $key)
		{
			if (!is_numeric($columns[$key]) && !isset($columns[$key])) continue;
			if ($ix++ != 0) $query .= ", ";
			$query .= $this->QuoteTable($key);
		}
		$query .= ') VALUES (';
		$ix = 0;
		foreach ($columns as $key => $val)
		{
			if (!is_numeric($val) && !isset($val)) continue;
			if ($ix > 0) $query .= ', ';
			if (is_array($val)) $query .= $this->ProcessVal($val);
			else $query .= "'".$this->database->Escape($val)."'";
			$ix++;
		}
		$query .= ")";
		if ($update_existing)
		{
			$query .= ' ON DUPLICATE KEY UPDATE ';
			$query .= $this->SetClause($columns, null);
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
	function AddSeries($values, $update_existing = false, $multi = false)
	{
		$query = 'INSERT INTO'.$this->QuoteTable($this->table).'VALUES';
		if ($multi) foreach ($values as $ix => $row)
			$query .= ($ix>0?',':null).'('.$this->GetValueString($row).')';
		else $query .= '('.$this->GetValueString($values).')';
		if ($update_existing)
			$query .= " ON DUPLICATE KEY UPDATE ".
				$this->GetSetString($values,'');
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
	function Get($opts = null)
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
		$query = 'SELECT ';
		$query .= $this->GetColumnString(@$opts['columns']);
		$query .= ' FROM '.$this->QuoteTable();
		$query .= $this->JoinClause(@$opts['joins'], $this->joins);
		$query .= $this->WhereClause(@$opts['match']);
		$query .= $this->GroupClause(@$opts['group']);
		$query .= $this->OrderClause(@$opts['order']);
		$query .= $this->AmountClause(@$opts['limit']);

		//Execute Query
		$rows = $this->database->Query($query, $this->ErrorHandler);

		//Prepare Data
		$f = $this->func_rows;
		switch ($this->database->type)
		{
			case DB_SL:
				if ($f($rows) < 1) return array();
				break;
			default:
				if ($f($this->database->link) < 1) return array();
		}
		$items = array();

		$a = null;
		if ($this->database->type == DB_MY)
		{
			$a = MYSQL_BOTH;
			if (@$opts['args'] == GET_ASSOC) $a = MYSQL_ASSOC;
		}
		else if ($this->database->type == DB_MI)
		{
			$a = MYSQLI_BOTH;
			if (@$opts['args'] == GET_ASSOC) $a = MYSQLI_ASSOC;
		}

		while (($row = call_user_func($this->func_fetch, $rows, $a)))
		{
			$newrow = array();
			foreach ($row as $key => $val)
			{
				//Note: Do not strip slashes at this level.
				$newrow[$key] = $val;
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
	function GetOne($opts)
	{
		$data = $this->Get($opts);
		if (!empty($data)) return $data[0];
		return $data;
	}

	/**
	 * Returns all rows that match $columns LIKE $phrase
	 *
	 * @param array $columns
	 * @param string $phrase
	 * @param int $start Where to start results for pagination.
	 * @param int $limit Limit of items to return for pagination.
	 * @param array $sort array('col' => 'ASC'|'DESC')
	 * @param mixed $filter array('col' => 'value') or 'col = value'
	 * @param mixed $joins array(new Join(dataset, condition, type))
	 * @return array
	 */
	function GetSearch($columns, $phrase, $start = 0, $limit = null,
		$sort = null, $filter = null, $joins = null)
	{
		$query = 'SELECT '.$this->GetColumnString($columns, false).' FROM '.
			$this->QuoteTable($this->table);
		$query .= DataSet::JoinClause($joins, $this->joins);

		$ix = 0;

		if (!empty($columns))
		{
			$query .=  ' WHERE (';

			//Phrase is a series of columns => phrases
			if (is_array($phrase))
			{
				$ix = 0;
				foreach ($phrase as $v => $c)
				{
					if ($ix++ > 0) $query .= ' OR';
					$query .= ' '.$c." LIKE '%{$v}%'";
				}
			}
			//Matching all selected columns to a single phrase.
			else
			{
				$newphrase = str_replace("'", '%', stripslashes($phrase));
				$newphrase = str_replace(' ', '%', $newphrase);
				if (is_array($columns))
				foreach ($columns as $name => $col)
				{
					if (is_array($col)) continue;
					if ($ix++ > 0) $query .= " OR";
					$query .= ' '.$this->QuoteTable($col)." LIKE '%{$newphrase}%'";
				}
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
	function GetCustom($query)
	{
		$rows = $this->database->Query($query, $this->ErrorHandler);
		if (empty($rows)) return $rows;
		$ret = array();
		$f = $this->func_fetch;
		while (($row = $f($rows)))
		{
			$newrow = array();
			foreach ($row as $key => $val)
			{
				$newrow[$key] = $val;
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
		$sets = $this->GetColVals($values);
		if (empty($sets)) { Trace('Nothing to update.'); return; }
		$query = 'UPDATE '.$this->QuoteTable($this->table);
		$query .= $this->JoinClause($this->joins);
		$query .= $this->SetClause($values);
		$query .= $this->WhereClause($match);
		$this->database->Query($query);
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

		//Grab all the destination items that are going to be swapped.
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
		$query = 'DELETE FROM '.$this->QuoteTable($this->table);
		$query .= $this->WhereClause($match);
		$this->database->Query($query);

		//Prune off all children...
		if (!empty($this->children))
		foreach ($this->children as $child)
		{
			$query = "DELETE c FROM {$child->ds->table} c LEFT JOIN {$this->table} p ON(c.{$child->child_key} = p.{$child->parent_key}) WHERE c.{$child->child_key} != 0 AND p.{$child->parent_key} IS NULL";
			$this->database->Query($query);
		}
	}

	function Truncate()
	{
		$this->database->Query('TRUNCATE '.$this->table);
	}

	function Begin()
	{
		mysqli_autocommit($this->database->link, false);
	}

	function End()
	{
		mysqli_commit($this->database->link);
		mysqli_autocommit($this->database->link, true);
	}
}

function BuildTree($items, $parent, $assoc = null)
{
	$flats = LinkList($items, $parent, $assoc);

	$root = new TreeNode();

	foreach ($flats as $f)
	{
		$p = $f->data[$assoc];
		if (isset($flats[$p]))
		{
			$flats[$p]->children[] = $f;
			$f->parents[$p] = $flats[$p];
		}
		else $root->children[$f->id] = $f;
	}
	return $root;
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
		if (isset($ret[$p]))
		{
			$ret[$p]->children[$id] = $i;
			$i->parent = $ret[$p];
		}
	}
	return $ret;
}

function TreeToUL($tree, $display = 'name')
{
	$ret = '<ul><li>'.$tree->data[$display];
	if (!empty($tree->children))
	foreach ($tree->children as $child)
	{
		$ret .= TreeToUL($child, $display);
	}
	$ret .= "</li></ul>\n";
	return $ret;
}

function MySqlDate($ds, $data, $col, $dbcol)
{
	$ts = MyDateTimestamp($data[$col]);
	return date('m/d/y', $ts);
}

/**
* Stack associative data from a series of joined results.
*
* @param mixed $items
* @param mixed $splits
* @example StackData($ds->Get(), array('tbl1_id', 'tbl2_id'));
*/
function StackData($items, $splits)
{
	$cursor[0] = new TreeNode('ROOT', 0); // Root object

	if (!empty($items))
	{
		foreach ($items as $i)
		{
			foreach ($splits as $depth => $col)
			{
				if (!isset($cursor[$depth+1]))
				{
					echo "Creating tree node for {$col} ({$i[$col]})<br/>\n";
					$cur = $cursor[$depth+1] = new TreeNode($i, $i[$col]);
				}
				else $cur = $cursor[$depth+1];
				$parent = $cursor[$depth];

				if ($i[$col] != $cur->data[$col])
				{
					$id = $i[$col];
					$parent->children[$id] = new TreeNode($i, $id);
				}
			}
		}
		foreach ($splits as $depth => $col)
		{
			$cursor[$depth+1]->children[$i[$col]] = new TreeNode($i, $i[$col]);
		}
	}

	foreach ($cursor[0]->children as $child)
	{
		varinfo($child->id);
	}

	return $cursor[0];
}

class DOMHigh
{
	/** @var DOMDocument */
	public $Doc;
	/** @var DOMXPath */
	public $XPath;

	function __construct($data)
	{
		$this->Doc = new DOMDocument();
		$this->Doc->loadXML($data);
		$this->XPath = new DOMXPath($this->Doc);
	}

	// XPath Related

	function Query($xpath)
	{
		$items = $this->XPath->query($xpath);
		$ret = array();
		for ($ix = 0; $item = $items->item($ix); $ix++)
			$ret[] = $item;
		return $ret;
	}

	function RegisterNamespace($prefix, $url)
	{
		$this->XPath->registerNamespace($prefix, $url);
	}

	// Manipulation

	function RemoveElements($xpath)
	{
		$els = $this->Query($xpath);
		foreach ($els as $e)
			$e->parentNode->removeChild($e);
	}

	function Append($xpath, $element, $attributes)
	{
		$elAdd = $this->Doc->createElement($element);
		foreach ($attributes as $n => $v)
			$elAdd->setAttribute($n, $v);
		$elsTarget = $this->Query($xpath);
		foreach ($elsTarget as $elTarget)
			$elTarget->appendChild($elAdd);
	}
}

?>
