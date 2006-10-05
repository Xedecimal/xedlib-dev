<?php

/**
 * @package Data
 */

/** Column options include nullable. */
define("DB_NULL",		2);
/** Column options include auto increment. */
define("DB_AINC",		4);
/** Column options include a primary key. */
define("DB_PKEY",		8);
/** Column optons include foreign key. */
define("DB_FKEY",		16);
/** Column options include unsigned. */
define("DB_UNSIGNED",	32);
define("GET_ASSOC", 0);

$queries = array();

/**
 * A generic database interface, currently only supports MySQL apparently.
 * @todo Add other database formats.
 */
class Database
{
	/** A link returned from mysql_connect(), don't worry about it. */
	var $link;
	/** Name of this database, set from constructor. */
	var $name;
	/**
	 * Whether or not we use odbc.
	 *
	 * @var bool true if piping through odbc.
	 */
	var $odbc;

	/**
	 * Instantiates a new xlDatabase object with the database name, hostname, user name and password.
	 * @param $dsn string Name of the database or DSN this will attach to.
	 * @param $host string Hostname to connect to, either specified or gathered from the defined constant XL_DBHOST.
	 * @param $user string Username to connect with, either specified or gathered from the defined constant XL_DBUSER.
	 * @param $pass string Password to connect with, either specified or gathered from the defined constant XL_DBPASS.
	 * @param $use_odbc bool Whether or not to use odbc instead of php mssql libs.
	 */
	function Database($dsn, $host = null, $user = null, $pass = null, $use_odbc = false)
	{
		$this->odbc = $use_odbc;
		if ($use_odbc) $this->link = odbc_connect($dsn, $user, $pass, SQL_CUR_USE_ODBC);
		else
		{
			$this->link = mssql_connect($host, $user, $pass);
			mssql_select_db($dsn, $this->link);
		}
		if (empty($this->link)) die("MSSQL Error on connect: ".mssql_get_last_message());
		$this->name = $dsn;
	}

	/**
	 * Perform a manual query on the associated database, try to use this sparingly because
	 * we will be moving to abstract database support so it'll be a load parsing the sql-like
	 * query and translating.
	 * @param $query string The actual SQL formatted query.
	 * @param $silent bool Should we return an error?
	 * @param $return Whether or not to return data (avoids truple errors with odbc)
	 * @return object Query result object.
	 */
	function Query($query, $silent = false, $return = true)
	{
		global $queries;
		$ret = array();
		if ($this->odbc) $res = odbc_exec($this->link, $query);
		else $res = mssql_query($query, $this->link);

		if (!$res)
		{
			if (!$silent) Error("Query: $query<br>\nMSSQL Error: ".
				odbc_errormsg()."<br>\n");
			return null;
		}
		$queries[] = $query;

		//Prepare Data
		if ($return)
		{
			$ret = array();
			if ($this->odbc)
			{
				while (($row = odbc_fetch_array($res))) $ret[] = $row;
			}
			else
			{
				if (mssql_num_rows($res) < 1) return null;
				while (($row = mssql_fetch_array($res))) $ret[] = $row;
			}

			return $ret;
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
	* Creates a generic table.
	* @param $table DataTable The table to be created.
	*/
	function CreateTable($table)
	{
		$query = "CREATE TABLE {$table->name} (\n";

		foreach ($table->cols as $ix => $col)
		{
			$name = $col[0];
			$type = $col[1];
			$size = $col[2];
			$opts = $col[3];
			$query .= "  $name";

			//Type/Size
			if ($type == "int")
			{
				$query .= " int";
				if ($size != NULL) $query .= "($size)";
			}
			else if ($type == "text")
			{
				if ($size < 256) $query .= " varchar($size)";
				else if ($size < 65536) { $query .= " text"; }
				else if ($size < 16777216) { $query .= " mediumtext"; }
				else if ($size < 4294967296) { $query .= " longtext"; }
				else die("Cannot create type based on field input.");
			}
			else if ($type == "float")
			{
				$sizes = explode(",", $size);
				$query .= " float({$sizes[0]}, {$sizes[1]})";
			}

			//Options
			if ($opts & DB_NNULL) $query .= " NOT NULL";
			if ($opts & DB_AINC) $query .= " AUTO_INCREMENT";
			if ($opts & DB_PKEY) $query .= " PRIMARY KEY";
			if ($opts & DB_FKEY) $query .= " FOREIGN KEY";
			if ($ix < count($table->cols)-1) $query .= ",";
			$query .= "\n";
		}
		$query .= ");";
		$this->Query($query);
	}

	/**
	* Drop a child table.
	* @param $name The name of the table to make a boo boo to.
	* \todo Move this to xlDataTable->Drop()
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
 * A generic database table definition, currently only MySQL supported.
 */
class DataTable
{
	/** Name of this table */
	var $name;
	/** An array of columns and associated data in the underlying database. */
	var $cols;

	/**
	* Instantiate this class associated with a given table in a database.
	* @param $name Name of the table to associate with.
	*/
	function DataTable($name)
	{
		$this->name = $name;
	}

	/**
	* Populates the $cols member with information on all the columns in this table.
	* @param $db The xlDatabase object to use for the connection.
	*/
	function GetCols($db)
	{
		$this->cols = array();
		$res = $db->Query("DESCRIBE {$this->name}");
		while (($row = mysql_fetch_array($res)))
		{
			$type = strtok($row[1], "()");
			$size = strtok("()");

			$options = 0;
			if (strstr($row[5], "auto_increment")) $options |= XL_DBAINC;
			if ($row[3] == "PRI") $options |= XL_DBPKEY;
			$this->cols[] = array($row[0], $type, $size, $options);
		}
	}

	/**
	* Depricated.
	* @param $db Depricated.
	* @param $where Depricated.
	* @return Depricated.
	*/
	function GetData($db, $where)
	{
		$query = "SELECT * FROM {$this->name}";
		if ($where) $query .= " WHERE $where;";
		$cols = $db->Query($query);
		$ds = new xlDataSet();
		while (($row = mysql_fetch_array($cols, MYSQL_NUM)))
		{
			$ds->AddRow($row);
		}
		return $ds;
	}

	/**
	* Depricated.
	* @param $db Depricated.
	* @param $items Depricated.
	* @return Depricated.
	*/
	function InsertData($db, $items)
	{
		$query = "INSERT INTO {$this->name} VALUES(";
		$x = 0;
		foreach ($items as $row)
		{
			if ($row == NULL) { $query .= "NULL"; }
			else if (is_string($row)) { $query .= "\"$row\""; }
			else { $query .= "$row"; }
			if ($x < count($items) - 1) { $query .= ", "; }
			else { $query .= ");"; }
			$x++;
		}
		$db->Query($query);
	}

	/**
	* Depricated.
	* @param $db Depricated.
	* @param $row Depricated.
	* @return Depricated.
	*/
	function UpdateData($db, $row)
	{
		$query = "UPDATE {$this->name} SET ";
		for ($x = 0; $x < count($this->cols); $x++)
		{
			switch ($this->cols[$x][1])
			{
				case "int":
					$query .= "{$this->cols[$x][0]} = {$row[$x]}";
				break;
				default:
					$query .= "{$this->cols[$x][0]} = \"{$row[$x]}\"";
				break;
			}
			if ($x < count($row) - 1) $query .= ", ";
		}
		$query .= " WHERE {$this->cols[0][0]} = {$row[0]};";
		$db->Query($query);
	}

	/**
	* Depricated.
	* @param $name Depricated.
	* @param $type Depricated.
	* @param $size Depricated.
	* @param $options Depricated.
	* @return Depricated.
	*/
	function AddField($name, $type, $size = NULL, $options = 0)
	{
		$this->cols[] = array($name, $type, $size, $options);
	}
}

/**
 * Removes quoting from a database field to perform functions and
 * such.
 */
function DeString($data) { return array("destring", $data); }
function DBNow() { return array("now"); }

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
	var $database;

	/**
	 * Associated table.
	 *
	 * @var DataTable
	 */
	var $table;

	var $children;

	/**
	 * Initialize a new CDataSet binded to $table in $db.
	 * @param $db Database A Database object to bind to.
	 * @param $table string Specifies the name of the table in $db to bind to.
	 */
	function DataSet($db, $table)
	{
		$this->database = $db;
		$this->table = new DataTable($table);
	}

	/**
	 * @param $cpkey string Name of the child's primary key for item swap.
	 * @param $temp string Name of temporary table containing space for item swap.
	 */
	function AddChild($ds, $pkey, $ckey, $cpkey = null, $temp = null)
	{
		$this->children[] = array("ds" => $ds, "pkey" => $pkey, "ckey" => $ckey, "cpkey" => $cpkey, "temp" => $temp);
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
					$ret .= " [{$col}] = '{$val}'";
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

	/**
	 * Inserts a row into the associated table with the passed array.
	 * @param $columns array An array of columns. If you wish to use functions
	 * please use the function DeString("myvalue") to avoid any database
	 * errors.
	 * @return bool Succeeded
	 */
	function Add($columns)
	{
		$query = "INSERT INTO [{$this->table->name}] (";
		foreach (array_keys($columns) as $ix => $key)
		{
			if (strlen($key) < 1) continue;
			if ($ix != 0) $query .= ", ";
			$query .= "[$key]";
		}
		$query .= ") VALUES(";
		$ix = 0;
		foreach ($columns as $key => $val)
		{
			if (strlen($key) < 1) { $ix++; continue; }
			//destring('value') for functions and such.
			if (is_array($val))
			{
				if ($val[0] == "destring") $query .= $val[1];
			}
			else $query .= "'".str_replace("'", "''", stripslashes($val))."'";
			if ($ix < count($columns)-1) $query .= ", ";
			$ix++;
		}
		$query .= ");";
		return $this->database->Query($query, false, false);
	}

	/**
	 * Get a set of data given the specific arguments.
	 * @param $match array Column to Value to match for in a WHERE statement.
	 * @param $sort array Column to Direction array.
	 * @param $filter array Column to Value array.
	 * @param $joins array Table to ON Expression values.
	 * @return array Array of items selected.
	 */
	function Get(
		$match = null,
		$sort = null,
		$filter = null,
		$joins = null)
	{
		//Prepare Query
		$query = 'SELECT';
		$query .= " * FROM [{$this->table->name}]";
		$query .= $this->JoinClause($joins);
		$query .= $this->WhereClause($match);
		$query .= $this->OrderClause($sort);
		$query .= $this->AmountClause($filter);

		//Execute Query
		$this->items = $this->database->Query($query);

		return $this->items;
	}

	/**
	 * Return a single item from this dataset
	 * @param $match array Passed on to WhereClause.
	 * @param $args Arguments passed to fetch_array.
	 * @return A single serialized row matching $match or null if not found.
	 */
	function GetOne($match, $args = null)
	{
		$data = $this->Get($match, null, null, null, $args);
		if (!empty($data)) return $data[0];
		return $data;
	}

	function GetScalar($col, $val, $retcol)
	{
		$query = "SELECT `$retcol` FROM `{$this->table->name}` WHERE `{$col}` = '{$val}'";
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
	function GetAll($limit = NULL, $args = null)
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
		$query = "SELECT * FROM {$this->table->name} ORDER BY $column $order";
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

	function GetSearch($columns, $phrase, $args = MYSQL_BOTH)
	{
		$query = "SELECT * FROM `".$this->table->name."` WHERE";
		foreach ($columns as $ix => $col)
		{
			if ($ix > 0) $query .= " OR";
			$query .= " $col LIKE '%$phrase%'";
		}
		$cols = $this->database->Query($query);
		if (mysql_affected_rows() < 1) return null;
		$this->items = array();
		while (($row = mysql_fetch_array($cols, $args))) $this->items[] = $row;
		return $this->items;
	}

	/**
	 * Query the database manually, not suggested to be used as it will be
	 * very cpu intensive when it evaluates the query itself.
	 * @param $query string The query to perform, including the table name and everything.
	 * @param $silent bool Should we output an error?
	 * @return array The serialized database rows.
	 */
	function GetCustom($query, $silent = false)
	{
		return $this->database->Query($query, $silent);
	}

	/**
	 * Return the total amount of rows in this associated table.
	 * @param $match array Passed off to WhereClause.
	 * @param $silent boolean Good for checking an installation.
	 * @return int The actual numeric count.
	 */
	function GetCount($match = null, $silent = false)
	{
		$query = "SELECT COUNT(*) FROM {$this->table->name}";
		$query .= $this->WhereClause($match);
		return $this->GetScalar($query, $silent);
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
		$query = "UPDATE [{$this->table->name}] SET ";
		$x = 0;
		foreach ($values as $key => $val)
		{
			if (is_array($val))
			{
				if ($val[0] == "destring") $query .= "[{$key}] = {$val[1]}";
			}
			else $query .= "[{$key}] = '".str_replace("'", "''", $val)."'";
			if ($x++ < count($values)-1) $query .= ", ";
		}
		$this->database->Query($query.$this->WhereClause($match), false, false);
	}

	function Swap($smatch, $dmatch, $pkey)
	{
		$sitems = $this->database->query("SELECT * FROM [{$this->table->name}]".$this->WhereClause($smatch));
		$sitem = $sitems[0];
		$ditems = $this->database->query("SELECT * FROM [{$this->table->name}]".$this->WhereClause($dmatch));
		$ditem = $ditems[0];
		if (!empty($this->children))
		{
			foreach ($this->children as $child)
			{
				//Copy Source children into Temp
				$query = "INSERT INTO `{$child['temp']}` SELECT `{$child['cpkey']}` FROM `{$child['ds']->table->name}` WHERE {$child['ckey']} = {$smatch[$child['pkey']]}";
				$child['ds']->database->query($query);

				//Move Destination children into Source parent.
				$child['ds']->Update(array($child['ckey'] => $ditem[$child['pkey']]), array($child['ckey'] => $sitem[$child['pkey']]));

				//Move Temp children into Destination parent.
				$query = "UPDATE `{$child['ds']->table->name}` JOIN `{$child['temp']}` SET {$child['ckey']} = {$ditem[$child['pkey']]} WHERE `{$child['temp']}`.{$child['cpkey']} = {$child['ds']->table->name}.{$child['cpkey']}";
				$child['ds']->database->query($query);

				$child['ds']->database->query("TRUNCATE [{$child['temp']}]");
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
		$this->database->Query("DELETE FROM [{$this->table->name}]".$this->WhereClause($match), false, false);
	}
}

?>
