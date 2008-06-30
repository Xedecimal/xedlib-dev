<?php

/**
 * @package Editor
 */

define('ED_SORT_NONE',   0);
define('ED_SORT_MANUAL', 1);
define('ED_SORT_TABLE',  2);

require_once('h_data.php');

class DisplayColumn
{
	/**
	 * Text of the column in the display table.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Callback function for evaluating this cell for each row.
	 *
	 * @var mixed
	 */
	public $callback;

	/**
	 * HTML attributes for this column.
	 *
	 * @var string
	 */
	public $attribs;

	/**
	 * Creates a new DisplayColumn.
	 *
	 * @param string $text
	 * @param mixed $callback
	 * @param string $attribs
	 */
	function DisplayColumn($text, $callback = null, $attribs = null)
	{
		$this->text = $text;
		$this->callback = $callback;
		$this->attribs = $attribs;
	}
}

class EditorHandler
{
	/**
	 * Default handler for creating an item.
	 * If you extend this object and return false, it will not add the item.
	 *
	 * @param array $data Context
	 * @return bool true by default
	 */
	function Create(&$data) { return true; }

	/**
	 * After an item is created, this contains the id of the new item. You
	 * cannot halt the item from being inserted at this point.
	 *
	 * @param mixed $id Unique id of this row.
	 * @param array $inserted Data that has been inserted (including the id).
	 * @return bool true by default
	 */
	function Created($id, $inserted) { return true; }

	/**
	 * Before an item is updated, this function is called. If you extend this
	 * object and return false, it will not be updated.
	 *
	 * @param mixed $id Unique id of this row.
	 * @param array $original Original data before update.
	 * @param array $update Columns suggested to get updated.
	 * @return bool true by default
	 */
	function Update($id, &$original, &$update) { return true; }

	/**
	 * Called before and item is deleted. If you extend this object and return
	 * false, it will not be deleted.
	 *
	 * @param int $id ID of deleted items
	 * @param array $data Context
	 * @return bool true by default (meant to be overridden)
	 */
	function Delete($id, &$data) { return true; }

	/**
	 * Called to retrieve additional fields for the editor form object.
	 * @param Form $form Contextual form suggested to add fields to.
	 * @param mixed $id Unique id of this row.
	 * @param array $data Data related to the action (update/insert).
	 */
	function GetFields(&$form, $id, $data) {}

	/**
	 * Returns an array of joins to be passed as an argument to DataSet->Get()
	 * @return array Join array.
	 * @see DataSet
	 */
	function GetJoins() { return array(); }
}

/**
 * Check the example...
 *
 * @example doc/examples/HandlerFile.php
 *
 */
class HandlerFile extends EditorHandler
{
	/**
	 * Root location to create folders.
	 *
	 * @var string
	 */
	public $target;
	/**
	 * Database column associated with the items created.
	 *
	 * @var string
	 */
	public $column;
	/**
	 * Conditions that can exemplify the creation of certain items.
	 * specified as conditions['column'] = 'match';
	 *
	 * @var array
	 */
	public $conditions;

	/**
	 * Creates a new file handler.
	 *
	 * @param string $target Target folder that will hold information.
	 * @param string $column Data column to name new folders by.
	 * @param array $conditions Conditions to consider managing folders.
	 */
	function HandlerFile($target, $column, $conditions = null)
	{
		$this->target = $target;
		$this->column = $column;
		$this->conditions = $conditions;
	}

	/**
	 * Called when an item is created.
	 * Example: array('usr_access' => array(1, 3, 5));
	 * This will manage files for the user if the column usr_access is 1, 3 or 5.
	 */
	function Created($id, $inserted)
	{
		$target = "{$this->target}/{$inserted[$this->column]}";
		if (!isset($this->conditions) && !file_exists($target))
		{
			mkdir($target);
			chmod($target, 0777);
		}
		else if (!empty($this->conditions))
		{
			foreach ($this->conditions as $col => $cond)
			{
				foreach ($cond as $val)
				{
					if ($inserted[$col] == $val && !file_exists($target))
					{
						mkdir($target);
						chmod($target, 0777);
						return true;
					}
				}
			}
		}
		return true;
	}

	function Update($id, &$original, &$update)
	{
		$source = "{$this->target}/{$original[$this->column]}";
		if (file_exists($source) && isset($update[$this->column]))
		{
			rename($source, $this->target.'/'.$update[$this->column]);
		}
		return true;
	}

	/**
	 * Called when an item is deleted.
	 *
	 * @param mixed $id
	 * @param array $data
	 * @return bool
	 */
	function Delete($id, &$data)
	{
		if (strlen($data[$this->column]) < 1) return true;
		if (file_exists("{$this->target}/{$data[$this->column]}"))
			DelTree("{$this->target}/{$data[$this->column]}");
		return true;
	}

	/*function Swap($idSrc, $idDst)
	{
		if (file_exists("{$this->target}/{$idSrc}"))
			rename("{$this->target}/{$idSrc}", "{$this->target}/..{$idSrc}");
		if (file_exists("{$this->target}/{$idDst}"))
			rename("{$this->target}/{$idDst}", "{$this->target}/{$idSrc}");
		if (file_exists("{$this->target}/..{$idSrc}"))
			rename("{$this->target}/..{$idSrc}", "{$this->target}/{$idDst}");
		return true;
	}*/
}

/**
 * A complex data editor.
 */
class EditorData
{
	/**
	 * Unique name of this editor.
	 *
	 * @var string
	 */
	public $Name;

	/**
	 * Dataset to interact with.
	 *
	 * @var DataSet
	 */
	public $ds;

	/**
	 * Filter to be passed to the DataSet in it's form.
	 *
	 * @var array
	 * @see DataSet.WhereClause
	 */
	public $filter;

	/**
	 * Order to sort items, passed to the DataSet.
	 *
	 * @var array
	 * @see DataSet.OrderClause
	 */
	public $sort;

	/**
	 * State of this editor.
	 *
	 * @var int
	 * @see STATE_CREATE
	 * @see STATE_EDIT
	 */
	public $state;

	/**
	 * The method used to handle sorting of the table portion of this
	 * editor. Use either ED_SORT_NONE (0), ED_SORT_MANUAL (1) or ED_SORT_TABLE (2).
	 *
	 * @var int
	 */
	public $sorting;

	/**
	 * Callback function for when an item is created.
	 *
	 * @var mixed
	 * @deprecated  use AddHandler instead.
	 */
	public $oncreate;

	/**
	 * Calback function to be called when an item is updated.
	 *
	 * @var mixed
	 * @deprecated  use AddHandler instead.
	 */
	public $onupdate;

	/**
	 * Callback function for when an item is deleted.
	 *
	 * @var mixed
	 * @deprecated use AddHandler instead.
	 */
	public $ondelete;

	/**
	 * Callback function for when an item is swapped with another.
	 *
	 * @var mixed
	 * @deprecated use AddHandler instead.
	 */
	//var $onswap;

	/**
	 * An array of handlers used for extra functionality of create, update,
	 * delete and swap.
	 *
	 * @var array
	 */
	public $handlers;

	/**
	 * Behavior settings for this editor.
	 * @var EditorDataBehavior
	 */
	public $Behavior;

	/**
	 * Default constructor.
	 *
	 * @param string $name Name of this editor
	 * @param DataSet $ds Dataset for this editor to interact with.
	 * @param array $filter Array to constrain editing to a given expression.
	 * @param array $sort Array of 'column' => 'desc/asc'.
	 */
	function EditorData($name, &$ds, $filter = null, $sort = null)
	{
		require_once('h_utility.php');
		require_once('h_display.php');

		$this->Name = $name;
		$this->filter = $filter;
		$this->ds = $ds;

		$this->Behavior = new EditorDataBehavior();
		$this->handlers = array();

		if (strtolower(get_class($ds)) == 'dataset')
		{
			$this->sort = $sort;
			$this->type = CONTROL_BOUND;
		}
		else $this->type = CONTROL_SIMPLE;
		$this->sorting = ED_SORT_MANUAL;
	}

	/**
	 * Adds a handler to extend the functionality of actions performed in this
	 * editor.
	 *
	 * @param callback $handler
	 * @see HandlerFile
	 */
	function AddHandler(&$handler)
	{
		$this->handlers[] = $handler;
	}

	/**
	 * To be called before presentation, will process, verify and calculate any
	 * data to be used in the Get function.
	 *
	 * @param string $action Current action, usually stored in POST['ca']
	 * @param mixed $ci Current item, if bound use an identifier for the row, otherwise an unbound DataSet object.
	 * @return null
	 */
	function Prepare()
	{
		$act = GetState($this->Name.'_action');

		if ($this->sorting == ED_SORT_TABLE)
			$this->sort = array(GetVar('sort', $this->ds->id) => GetVar('order', 'ASC'));

		$this->state = $act == 'edit' ? STATE_EDIT : STATE_CREATE;
		if ($act == 'Create')
		{
			$insert = array();
			$child_id = GetVar('child');
			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			$fields = $context->ds->FieldInputs;
			foreach ($fields as $col => $in)
			{
				if (is_object($in))
				{
					$value = GetVar($this->Name.'_'.$col);
					if ($in->type == 'date')
					{
						$insert[$col] = $value[2].'-'.$value[0].'-'.$value[1];
					}
					else if ($in->type == 'password' && strlen($value) > 0)
					{
						$insert[$col] = md5($value);
					}
					else if ($in->type == 'file' && $value != null)
					{
						$ext = substr(strrchr($value['name'], '.'), 1);

						$moves[] = array(
							'tmp' => $value['tmp_name'], //Source
							'dst' => $in->valu, //Destination folder
							'col' => $col,
							'ext' => $ext
						);
						$insert[$col] = $ext;
					}
					else if ($in->type == 'selects') $insert[$col] = $value;
					else $insert[$col] = $value;
				}
				else if (is_numeric($col)) continue;
				else $insert[$col] = DeString($in);
				//I just changed this to 'else' (check the history), because a
				//numeric value with a string column would not go in eg. 5
				//instead of '5', if this ends up conflicting, we'll need to
				//come up with a different solution.
			}

			foreach ($this->handlers as $handler)
			{
				if (!$handler->Create($insert)) return;
			}

			$parent = GetVar('parent');

			if (isset($parent))
			{
				$child = $this->ds->children[GetVar('child')];
				$insert[$child->child_key] = $parent;
			}
			$id = $context->ds->Add($insert);
			$insert[$context->ds->id] = $id;

			if (!empty($moves))
			foreach ($moves as $move)
			{
				$target = "{$move['dst']}/{$id}_{$move['col']}.{$move['ext']}";
				move_uploaded_file($move['tmp'], $target);
				chmod($target, 0777);
			}

			foreach ($this->handlers as $handler)
				$handler->Created($id, $insert);
		}
		else if ($act == 'Update')
		{
			$ci = GetVar($this->Name.'_ci');

			if ($this->type == CONTROL_SIMPLE)
			{
				foreach (array_keys($this->ds->FieldInputs) as $name)
				{
					$vals[$name] = GetVar($name);
				}
				$fp = fopen($ci, 'w+');
				fwrite($fp, serialize($vals));
				fclose($fp);
			}
			$child_id = GetVar('child');
			$context = $child_id != null ? $this->ds->children[$child_id] : $this;
			$update = array();
			foreach ($context->ds->FieldInputs as $col => $in)
			{
				if (is_object($in))
				{
					$value = GetVar($this->Name.'_'.$col);

					if ($in->type == 'date')
					{
						$update[$col] = $value[2].'-'.$value[0].'-'.$value[1];
					}
					else if ($in->type == 'password')
					{
						if (strlen($value) > 0) $update[$col] = md5($value);
					}
					else if ($in->type == 'checkbox')
						$update[$col] = ($value == 1) ? 1 : 0;
					else if ($in->type == 'selects')
					{
						$update[$col] = $value;
					}
					else if ($in->type == 'file')
					{
						if (strlen($value['tmp_name']) > 0)
						{
							$files = glob("{$in->valu}/{$ci}_{$col}.*");
							foreach ($files as $file) unlink($file);
							$ext = substr(strrchr($value['name'], '.'), 1);
							$src = $value['tmp_name'];
							$dst = "{$in->valu}/{$ci}_{$col}.{$ext}";
							move_uploaded_file($src, $dst);
							$update[$col] = $ext;
						}
					}
					else $update[$col] = $value;
				}
			}

			if (count($this->handlers) > 0)
			{
				$data = $this->ds->GetOne(array($this->ds->id => $ci));
				foreach ($this->handlers as $handler)
				{
					if (!$handler->Update($ci, $data, $update)) return;
				}
			}

			if ($this->type == CONTROL_BOUND)
				$context->ds->Update(array($context->ds->id => $ci), $update);

			$this->Reset();
		}
		/*else if ($action == $this->name.'_swap')
		{
			global $ci;
			$ct = GetVar('ct');

			$child_id = GetVar('child');
			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			foreach ($this->handlers as $handler)
			{
				if (!$handler->Swap($ci, $ct)) return;
			}

			if (!empty($context->ds->FieldInputs))
			foreach ($context->ds->FieldInputs as $name => $data)
			{
				if (is_array($data))
				{
					if ($data[1] == 'file')
					{
						//Move Source to tmp_source
						$files = glob("{$data[2]}/{$ci}.*");
						foreach ($files as $file)
						{
							$info = pathinfo("{$data[2]}/{$file}");
							rename($file, "{$data[2]}/tmp_{$info['filename']}.{$info['extension']}");
						}

						//Move Target to Source
						$files = glob("{$data[2]}/{$ct}.*");
						foreach ($files as $file)
						{
							$info = pathinfo("{$data[2]}/{$file}");
							rename($file, "{$data[2]}/{$ci}.{$info['extension']}");
						}

						//Move tmp_source to Target
						$files = glob("{$data[2]}/tmp_{$ci}.*");
						foreach ($files as $file)
						{
							$info = pathinfo("{$data[2]}/{$file}");
							rename($file, "{$data[2]}/{$ct}.{$info['extension']}");
						}
					}
				}
			}

			$context->ds->Swap(array($context->ds->id => $ci), array($context->ds->id => $ct), $context->ds->id);
		}*/
		else if ($act == 'delete')
		{
			$ci = GetState($this->Name.'_ci');

			$child_id = GetVar('child');
			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			if (count($this->handlers) > 0)
			{
				$data = $context->ds->GetOne(array($context->ds->id => $ci));
				foreach ($this->handlers as $handler)
				{
					if (!$handler->Delete($ci, $data)) return;
				}
			}
			if (!empty($context->ds->FieldInputs))
			foreach ($context->ds->FieldInputs as $name => $in)
			{
				if (strtolower(get_class($in)) == 'forminput')
				{
					if ($in->type == 'file')
					{
						$files = glob("{$in->valu}/{$ci}_{$name}.*");
						foreach ($files as $file) unlink($file);
					}
				}
			}
			$context->ds->Remove(array($context->ds->id => $ci));
		}

		if ($this->type == CONTROL_SIMPLE)
		{
			if (file_exists($ci))
				$this->values = unserialize(file_get_contents($ci));
			else
				$this->values = array();
		}
	}

	/**
	 * Looks like it converts database rows to an array for DataToSel or
	 * something.
	 *
	 * @param array $items
	 * @param mixed $sel
	 * @deprecated No idea where it came from.
	 * @return array
	 */
	function GetSelArray($items, $sel)
	{
		$ret = array();
		foreach ($items as $i)
		{
			$ret[$i->id] = array($i, $i->id == $sel);
		}
		return $ret;
	}

	/**
	 * Gets a selection mask, for using 'selects' types and bitmasking the
	 * results.
	 *
	 * @param array $items
	 * @param int $sel
	 * @return array
	 */
	function GetSelMask($items, $sel)
	{
		$ret = array();
		foreach ($items as $id => $i)
		{
			$i->selected = ($id & $sel) > 0;
			$ret[$id] = $i;
		}
		return $ret;
	}

	/**
	 * Gets the rendered HTML for this editor.
	 *
	 * @param string $target Filename that uses this editor.
	 * @param mixed $ci ID of current item (eg. GetVar('ci'))
	 * @return string
	 */
	function Get()
	{
		global $me;
		//$act = GetVar($this->Name.'_action');

		$ret['name'] = $this->Name;

		$act = GetVar($this->Name.'_action');
		$q = GetVar($this->Name.'_q');

		//if (isset($ci) && $act == 'edit') $this->state = STATE_EDIT;
		$ret['ds'] = $this->ds;
		if ($act != 'edit' && !empty($this->ds->DisplayColumns)
			&& ($this->Behavior->Search && isset($q)))
			$ret['table'] = $this->GetTable($me, $act, $q);
		else $ret['table'] = null;
		$ret['forms'] = $this->GetForms($me, $act,
			GetVar('editor') == $this->Name ? GetVar('child') : null);
		return $ret;
	}

	/**
	 * Builds a recursive tree of editable items.
	 *
	 * @param array $items Items to be inserted into the tree.
	 * @return TreeNode
	 * @see GetTable
	 */
	function BuildTree($items)
	{
		if (!empty($items))
		{
			//Columns
			//* Gather all columns required for display and relation.
			//Children
			//* Map child names to child index.
			$cols[$this->ds->table] = array($this->ds->id => 1);
			if (!empty($this->ds->DisplayColumns))
			foreach (array_keys($this->ds->DisplayColumns) as $col)
				$cols[$this->ds->table][$col] = $this->ds->id == $col;

			if (!empty($this->ds->children))
			foreach ($this->ds->children as $ix => $child)
			{
				$children[$child->ds->table] = $ix;
				$cols[$child->ds->table][$child->parent_key] = 1;
				$cols[$child->ds->table][$child->child_key] = 0;
				if (!empty($child->ds->DisplayColumns))
				foreach (array_keys($child->ds->DisplayColumns) as $col)
				{
					$cols[$child->ds->table][$col] = 0;
				}
			}

			//Flats
			// * Convert each item into separated TreeNodes
			// * Associate all indexes by table, then id

			$flats = array();

			//Iterate all the resulting database rows.
			foreach ($items as $ix => $item)
			{

				//Iterate the columns that were created in step 1.
				foreach ($cols as $table => $columns)
				{
					//This will store all the associated data in the treenode
					//for the editor to reference while processing the treee.
					$data = array();
					$skip = false;

					//Now we're iterating the display columns.
					foreach ($columns as $column => $id)
					{
						//This column is not associated with a database row.
						if (is_numeric($column)) continue;

						//Table names are included to avoid ambiguity.
						$colname = $table.'_'.$column;

						//ID would be specified if this is specified as a keyed
						//value.
						if ($id)
						{
							if (empty($item[$colname]))
							{
								$skip = true;
								break;
							}
							$idcol = $colname;
						}
						$data[$colname] = $item[$colname];
					}
					if (!$skip)
					{
						$tn = new TreeNode($data);
						$tn->id = $item[$idcol];
						$flats[$table][$item[$idcol]] = $tn;
					}
				}
			}

			//Tree
			// * Construct tree out of all items and children.

			$tree = new TreeNode('Root');

			foreach ($flats as $table => $items)
			{
				foreach ($items as $ix => $node)
				{
					$child_id = isset($children[$table]) ? $children[$table] : null;

					if (isset($children[$table]))
					{
						$ckeycol = $this->ds->children[$child_id]->child_key;
						$pid = $node->data["{$table}_{$ckeycol}"];
					}
					else $pid = 0;

					$node->data['_child'] = $child_id;

					if ($pid != 0)
						$flats[$this->ds->table][$pid]->children[] = $node;
					else
						$tree->children[] = $node;
				}
			}
			//Put child table children above related
			//children, helps to understand the display.
			if (count($this->ds->children) > 0) $this->FixTree($tree);
			return $tree;
		}
		return null;
	}

	/**
	 * Fixes a tree of items so that foreign children appear on the top. Makes
	 * it much more readable.
	 *
	 * @param TreeNode $tree
	 * @see BuildTree
	 */
	function FixTree(&$tree)
	{
		usort($tree->children, array($this, "SortByChild"));
		if (!empty($tree->children))
		foreach ($tree->children as $cnode) $this->FixTree($cnode);
	}

	/**
	 * Simple callback to sort items by a child, used by FixTree
	 *
	 * @access private
	 * @param TreeNode $a
	 * @param TreeNode $b
	 * @return int
	 * @see FixTree
	 * @see BuildTree
	 */
	function SortByChild($a, $b)
	{
		if (isset($a->data['_child']))
			return ($a->data['_child'] > $b->data['_child']) ? -1 : 1;
		return 0;
	}

	/**
	 * Gets the HTML rendered table portion of this editor.
	 *
	 * @param string $target Filename that is using this editor.
	 * @param mixed $ci Currently editing item (eg. GetVar('ci')).
	 * @return string
	 * @access private
	 */
	function GetTable($target, $ci)
	{
		$ret = null;
		if (empty($this->ds->DisplayColumns)) return null;
		if ($this->type == CONTROL_BOUND)
		{
			$cols = array();

			//Build columns so nothing overlaps (eg. id of this and child table)

			$cols["{$this->ds->table}_{$this->ds->id}"] =
				$this->ds->table.'.'.$this->ds->id;

			if (!empty($this->ds->DisplayColumns))
			foreach ($this->ds->DisplayColumns as $col => $disp)
			{
				if (is_numeric($col)) continue;
				$cols["{$this->ds->table}_{$col}"] =
					$this->ds->table.'.'.$col;
			}

			$joins = null;
			if (!empty($this->ds->children))
			foreach ($this->ds->children as $child)
			{
				$joins = array();

				//Parent column of the child...
				$cols["{$child->ds->table}_{$child->child_key}"] =
					$child->ds->table.'.'.$child->child_key;

				//Coming from another table, we gotta join it in.
				if ($child->ds->table != $this->ds->table)
				{
					$joins[$child->ds->table] = "{$child->ds->table}.
						{$child->child_key} = {$this->ds->table}.
						{$child->parent_key}";

					//We also need to get the column names that we'll need...
					$cols["{$child->ds->table}_{$child->ds->id}"] =
						$child->ds->table.'.'.$child->ds->id;
					if (!empty($child->ds->DisplayColumns))
					foreach ($child->ds->DisplayColumns as $col => $disp)
					{
						$cols["{$child->ds->table}_{$col}"] =
							$child->ds->table.'.'.$col;
					}
				}
			}

			/*$items = $this->ds->GetInternal($this->filter, $this->sort,
				null, $joins, $cols);*/

			$items = $this->ds->GetSearch($cols, GetVar($this->Name.'_q'),
				null, null, $this->sort, $this->filter);

			$root = $this->BuildTree($items);
		}
		//else { $sel = $ci; $this->state = STATE_EDIT; }

		if (isset($root))
		{
			$cols = array();
			$atrs = array();

			//Columns and column attributes.
			if (!empty($this->ds->DisplayColumns))
			foreach ($this->ds->DisplayColumns as $col => $disp)
			{
				$cols[$col] = "{$disp->text}";
				$atrs[] = $disp->attribs;
			}

			//Gather children columns.
			if (!empty($this->ds->children))
			foreach ($this->ds->children as $child)
			{
				if ($child->ds->table != $this->ds->table)
				if (!empty($child->ds->DisplayColumns))
				foreach ($child->ds->DisplayColumns as $col => $disp)
				{
					$cols[$col] = "<b>{$disp->text}</b>";
					$atrs[] = $disp->attribs;
				}
			}

			if ($this->sorting == ED_SORT_TABLE)
				$table = new SortTable($this->Name.'_table', $cols, $atrs);
			else
				$table = new Table($this->Name.'_table', $cols, $atrs);

			$rows = array();
			$this->AddRows($rows, $target, $root, 0);

			foreach ($rows as $ix => $row)
			{
				$class = $ix % 2 ? 'even' : 'odd';
				$table->AddRow($row, array('class' => $class));
			}

			$ret .= $table->Get(array('class' => 'editor'));
		}
		return $ret;
	}

	/**
	 * Recursively populates $rows with child items.
	 *
	 * @param array $rows Referenced rows that are being populated.
	 * @param string $target Filename of script using this editor.
	 * @param TreeNode $node Node of this item, for recursion.
	 * @param int $level Depth of these items.
	 * @access private
	 */
	function AddRows(&$rows, $target, $node, $level)
	{
		global $PERSISTS;

		if (!empty($node->children))
		foreach ($node->children as $cnode)
		{
			$ix = 0;
			$row = array();

			if (isset($cnode->data['_child']))
				$child_id = $cnode->data['_child'];

			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			//Don't display children that don't have a display to show.
			if (empty($context->ds->DisplayColumns)) continue;

			//Pad all existing columns to ensure proper width.
			$total_cells = count($this->ds->DisplayColumns);
			if (!empty($this->ds->children))
			foreach ($this->ds->children as $child)
				if ($child->ds->table != $this->ds->table)
					$total_cells += count($child->ds->DisplayColumns);
			$row = array_pad($row, $total_cells, '&nbsp;');

			//Move cursor (ix) to the first column we're displaying here.
			if (isset($child_id))
			{
				if ($this->ds->children[$child_id]->ds->table != $this->ds->table)
					$ix += count($this->ds->DisplayColumns);
				$i = 0;
				while ($i++ < $child_id-1)
				{
					$ix += count($this->ds->children[$i]->ds->DisplayColumns);
				}
			}

			//Show all displays for this context.
			if (!empty($context->ds->DisplayColumns))
			foreach ($context->ds->DisplayColumns as $col => $disp)
			{
				$disp_index = $context->ds->table.'_'.$col;

				//Callback mapped
				if (isset($disp->callback))
				{
					$row[$ix++] = call_user_func_array($disp->callback,
						array($this->ds, $cnode->data, $disp_index, $col));
				}
				//Regular field
				else
				{
					if (isset($cnode->data[$disp_index]))
					{
						$row[$ix++] = array(
							htmlspecialchars(stripslashes($cnode->data[$disp_index])),
							array('class' => 'editor_cell', 'id' => "{$this->Name}:{$col}:{$cnode->id}")
						);
					}
				}
			}

			$url_defaults = array('editor' => $this->Name);
			if (isset($child_id)) $url_defaults['child'] = $child_id;

			if (!empty($PERSISTS)) $url_defaults = array_merge($url_defaults, $PERSISTS);

			$p = GetRelativePath(dirname(__FILE__));

			if ($this->Behavior->AllowEdit)
			{
				$url_edit = URL($target, array_merge(array(
					$this->Name.'_action' => 'edit',
					$this->Name.'_ci' => $cnode->id), $url_defaults));
				$url_del = URL($target, array_merge(array(
					$this->Name.'_action' => 'delete',
					$this->Name.'_ci' => $cnode->id), $url_defaults));
				$row[] = "<a href=\"$url_edit#box_{$this->Name}_forms\"><img
					src=\"{$p}/images/edit.png\" alt=\"Edit\" title=\"Edit
					Item\" class=\"png\" /></a>";
				$row[] = "<a href=\"$url_del#{$this->Name}_table\"
					onclick=\"return confirm('Are you sure?')\"><img
					src=\"{$p}/images/delete.png\" alt=\"Delete\" title=\"Delete
					Item\" class=\"png\" /></a>";
			}

			// @TODO Bring this tree system back to life!
			//$row[0] = str_repeat("&nbsp;", $level*4).$row[0];

			//Sorting should be done by javascript.

			/*if ($this->sorting == ED_SORT_MANUAL && count($node->children) > 1)
			{
				//We can possibly swap up
				if ($index > 0)
				{
					//This is a child of some sort, but it doesn't match the
					//prior child, this would require a change of parents.
					if ($node->children[$index-1]->data['_child'] == $cnode->data['_child'])
					{
						$args = array(
							'ci' => $cnode->id,
							'ct' => $node->children[$index-1]->id,
							'ca' => $this->name.'_swap'
						);
						if (isset($PERSISTS)) $args = array_merge($PERSISTS, $args);
						$url = URL($target, $args);
						$row[] = GetButton($url, 'up.png', 'Up', 'class="png"');
					}
				}
				else $row[] = '&nbsp;';
				if ($index < count($node->children)-1 && $node->children[$index+1]->data['_child'] == $cnode->data['_child'])
				{
					$args = array(
						'ci' => $cnode->id,
						'ct' => $node->children[$index+1]->id,
						'ca' => $this->name.'_swap'
					);
					if (isset($PERSISTS)) $args = array_merge($PERSISTS, $args);
					$url = URL($target, $args);
					$row[] = GetButton($url, 'down.png', 'Down', 'class="png"');
				}
				else $row[] = '&nbsp;';
			}
			else { $row[] = '&nbsp;'; $row[] = '&nbsp;'; }*/

			$rows[] = $row;

			$this->AddRows($rows, $target, $cnode, $level+1);
		}
	}

	/**
	 * Gets the form portion of this editor.
	 *
	 * @param string $target Filename of script that uses this editor.
	 * @param mixed $ci Current Item (eg. GetVar('ci')).
	 * @param int $state Current state of the editor.
	 * @param int $curchild Current child by DataSet Relation.
	 * @return string
	 */
	function GetForm($state, $curchild = null)
	{
		$fullname = $this->Name;
		if ($curchild != null) $fullname .= '_'.$curchild;
		$ci = GetState($this->Name.'_ci');

		if ($this->type == CONTROL_BOUND)
		{
			$context = isset($curchild) ? $this->ds->children[$curchild] :
				$this;

			if (!isset($this->ds)) Error("<br />What: Dataset is not set.
				<br />Where: EditorData({$this->name})::GetForm.
				<br />Why: This editor was not created with a proper dataset.");

			$joins = array();
			foreach ($this->handlers as $handler)
			{
				$join = $handler->GetJoins();
				if (!empty($join)) $joins = array_merge($joins, $join);
			}

			$sel = $state == STATE_EDIT ? $context->ds->Get(
				array($context->ds->id => $ci), null, null, $joins) : null;

			$ds = $context->ds;
		}
		else
		{
			$ds = $this->ds;
			if (!empty($ds->FieldInputs))
			foreach (array_keys($ds->FieldInputs) as $n)
			{
				if (isset($this->values[$n]))
					$ds->FieldInputs[$n]->valu =
						stripslashes($this->values[$n]);
			}
		}

		if (!empty($ds->FieldInputs))
		{
			$frm = new Form($fullname, null, false);

			if (isset($ds->Validation))
			{
				$frm->Validation = $ds->Validation;
				$frm->Errors = $ds->Errors;
			}

			if ($state == STATE_EDIT || $this->type != CONTROL_BOUND)
				$frm->AddHidden($this->Name.'_ci', $ci);

			global $PERSISTS;
			if (!empty($PERSISTS))
			foreach ($PERSISTS as $key => $val) $frm->AddHidden($key, $val);

			if (isset($curchild))
			{
				$frm->AddHidden('parent', $ci);
				$frm->AddHidden('child', $curchild);
			}

			foreach ($ds->FieldInputs as $col => $in)
			{
				if (is_object($in))
				{
					if ($in->type == 'custom') //Callback
					{
						$cb = $in->valu;
						call_user_func($cb, isset($sel) ? $sel : null, $frm);
						continue;
					}
					else if ($in->type == 'select')
					{
						if (isset($sel) && isset($in->valu[$sel[0][$col]]))
							$in->valu[$sel[0][$col]]->selected = true;
					}
					else
					{
						if ($in->type == 'password') $in->valu = '';
						else if (isset($sel[0][$col]))
						{
							if ($in->type == 'date')
								$in->valu = MyDateTimestamp($sel[0][$col]);
							else $in->valu = $sel[0][$col];
						}
						//If we bring this back, make sure setting explicit
						//values in DataSet::FormInputs still works.
						//else { $in->valu = null; }
					}

					$in->name = $col;

					$frm->AddInput($in);
				}
				//This ends up disabling static columns eg. 'colname' => 'NOW()';
				//if we're going to insert text, just use a numeric column.
				//else if (is_string($in)) $frm->AddInput($in);
				else if (is_numeric($col)) $frm->AddInput('&nbsp;');
			}

			foreach ($this->handlers as $handler)
			{
				//Use plural objects to compliment the joins property.
				//For some reason I change this to a single item when
				//it can be multiple.

				$handler->GetFields($frm,
				isset($sel) ? $sel[0][$this->ds->id] : null,
				isset($sel) ? $sel : null);
			}

			$frm->State = $state == STATE_EDIT || $this->type != CONTROL_BOUND
				? 'Update' : 'Create';
			$frm->Description = $ds->Description;
			$frm->AddInput(
				$frm->GetSubmitButton($this->Name.'_action', $frm->State).
				($state == STATE_EDIT && $this->type == CONTROL_BOUND ?
				 '<input type="submit" name="'.$this->Name.'_action" value="Cancel" />'
				 : null)
			);

			return $frm;
		}
		return null;
	}

	/**
	 * Get update and possibly children's create forms for the lower
	 * section of this editor.
	 *
	 * @param string $target Target script asking for this information.
	 * @param mixed $ci Identifier of current object.
	 * @param int $curchild Current child.
	 * @return string
	 */
	function GetForms($curchild = null)
	{
		$ret = null;
		$context = $curchild != null ? $this->ds->children[$curchild] : $this;

		$ci = GetState($this->Name.'_ci');
		$ca = GetVar($this->Name.'_ca');

		$frm = $this->GetForm($this->state, $curchild);
		if ($frm != null) $ret[] = $frm;

		if (isset($ci) && $ca == 'edit')
		{
			if (!empty($context->ds->children))
			foreach ($context->ds->children as $ix => $child)
			{
				if (isset($child->ds->FieldInputs))
				{
					$ret[] = $this->GetForm(STATE_CREATE, $ix);
				}
			}
		}
		return $ret;
	}

	function TagForms($t, $guts)
	{
		global $me;

		$out = '';
		$forms = $this->GetForms();
		$vp = new VarParser();
		if (!empty($forms))
		foreach ($forms as $frm)
		{
			$d['form_title'] = "{$frm->State} {$frm->Description}";
			$d['form_content'] = $frm->Get("method=\"post\" action=\"{$me}\"",
				'class="form"');
			$out .= $vp->ParseVars($guts, $d);
		}
		return $out;
	}

	function TagPart($guts, $attribs)
	{
		$this->parts[$attribs['TYPE']] = $guts;
	}

	/**
	 * Gets a standard user interface for a single editor's Get() method.
	 * @param string $target Target script to post to.
	 * @param array $editor_return Return value of EditorData::Get().
	 * @param string $form_atrs Additional form attributes.
	 * @return string Rendered html of associated objects.
	 */
	function GetUI()
	{
		require_once('h_template.php');

		//$editor_return = $this->Get($target, $ci);

		$t = new Template();
		$t->ReWrite('forms', array($this, 'TagForms'));
		$t->ReWrite('part', array($this, 'TagPart'));
		$t->Set('name', $this->Name);
		$t->Set('plural', Plural($this->Name));

		if (!empty($this->ds))
			$t->Set('table_title', Plural($this->ds->Description));

		global $me;
		$t->Set('table', $this->GetTable($me, GetState($this->Name.'_ci')));

		/*if (!empty($editor_return['forms']))
		{
			$forms = null;
			foreach ($editor_return['forms'] as $frm)
			{
				$forms .= GetBox('box_user_form', "{$frm->State} {$frm->Description}",
					$frm->Get('method="post" action="'.$me.'"', 'class="form"'));
			}
			$t->Set('forms', $forms);
		}
		else $t->Set('forms', '');*/
		return $t->Get(dirname(__FILE__).'/temps/editor.xml');
	}

	function Reset()
	{
		unset($_SESSION[$this->Name.'_action']);
		unset($_SESSION[$this->Name.'_ci']);
	}
}

class EditorDataBehavior
{
	public $AllowEdit = true;
	public $Search = true;
	public $Group;
}

class DisplayData
{
	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var DataSet
	 */
	public $ds;

	public $joins;

	public $Behavior;

	/**
	 * @param string $name Name of this display for state management.
	 * @param DataSet $ds Associated dataset to collect information from.
	 */
	function DisplayData($name, $ds)
	{
		$this->name = $name;
		$this->ds = $ds;
		$this->Behavior = new DisplayDataBehavior();
	}

	/**
	 * Available to calling script to prepare any actions that may be ready to be
	 * performed.
	 * @access public
	 */
	function Prepare()
	{
		$ca = GetVar('ca');

		if ($ca == 'update')
		{
			$ci = GetVar('ci');
			$up = array();
			foreach ($this->ds->FieldInputs as $col => $fi)
			{
				$fi->name = $col;
				// Sub table, we're going to need to clear and re-create the
				// associated table rows.
				$ms = null;
				if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
				{
					$join = $this->joins[$ms[1]];
					$cond = $join->Condition;
					$join->DataSet->Remove(array($cond[0] => $ci));
					$vals = GetVar($ms[2]);
					if (!empty($vals))
					foreach ($vals as $val)
					{
						$add = array($cond[0] => $ci);
						$add[$ms[2]] = $val;
						$join->DataSet->Add($add);
					}
				}
				else $up[$col] = $fi->GetData();
			}
			$this->ds->Update(array($this->ds->id => $ci), $up);
		}
	}

	/**
	 * @param string $target Target script to interact with.
	 * @param string $ca Current action to execute.
	 */
	function Get($target, $ca)
	{
		$ret = '';
		$q = GetVar('q');

		if ($ca == 'edit' && $this->Behavior->AllowEdit)
		{
			$ci = GetVar('ci');

			if (!empty($this->ds->FieldInputs))
			{
				foreach (array_keys($this->ds->FieldInputs) as $col)
				{
					// This is a sub table, we GROUP_CONCAT these for
					// finding later if need be.
					$ms = null;
					if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
						$cols[$ms[2]] =
							DeString("GROUP_CONCAT(DISTINCT {$ms[2]})");
					else $cols[$col] = $col;
				}

				$item = $this->ds->GetOne(array($this->ds->id => $ci),
					$this->joins, $cols, $this->ds->id);

				$frm = new Form('frmEdit');
				$frm->AddHidden('editor', $this->name);
				$frm->AddHidden('ca', 'update');
				$frm->AddHidden('ci', $ci);

				foreach ($this->ds->FieldInputs as $col => $fi)
				{
					if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
						$col = $ms[2];
					$fi->name = $col;
					if ($fi->type == 'select' || $fi->type == 'selects'
						|| $fi->type == 'radios' || $fi->type == 'checks')
					{
						$sels = explode(',', $item[$col]);
						if (!empty($sels))
						foreach($sels as $sel)
							if (isset($fi->valu[$sel]))
								$fi->valu[$sel]->selected = true;
						if (isset($fi->valu[$item[$col]]))
							$fi->valu[$item[$col]]->selected = true;
					}
					else $fi->valu = $item[$col];

					$frm->AddInput($fi);
				}
				$frm->AddInput(new FormInput(null, 'submit', null, 'Update'));
				$ret .= $frm->Get('action="'.$target.'" method="post"');
			}

			if (!empty($this->Editors))
			foreach ($this->Editors as $join => $editor)
			{
				if (preg_match('/([^.]+)\.(.*)/', $join, $ms))
					$editor->filter = "{$ms[2]} = $ci";
				$ret .= $editor->GetUI($target, $ci);
			}
		}

		else if ($ca == 'search')
		{
			$fs = GetVar('field');
			$ss = GetVar('search');

			$query = "SELECT *";

			foreach (array_keys($ss) as $col)
			{
				$fi = $this->ds->FieldInputs[$col];
				if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
					$query .= ", GROUP_CONCAT(DISTINCT {$col}) AS gc_{$ms[2]}";
			}

			$query .= " FROM `{$this->ds->table}`";
			$query .= DataSet::JoinClause($this->joins);

			if (!empty($ss))
			{
				$where = ' WHERE';
				$having = ' HAVING';

				$ix = 0;
				foreach (array_keys($ss) as $col)
				{
					if (!isset($fs[$col])) continue;

					$fi = $this->ds->FieldInputs[$col];
					$fi->name = $col;
					if ($ix++ > 0) $where .= ' AND';

					if ($fi->type == 'select') $where .= " $col IN ($fs[$col])";
					else if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
						$having .= " FIND_IN_SET('".implode(',', $fs[$col])."', gc_$ms[2]) > 0";
					else if ($fi->type == 'date')
					{
						$where .= " $col BETWEEN '".
						TimestampToMySql(DateInputToTS($fs[$col][0]), false).'\' AND \''.
						TimestampToMySql(DateInputToTS($fs[$col][1]), false).'\'';
					}
					//else $where .= " $col LIKE '%".$fs[$col]."%'";
					else $where .= " $col LIKE '%".$fi->GetData($fs[$col])."%'";
				}

				if (strlen($where) > 6) $query .= $where;
				$query .= ' GROUP BY app_id';
				if (strlen($having) > 7) $query .= $having;

				$result = $this->ds->GetCustom($query);
			}
			else $result = array();

			$items = GetFlatPage($result, GetVar('cp', 0), 10);
			if (!empty($items) && !empty($this->ds->DisplayColumns))
			{
				$ret .= "<table>";
				foreach ($items as $ix => $i)
				{
					$ret .= <<<EOD
<tr><td class="header">
	<label><input type="checkbox" value="{$i[$this->ds->id]}" />Compare</label>
</td>
EOD;
					if ($this->Behavior->AllowEdit) $ret .= <<<EOD
<td align="right" class="header">
	<a href="{$target}?editor={$this->name}&ca=edit&ci={$i[$this->ds->id]}">Edit</a>
</td></tr>
EOD;
					foreach ($this->ds->DisplayColumns as $f => $dc)
					{
						$val = !empty($dc->callback) ?
							call_user_func($dc->callback, $this->ds, $i, $f) : $i[$f];
						$ret .= "<tr><td align=\"right\">{$dc->text}</td><td>$val</td></tr>\n";
					}
					if ($ix > 10) break;
				}
				$ret .= "</table>";
				if (count($result) > 10)
				{
					$ret .= GetPages($result, 10, array('editor' => 'employee',
						'ca' => 'search', 'q' => $q, 'fields' => $fs));
				}
			}
		}

		else $ret = $this->GetSearch($target);

		return $ret;
	}

	/**
	 * @param string $target Target script to interact with.
	 */
	function GetSearch($target)
	{
		if (empty($this->SearchFields)) { Error("You should specify a few SearchField items"); return null; }
		$frm = new Form('frmSearch');
		$frm->AddHidden('ca', 'search');
		if (isset($GLOBALS['editor'])) $frm->AddHidden('editor', $GLOBALS['editor']);
		$frm->AddInput(new FormInput('Search', 'custom', null, array($this, 'callback_fields')));
		$frm->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Search'));
		return $frm->Get('action="'.$target.'" method="post"');
	}

	function callback_fields()
	{
		$ret = '<table>';
		foreach ($this->SearchFields as $col)
		{
			if (!isset($this->ds->FieldInputs[$col])) continue;
			$fi = $this->ds->FieldInputs[$col];
			$fi->name = 'field['.$col.']';
			$ret .= '<tr><td valign="top"><label><input type="checkbox"
				value="1" name="search['.$col.']" onclick="show(\''.$col.'\',
				this.checked)" /> '.$fi->text.'</label></td>';
			if ($fi->type == 'date')
			{
				$fi->name = 'field['.$col.'][0]';
				$ret .= ' <td valign="top" style="display: none" id="'.$col.'"> from '.$fi->Get($this->name).' to ';
				$fi->name = 'field['.$col.'][1]';
				$ret .= $fi->Get($this->name)."</td>\n";
			}
			else $ret .= '<td style="display: none" id="'.$col.'">'.$fi->Get($this->name).'</td>';
			$ret .= '</tr>';
		}
		return $ret.'</table>';
	}
}

class DisplayDataBehavior
{
	public $AllowEdit;

	function AllowAll()
	{
		$this->AllowEdit = true;
	}
}

class FileAccessHandler extends EditorHandler
{
	/**
	 * Top level directory to allow access.
	 * @var string
	 */
	private $root;

	/**
	 * Constructor for this object, sets required properties.
	 * @param string $root Top level directory to allow access.
	 */
	function FileAccessHandler($root)
	{
		require_once('a_file.php');
		$this->root = $root;
	}

	/**
	 * Recurses a single folder to collect access information out of it.
	 * @param string $root Source folder to recurse into.
	 * @param int $level Amount of levels deep for tree construction.
	 * @param int $id Identifier of the object we are looking for access to.
	 * @return array Array of SelOption objects.
	 */
	static function PathToSelOption($root, $id, $level)
	{
		$ret = array();

		//Get information on this item.
		$so = new SelOption($root);
		$fi = new FileInfo($root);
		if (!empty($fi->info['access']) && isset($fi->info['access'][$id]))
			$so->selected = true;
		$ret[$root] = $so;

		//Recurse children.
		$dp = opendir($root);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$fp = $root.'/'.$file;
			if (is_dir($fp)) $ret = array_merge($ret,
				FileAccessHandler::PathToSelOption($fp, $id, $level+1));
		}

		return $ret;
	}

	/**
	 * Recurses a single folder to set access information in it.
	 * @param string $root Source folder to recurse into.
	 * @param int $id Identifier of the object we are looking for access to.
	 * @param array $accesses Series of access items that will eventually get set.
	 */
	static function RecurseSetPerm($root, $id, $accesses)
	{
		//Set information on this item.
		$fi = new FileInfo($root);

		if (!empty($accesses) && in_array($root, $accesses))
			$fi->info['access'][$id] = 1;
		else if (isset($fi->info['access']))
			unset($fi->info['access'][$id]);
		$fi->SaveInfo();

		//Recurse children.
		$dp = opendir($root);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$fp = $root.'/'.$file;
			if (is_dir($fp)) FileAccessHandler::RecurseSetPerm($fp, $id, $accesses);
		}
	}

	static function RecurseGetPerm($root, $id)
	{
		$ret = array();
		$fi = new FileInfo($root);
		if (isset($fi->info['access'][$id])) $ret[] = $root;

		$dp = opendir($root);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$fp = $root.'/'.$file;
			if (is_dir($fp)) $ret = array_merge($ret, FileAccessHandler::RecurseGetPerm($fp, $id));
		}

		return $ret;
	}

	static function Copy($root, $src, $dst)
	{
		FileAccessHandler::RecurseSetPerm($root, $dst, FileAccessHandler::RecurseGetPerm($root, $src));
	}

	/**
	 * Called when a file or folder gets updated.
	 */
	function Update($id, &$original, &$update)
	{
		$accesses = GetVar('accesses');
		$this->RecurseSetPerm($this->root, $id, $accesses);
		return true;
	}

	function Created($id, $inserted)
	{
		$accesses = GetVar('accesses');
		$this->RecurseSetPerm($this->root, $id, $accesses);
	}

	/**
	 * Adds a series of options to the form associated with the given file.
	 * @todo Rename to AddFields
	 */
	function GetFields(&$form, $id, $data)
	{
		$form->AddInput(new FormInput('Accessable Folders', 'selects',
			'accesses', $this->PathToSelOption($this->root, $id, 0), array('SIZE' => 8)));
	}
}

class EditorText
{
	public $Name;
	private $item;

	function EditorText($name, $item)
	{
		$this->Name = $name;
		$this->item = $item;
	}

	function Prepare($action)
	{
		if ($action == 'update')
		{
			$fp = fopen($this->item, 'w');
			fwrite($fp, stripslashes(GetVar($this->Name.'_body')));
			fclose($fp);
		}
	}

	function Get($target)
	{
		$frmRet = new Form($this->Name);
		$frmRet->AddHidden('ca', 'update');

		$content = file_exists($this->item) ?
			stripslashes(file_get_contents($this->item)) : '';

		$frmRet->AddInput(new FormInput(null, 'area', 'body', $content,
			array('ROWS' => 30)));
		$frmRet->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));

		return $frmRet->Get('method="post" action="'.$target.'"');
	}
}

class EditorUpload
{
	public $Name;
	private $item;

	function EditorUpload($name, $item)
	{
		$this->Name = $name;
		$this->item = $item;
	}

	function Prepare($action)
	{
		if ($action == 'update')
		{
			move_uploaded_file($_FILES['file']['tmp_name'], $this->item);
		}
	}

	function Get($target)
	{
		$frmRet = new Form($this->Name);
		$frmRet->AddHidden('ca', 'update');

		$frmRet->AddInput(new FormInput(null, 'file', 'file'));
		$frmRet->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));

		return $frmRet->Get('enctype="multipart/form-data" method="post" action="'.$target.'"');
	}
}

?>
