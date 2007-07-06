<?php

define('ED_SORT_NONE',   0);
define('ED_SORT_MANUAL', 1);
define('ED_SORT_TABLE',  2);

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
	 * @return DisplayColumn
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
	 * @return boolean true by default (meant to be overriden)
	 */
	function Create(&$data) { return true; }

	/**
	 * After an item is created, this contains the id of the new item. You
	 * cannot halt the item from being inserted at this point.
	 *
	 * @param array $inserted Data that has been inserted (including the id).
	 * @return boolean true by default (meant to be overridden)
	 */
	function Created($id, $inserted) { return true; }

	/**
	 * Before an item is updated, this function is called. If you extend this
	 * object and return false, it will not be updated.
	 *
	 * @param array $data Context
	 * @param array $original Original data before update.
	 * @param array $update Columns suggested to get updated.
	 * @return boolean true by default (meant to be overridden)
	 */
	function Update($id, &$original, &$update) { return true; }

	/**
	 * Called before and item is deleted. If you extend this object and return
	 * false, it will not be deleted.
	 *
	 * @param int $id ID of deleted items
	 * @param array $data Context
	 * @return boolean true by default (meant to be overridden)
	 */
	function Delete($id, &$data) { return true; }

	/**
	 * Called to retrieve additional fields for the editor form object.
	 * @param object $form Contextual form suggested to add fields to.
	 * @param array $data Data related to the action (update/insert).
	 */
	function GetFields(&$form, $id, $data) {}

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
	 * @return HandlerFile
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
	 *
	 * @param array $data row data
	 * @return boolean True to allow the creation.
	 */
	function Created($id, $data)
	{
		$target = "{$this->target}/{$data[$this->column]}";
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
					if ($data[$col] == $val && !file_exists($target))
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

	/**
	 * Called when an item is updated.
	 *
	 * @param array $data
	 * @param array $update
	 * @return boolean
	 */
	function Update($id, &$data, &$update)
	{
		$source = "{$this->target}/{$data[$this->column]}";
		if (file_exists($source))
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
	 * @return boolean
	 */
	function Delete($id, &$data)
	{
		if (strlen($data[$this->column]) < 1) return true;
		if (file_exists("{$this->target}/{$data[$this->column]}"))
			DelTree("{$this->target}/{$data[$this->column]}");
		return true;
	}
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
	public $name;
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
	public $onswap;
	/**
	 * An array of handlers used for extra functionality of create, update,
	 * delete and swap.
	 *
	 * @var array
	 */
	public $handlers;

	/**
	 * Default constructor.
	 *
	 * @param string $name Name of this editor
	 * @param DataSet $ds Dataset for this editor to interact with.
	 * @param array $filter Array to constrain editing to a given expression.
	 * @param array $sort Array of 'column' => 'desc/asc'.
	 * @return EditorData
	 */
	function EditorData($name, $ds = null, $filter = null, $sort = null)
	{
		require_once('h_utility.php');
		require_once('h_display.php');
		$this->name = $name;
		$this->filter = $filter;
		$this->handlers = array();

		if ($ds != null)
		{
			$this->ds = $ds;
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
	 * @param Handler $handler
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
	 * @return void
	 */
	function Prepare($action)
	{
		// Don't speak unless spoken to.
		if (GetVar('editor') != $this->name) return;

		if ($this->sorting == ED_SORT_TABLE)
			$this->sort = array(GetVar('sort', $this->ds->id) => GetVar('order', 'ASC'));

		$this->state = $action == $this->name.'_edit' ? STATE_EDIT : STATE_CREATE;
		if ($action == $this->name.'_create')
		{
			$insert = array();
			$child_id = GetVar('child');
			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			$fields = $context->ds->FieldInputs;
			foreach ($fields as $col => $in)
			{
				if (is_object($in))
				{
					$value = GetVar($col);
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
							'dst' => $data[2], //Destination folder
							'ext' => $ext
						);
						$insert[$col] = $ext;
					}
					else if ($in->type == 'selects')
						$insert[$col] = $value;
					else $insert[$col] = $value;
				}
				else if (is_string($in)) $insert[$col] = DeString($in);
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

			if (!empty($moves))
			foreach ($moves as $move)
			{
				$target = "{$move['dst']}/{$id}_{$data[0]}.{$move['ext']}";
				move_uploaded_file($move['tmp'], $target);
				chmod($target, 0777);
			}

			foreach ($this->handlers as $handler)
				$handler->Created($id, $insert);
		}
		else if ($action == $this->name.'_update')
		{
			global $ci;
			$child_id = GetVar('child');
			$context = $child_id != null ? $this->ds->children[$child_id] : $this;
			$update = array();
			foreach ($context->ds->FieldInputs as $col => $in)
			{
				if (is_object($in))
				{
					$value = GetVar($col);
					if ($in->type == 'date')
					{
						$insert[$data[0]] = $value[2].'-'.$value[0].'-'.$value[1];
					}
					else if ($in->type == 'password')
					{
						if (strlen($value) > 0) $update[$col] = md5($value);
					}
					else if ($in->type == 'checkbox')
						$update[$col] = ($value == 1) ? 1 : 0;
					else if ($in->type == 'selects')
					{
						$update[$data[0]] = $value;
					}
					else if ($in->type == 'file')
					{
						if (strlen($value['tmp_name']) > 0)
						{
							$files = glob("{$data[2]}/{$ci}_{$data[0]}.*");
							foreach ($files as $file) unlink($file);
							$ext = substr(strrchr($value['name'], '.'), 1);
							$src = $value['tmp_name'];
							$dst = "{$data[2]}/{$ci}_{$data[0]}.{$ext}";
							move_uploaded_file($src, $dst);
							$update[$data[0]] = $ext;
						}
					}
					else $update[$col] = GetVar($col);
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
		}
		else if ($action == $this->name.'_swap')
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
		}
		else if ($action == $this->name.'_delete')
		{
			global $ci;

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
			foreach ($context->ds->FieldInputs as $name => $data)
			{
				if (is_array($data))
				{
					if ($data[1] == 'file')
					{
						$files = glob("{$data[2]}/{$ci}_{$data[0]}.*");
						foreach ($files as $file) unlink($file);
					}
				}
			}
			$context->ds->Remove(array($context->ds->id => $ci));
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
	function Get($target, $ci = null)
	{
		$ca = GetVar('ca');

		if (isset($ci) && $ca == $this->name.'_edit') $this->state = STATE_EDIT;
		$ret['ds'] = $this->ds;
		if ($ca != $this->name.'_edit' && !empty($this->ds->DisplayColumns))
			$ret['table'] = $this->GetTable($target, $ci);
		$ret['forms'] = $this->GetForms($target, $ci,
			GetVar('editor') == $this->name ? GetVar('child') : null);
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
			foreach ($this->ds->DisplayColumns as $col => $disp)
			{
				$cols[$this->ds->table][$col] = 0;
			}

			if (!empty($this->ds->children))
			foreach ($this->ds->children as $ix => $child)
			{
				$children[$child->ds->table] = $ix;
				$cols[$child->ds->table][$child->parent_key] = 1;
				$cols[$child->ds->table][$child->child_key] = 0;
				if (!empty($child->ds->DisplayColumns))
				foreach ($child->ds->DisplayColumns as $col => $disp)
				{
					$cols[$child->ds->table][$col] = 0;
				}
			}

			//Flats
			//* Convert each item into separated TreeNodes
			//* Associate all indexes by table, then id

			$flats = array();

			foreach ($items as $ix => $item)
			{
				foreach ($cols as $table => $columns)
				{
					$data = array();
					$skip = false;
					foreach ($columns as $column => $id)
					{
						$colname = $table.'_'.$column;
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
			//* Construct tree out of all items and children.

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
		if ($this->type == CONTROL_BOUND)
		{
			$cols = array();

			//Build columns so nothing overlaps (eg. id of this and child table)

			$cols["{$this->ds->table}_{$this->ds->id}"] =
				$this->ds->table.'.'.$this->ds->id;

			if (!empty($this->ds->DisplayColumns))
			foreach ($this->ds->DisplayColumns as $col => $disp)
			{
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

			$items = $this->ds->GetInternal($this->filter, $this->sort,
				null, $joins, $cols);

			$root = $this->BuildTree($items);
		}
		else { $sel = $ci; $this->state = STATE_EDIT; }

		if (isset($root))
		{
			$cols = array();
			$atrs = array();

			//Columns and column attributes.
			if (!empty($this->ds->DisplayColumns))
			foreach ($this->ds->DisplayColumns as $col => $disp)
			{
				$cols[$col] = "<b>{$disp->text}</b>";
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
				$table = new SortTable($this->name.'_table', $cols, $atrs);
			else
				$table = new Table($this->name.'_table', $cols, $atrs);

			$rows = array();
			$this->AddRows($rows, $target, $root, 0);

			foreach ($rows as $ix => $row)
			{
				$class = $ix % 2 == 0 ? 'row_even' : 'row_odd';
				$table->AddRow($row, "class=\"{$class}\"");
			}

			$ret .= $table->Get('class="editor"');
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
		foreach ($node->children as $index => $cnode)
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
						array($cnode->data, $disp_index));
				}
				//Regular field
				else
				{
					if (isset($cnode->data[$disp_index]))
						$row[$ix++] = htmlspecialchars(stripslashes($cnode->data[$disp_index]));
				}
			}

			$url_defaults = array('editor' => $this->name);
			if (isset($child_id)) $url_defaults['child'] = $child_id;

			if (!empty($PERSISTS)) $url_defaults = array_merge($url_defaults, $PERSISTS);

			$p = GetRelativePath(dirname(__FILE__));

			$url_edit = URL($target, array_merge(array('ca' => $this->name.'_edit', 'ci' => $cnode->id), $url_defaults));
			$url_del = URL($target, array_merge(array('ca' => $this->name.'_delete', 'ci' => $cnode->id), $url_defaults));
			$row[] = "<a href=\"$url_edit#{$this->name}_editor\"><img src=\"{$p}/images/edit.png\" alt=\"Edit\" title=\"Edit Item\" class=\"png\" /></a>";
			$row[] = "<a href=\"$url_del#{$this->name}_table\" onclick=\"return confirm('Are you sure?')\"><img src=\"{$p}/images/delete.png\" alt=\"Delete\" title=\"Delete Item\" class=\"png\" /></a>";

			$row[0] = str_repeat("&nbsp;", $level*4).$row[0];

			if ($this->sorting == ED_SORT_MANUAL && count($node->children) > 1)
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
			else { $row[] = '&nbsp;'; $row[] = '&nbsp;'; }

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
	function GetForm($target, $ci, $state, $curchild = null)
	{
		$context = isset($curchild) ? $this->ds->children[$curchild] : $this;

		$fullname = 'form_'.$state.'_'.$context->ds->table;

		if ($state == CONTROL_BOUND)
		{
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
		}

		if (!empty($context->ds->FieldInputs))
		{
			$frm = new Form($fullname);

			if (isset($context->ds->Validation))
			{
				$frm->Validation = $context->ds->Validation;
				$frm->Errors = $context->ds->Errors;
			}
			$frm->AddHidden('editor', $this->name);
			$frm->AddHidden('ca', $state == STATE_EDIT ? $this->name.'_update' :
				$this->name.'_create');

			if ($state == STATE_EDIT) $frm->AddHidden('ci', $ci);
			if (isset($curchild))
			{
				$frm->AddHidden('parent', $ci);
				$frm->AddHidden('child', $curchild);
			}

			foreach ($context->ds->FieldInputs as $col => $in)
			{
				if (is_object($in))
				{
					if ($in->type == 'custom') //Callback
					{
						$cb = $data[2];
						call_user_func($cb, isset($sel) ? $sel : null, $frm);
						continue;
					}
					else if ($in->type == 'select')
					{
						if (isset($sel) && isset($sel[0][$col]) > 0)
							$in->valu[$sel[0][$col]]->selected = true;
					}
					else if ($in->type == 'selects')
					{
						if (isset($sel) && isset($sel[0][$data[0]]))
						$value = $this->GetSelMask($data[2], isset($sel) &&
							strlen($data[0]) > 0 ? $sel[0][$data[0]] : null);
						else $value = $data[2];
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
						else if (isset($data[2])) { $in->valu = $data[2]; }
						else { $in->valu = null; }
					}

					$in->name = $col;

					$frm->AddInput($in);
				}
				else if (is_numeric($col)) $frm->AddRow('&nbsp;');
			}

			foreach ($this->handlers as $handler)
			{
				$handler->GetFields($frm,
				isset($sel[0]) ? $sel[0][$this->ds->id] : null,
				isset($sel[0]) ? $sel[0] : null);
			}

			$frm->State = $state == STATE_EDIT ? 'Update' : 'Create';
			$frm->Description = $context->ds->Description;
			$frm->AddInput(
				$frm->GetSubmitButton('butSubmit', $frm->State).
				($state == STATE_EDIT && $this->type == CONTROL_BOUND ? '<input type="button" value="Cancel" onclick="javascript: document.location.href=\''.$target.'?editor='.$this->name.'\'"/>' : null)
			);

			return $frm;
		}
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
	function GetForms($target, $ci, $curchild = null)
	{
		$ret = null;
		$context = $curchild != null ? $this->ds->children[$curchild] : $this;

		$frm = $this->GetForm($target, $ci, $this->state, $curchild);
		if ($frm != null) $ret[] = $frm;

		if (isset($ci) && GetVar('ca') == $this->name.'_edit')
		{
			if (!empty($context->ds->children))
			foreach ($context->ds->children as $ix => $child)
			{
				if (isset($child->ds->FieldInputs))
				{
					$ret[] = $this->GetForm($target, $ci, STATE_CREATE, $ix);
				}
			}
		}
		return $ret;
	}

	/**
	 * Gets a standard user interface for a single editor's Get() method.
	 * @param string $target Target script to post to.
	 * @param array $editor_return Return value of EditorData::Get().
	 * @param string $form_atrs Additional form attributes.
	 * @return string Rendered html of associated objects.
	 */
	static function GetUI($target, $editor_return, $form_atrs = null)
	{
		$ret = null;
		if (isset($editor_return['table'])) $ret .= GetBox('box_items',
			Plural($editor_return['ds']->Description), $editor_return['table']);
		if (!empty($editor_return['forms']))
		foreach ($editor_return['forms'] as $frm)
			$ret .= GetBox('box_user_form', "{$frm->State} {$frm->Description}",
				$frm->Get('method="post" action="'.$target.'"'.(isset($form_atrs) ? ' '.$form_atrs : null), 'class="form"'));
		return $ret;
	}
}

?>
