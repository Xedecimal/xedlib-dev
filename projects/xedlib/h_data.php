<?php

/**
 * @package Data
 */

define("GET_ASSOC", MYSQL_ASSOC);
define("GET_BOTH", MYSQL_BOTH);

/**
 * A generic database interface, currently only supports MySQL apparently.
 */
class Database
{
	/** A link returned from mysql_connect(), don't worry about it. */
	public $link;
	/** Name of this database, set from constructor. */
	public $name;

	/**
	 * Instantiates a new xlDatabase object with the database name, hostname, user name and password.
	 * @param $database string Name of the database this will attach to.
	 * @param $host string Hostname to connect to, either specified or gathered from the defined constant XL_DBHOST.
	 * @param $user string Username to connect with, either specified or gathered from the defined constant XL_DBUSER.
	 * @param $pass string Password to connect with, either specified or gathered from the defined constant XL_DBPASS.
	 */
	function Database($database, $host = null, $user = null, $pass = null)
	{
		$this->link = mysql_connect($host, $user, $pass);
		if (mysql_error()) die("MySQL Error on connect: ".mysql_error());
		mysql_select_db($database, $this->link);
		$this->name = $database;
	}

	/**
	 * Perform a manual query on the associated database, try to use this sparingly because
	 * we will be moving to abstract database support so it'll be a load parsing the sql-like
	 * query and translating.
	 * @param $query string The actual SQL formatted query.
	 * @param $silent bool Should we return an error?
	 * @return object Query result object.
	 */
	function Query($query, $silent = false)
	{
		$res = mysql_query($query, $this->link);
		if (mysql_error())
		{
			user_error("Query: $query<br>\nMySQL Error: ".mysql_error());
			return null;
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
	* @param $name The name of the table to make a boo boo to.
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

	function CheckInstall()
	{
		mysql_select_db($this->name, $this->link);
		if (mysql_error())
		{
			echo "Database: Could not locate database, installing...<br/>\n";
			mysql_query("CREATE DATABASE {$this->name}");
		}
	}
}

/**
 * Removes quoting from a database field to perform functions and
 * such.
 */
function DeString($data) { return array("destring", $data); }
function DBNow() { return array("now"); }

class Relation
{
	public $ds;
	public $parent_key;
	public $child_key;

	/**
	 * Prepares a relation for database association.
	 *
	 * @param DataSet $ds DataSet for this child.
	 * @param string $primary_key Column name of the primary key of $ds.
	 * @example ../examples/relation.php
	 * @return Relation
	 */
	function Relation($ds, $parent_key, $child_key)
	{
		$this->ds = $ds;
		$this->parent_key = $parent_key;
		$this->child_key = $child_key;
	}
}

/**
 * A general dataset, good for binding to a database's table.
 * Used to generically retrieve, store, update and delete
 * data quickly and easily, given the table matches a few
 * general guidelines, for one it must have an auto_increment
 * primary key for it's first field named 'id' so it can
 * easily locate fields.
 */
class DataSet
{
	/**
	 * Associated database.
	 *
	 * @var Database
	 */
	public $database;

	public $table;
	public $children;
	public $display;
	public $fields;
	public $id;

	/**
	 * Initialize a new CDataSet binded to $table in $db.
	 * @param $db Database A Database object to bind to.
	 * @param $table string Specifies the name of the table in $db to bind to.
	 */
	function DataSet($db, $table, $id = 'id')
	{
		$this->database = $db;
		$this->table = $table;
		$this->id = $id;
	}

	/**
	 * @param Relation $relation Child to add.
	 */
	function AddChild($relation)
	{
		$this->children[] = $relation;
	}

	function ColsClause($cols)
	{
		if (is_array($cols))
		{
			$ret = '';
			$ix = 0;
			foreach ($cols as $col => $val)
			{
				if ($ix++ > 0) $ret .= ",";
				$ret .= " `{$col}` AS `{$val}`";
			}
			return $ret;
		}
		return null;
	}

	function QuoteTable($name)
	{
		if (strpos($name, '.') > -1) return str_replace('.', '`.`', "`$name`");
		return "`$name`";
	}

	function WhereClause($match)
	{
		if (isset($match))
		{
			if (is_array($match))
			{
				$ret = ' WHERE';
				$ix = 0;
				foreach ($match as $col => $val)
				{
					if ($ix++ > 0) $ret .= ' AND';
					if (is_array($val) && $val[0] = 'destring')
						$ret .= ' '.$this->QuoteTable($col)." = {$val[1]}";
					else
						$ret .= ' '.$this->QuoteTable($col)." = '{$val}'";
				}
				return $ret;
			}
		}
		return null;
	}

	function JoinClause($joining)
	{
		if (isset($joining))
		{
			$ret = '';
			if (is_array($joining))
			{
				foreach ($joining as $col => $on)
				{
					$ret .= " JOIN {$col} ON({$on})";
				}
			}
			return $ret;
		}
		return null;
	}

	function OrderClause($sorting)
	{
		if (isset($sorting))
		{
			$ret = ' ORDER BY';
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

	function AmountClause($amount)
	{
		if (isset($amount))
		{
			$ret = ' LIMIT';
			if (is_array($amount)) $ret .= " {$amount[1]}, {$amount[0]}";
			else $ret .= " 0, {$amount}";
			return $ret;
		}
		return null;
	}

	function GetSetString($values)
	{
		$ret = null;
		if (!empty($values))
		{
			$x = 0;
			foreach ($values as $key => $val)
			{
				if (is_array($val))
				{
					if ($val[0] == "destring") $ret .= "`{$key}` = {$val[1]}";
				}
				else $ret .= "`{$key}` = '".addslashes($val)."'";
				if ($x++ < count($values)-1) $ret .= ", ";
			}
		}
		return $ret;
	}

	/**
	 * Inserts a row into the associated table with the passed array.
	 * @param $columns array An array of columns. If you wish to use functions
	 * @param $update_existing bool Whether to update the existing values
	 * by unique keys, or just to add ignoring keys otherwise.
	 * @return int ID of added row.
	 */
	function Add($columns, $update_existing = false)
	{
		$query = "INSERT INTO `{$this->table}` (";
		foreach (array_keys($columns) as $ix => $key)
		{
			if ($ix != 0) $query .= ", ";
			$query .= "`$key`";
		}
		$query .= ") VALUES(";
		$ix = 0;
		foreach ($columns as $key => $val)
		{
			//destring('value') for functions and such.
			if (is_array($val))
			{
				if ($val[0] == "destring") $query .= $val[1];
			}
			else $query .= "'".addslashes($val)."'";
			if ($ix < count($columns)-1) $query .= ", ";
			$ix++;
		}
		$query .= ")";
		if ($update_existing)
		{
			$query .= " ON DUPLICATE KEY UPDATE ".$this->GetSetString($columns);
		}
		$this->database->Query($query);
		return mysql_insert_id($this->database->link);
	}

	/**
	 * Get a set of data given the specific arguments.
	 * @param $match array Column to Value to match for in a WHERE statement.
	 * @param $sort array Column to Direction array.
	 * @param $filter array Column to Value array.
	 * @param $joins array Table to ON Expression values.
	 * @param $args int passed to mysql_fetch_array.
	 * @return array Array of items selected.
	 */
	function Get(
		$match = null,
		$sort = null,
		$filter = null,
		$joins = null,
		$args = GET_BOTH)
	{
		//Prepare Query
		$query = 'SELECT';
		$query .= " * FROM `{$this->table}`";
		$query .= $this->JoinClause($joins);
		$query .= $this->WhereClause($match);
		$query .= $this->OrderClause($sort);
		$query .= $this->AmountClause($filter);

		//Execute Query
		$rows = $this->database->Query($query);

		//Prepare Data
		$this->items = array();
		if (mysql_affected_rows() < 1) return null;
		while (($row = mysql_fetch_array($rows, $args)))
		{
			$newrow = array();
			foreach ($row as $key => $val)
			{
				$newrow[$key] = stripslashes($val);
			}
			$this->items[] = $newrow;
		}
		return $this->items;
	}

	/**
	 * Return a single item from this dataset
	 * @param $match array Passed on to WhereClause.
	 * @param $args Arguments passed to fetch_array.
	 * @return A single serialized row matching $match or null if not found.
	 */
	function GetOne($match, $args = GET_BOTH)
	{
		$data = $this->Get($match, null, null, null, $args);
		if (isset($data)) return $data[0];
		return $data;
	}

	function GetScalar($match, $col)
	{
		$query = "SELECT `$col` FROM `{$this->table}`".$this->WhereClause($match);
		$cols = $this->database->Query($query);
		$data = mysql_fetch_array($cols);
		return $data[0];
	}

	/**
	 * Returns every row unless limit is specified without a specific sort column or order
	 * @param $limit int Maximum amount of rows to return.
	 * @param $args int Arguments to be passed to mysql_fetch_array (defaults to MYSQL_NUM)
	 * @return array An array of results from the query specified.
	 */
	function GetAll($limit = NULL, $args = GET_BOTH)
	{
		return $this->Get(null, null, $limit, null, $args);
	}

	/**
	 * Returns every database  row from the database sorting by the given column
	 * @param $column Name of the table column to sort by.
	 * @param $order A string either "ASC" or "DESC" I believe.
	 * @param $start Result index to being at.
	 * @param $amount Result count to return.
	 * @return array An array of results from the query specified or null if none.
	 */
	function GetAllSort($column, $order = "ASC", $start = NULL, $amount = NULL)
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

	function GetSearch($columns, $phrase, $args = GET_BOTH)
	{
		$newphrase = str_replace("'", '%', stripslashes($phrase));
		$newphrase = str_replace(' ', '%', $newphrase);
		$query = "SELECT * FROM `{$this->table}` WHERE";
		foreach ($columns as $ix => $col)
		{
			if ($ix > 0) $query .= " OR";
			$query .= " `$col` LIKE '%{$newphrase}%'";
		}
		return $this->GetCustom($query);
	}

	/**
	 * Query the database manually, not suggested to be used as it will be
	 * very cpu intensive when it evaluates the query itself.
	 * @param $query string The query to perform, including the table name and everything.
	 * @param $silent bool Should we output an error?
	 * @return array The serialized database rows.
	 */
	function GetCustom($query, $silent = false, $args = GET_BOTH)
	{
		$rows = $this->database->Query($query, $silent);
		if ($rows == null) return null;
		$ret = array();
		while (($row = mysql_fetch_array($rows, $args)))
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
	 * @param $match array Passed off to WhereClause.
	 * @param $silent boolean Good for checking an installation.
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
	 * @param $match array Passed on to WhereClause.
	 * @param $values array Data to be updated.
	 * values to update using array("column1" => "value", "column2"
	 * => "value2").
	 */
	function Update($match, $values)
	{
		$query = "UPDATE `{$this->table}` SET ".$this->GetSetString($values);
		$this->database->Query($query.$this->WhereClause($match));
	}

	function Swap($smatch, $dmatch, $pkey)
	{
		$sitems = $this->database->query("SELECT * FROM `{$this->table}`".$this->WhereClause($smatch));
		$sitem = mysql_fetch_array($sitems, GET_ASSOC);
		$ditems = $this->database->query("SELECT * FROM `{$this->table}`".$this->WhereClause($dmatch));
		$ditem = mysql_fetch_array($ditems, GET_ASSOC);
		if (!empty($this->children))
		{
			foreach ($this->children as $child)
			{
				//Copy Source children into Temp
				$query = "INSERT INTO `{$child['temp']}` SELECT `{$child['cpkey']}` FROM `{$child['ds']->table}` WHERE `{$child['ckey']}` = {$smatch[$child['pkey']]}";
				$child['ds']->database->query($query);

				//Move Destination children into Source parent.
				$child['ds']->Update(array($child['ckey'] => $ditem[$child['pkey']]), array($child['ckey'] => $sitem[$child['pkey']]));

				//Move Temp children into Destination parent.
				$query = "UPDATE `{$child['ds']->table}` JOIN `{$child['temp']}` SET {$child['ckey']} = {$ditem[$child['pkey']]} WHERE `{$child['temp']}`.`{$child['cpkey']}` = `{$child['ds']->table}`.`{$child['cpkey']}`";
				$child['ds']->database->query($query);

				$child['ds']->database->query("TRUNCATE `{$child['temp']}`");
			}
		}
		unset($sitem['id'], $ditem['id']);
		$this->Update($smatch, $ditem);
		$this->Update($dmatch, $sitem);
	}

	/**
	* Removes all items that match the specified value in the specified column in the
	* associated table.
	* @param $match array Passed on to WhereClause to decide deleted data.
	*/
	function Remove($match)
	{
		$matches = array();
		$query = "DELETE FROM `{$this->table}`";
		$query .= $this->WhereClause($match);
		$this->database->Query($query);

		//Prune off all children...
		if (!empty($this->children)) foreach ($this->children as $ix => $child)
		{
			$query = "DELETE c FROM {$child->ds->table} c LEFT JOIN {$this->table} p ON(c.{$child->child_key} = p.{$child->parent_key}) WHERE c.{$child->child_key} != 0 AND p.{$child->parent_key} IS NULL";
			$this->database->Query($query);
		}
	}

	function GetMatches($original)
	{
		$ret = $original;
		$tables = array($this->table);
		if (!empty($this->children))
		{
			foreach ($this->children as $child)
			{
				$tm = $child['ds']->GetMatches($ret);
				$tables = array_merge($tm[0], $tables);
				$tm[1]["{$child['ds']->table}.{$child['ckey']}"] = DeString($this->QuoteTable($this->table.'.'.$child['pkey']));
				if ($tm[1] != null) $ret = array_merge($ret, $tm[1]);
			}
		}
		return array($tables, $ret);
	}
}

?>