<?php

/**
 * Enter description here...
 */
class DisplayColumn
{
	/**
	 * Text of the column in the display table.
	 *
	 * @var string
	 */
	public $text;
	/**
	 * Name of the associate dataset column.
	 *
	 * @var string
	 */
	public $column;
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
	 * @param string $column
	 * @param mixed $callback
	 * @param string $attribs
	 * @return DisplayColumn
	 */
	function DisplayColumn($text, $column, $callback = null, $attribs = null)
	{
		$this->text = $text;
		$this->column = $column;
		$this->callback = $callback;
		$this->attribs = $attribs;
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
	 * Whether or not this editor allows sorting.
	 *
	 * @var bool
	 */
	public $sorting;
	/**
	 * Callback function for when an item is created.
	 *
	 * @var mixed
	 * @deprecated  use $handler instead.
	 */
	public $oncreate;
	/**
	 * Calback function to be called when an item is updated.
	 *
	 * @var unknown_type
	 * @deprecated  use $handler instead.
	 */
	public $onupdate;
	/**
	 * Callback function for when an item is deleted.
	 *
	 * @var mixed
	 * @deprecated use $handler instead.
	 */
	public $ondelete;
	/**
	 * Callback function for when an item is swapped with another.
	 *
	 * @var mixed
	 * @deprecated use $handler instead.
	 */
	public $onswap;

	/**
	 * Default constructor.
	 *
	 * @param $name string Name of this editor
	 * @param $ds DataSet Dataset for this editor to interact with.
	 * @param $fields array Array of items to allow editing.
	 * @param $display array Array of items to display in top table.
	 * @param $filter array Array to constrain editing to a given expression.
	 * @return EditorData
	 */
	function EditorData($name, $ds = null, $filter = null, $sort = null)
	{
		require_once('h_utility.php');
		$this->name = $name;
		$this->filter = $filter;
		if ($ds != null)
		{
			$this->ds = $ds;
			$this->sort = $sort;
			$this->type = CONTROL_BOUND;
		}
		else $this->type = CONTROL_SIMPLE;
		$this->sorting = true;
	}

	/**
	 * To be called before presentation, will process, verify and calculate any
	 * data to be used in the Get function.
	 *
	 * @param $action string Current action, usually stored in POST['ca']
	 */
	function Prepare($action)
	{
		$parent = GetVar('parent');
		$this->state = GetVar('ca') == $this->name.'_edit' ? STATE_EDIT : STATE_CREATE;
		if ($action == $this->name.'_create')
		{
			$insert = array();
			if (isset($parent)) $fields = $this->ds->children[GetVar('child')]->ds->fields;
			else $fields = $this->ds->fields;
			foreach ($fields as $name => $data)
			{
				if (is_array($data))
				{
					$value = GetVar($data[0]);
					if ($data[1] == 'date')
					{
						$insert[$name] = $value[2].'-'.$value[0].'-'.$value[1];
					}
					else if ($data[1] == 'password' && strlen($value) > 0)
					{
						$insert[$data[0]] = md5($value);
					}
					else $insert[$data[0]] = $value;
				}
				else $insert[$name] = DeString($data);
			}
			if (isset($this->oncreate))
			{
				$handler = $this->oncreate;
				if (!$handler($insert)) return;
			}
			if (isset($parent))
			{
				$child = $this->ds->children[GetVar('child')];
				$insert[$child->child_key] = $parent;
				$child->ds->Add($insert);
			}
			else $this->ds->Add($insert);
		}
		else if ($action == $this->name.'_delete')
		{
			global $ci;
			if (isset($this->ondelete))
			{
				$handler = $this->ondelete;
				if (!$handler($ci)) return;
			}
			$this->ds->Remove(array($this->ds->id => $ci));
		}
		else if ($action == $this->name.'_update')
		{
			global $ci;
			$child = $this->ds->children[GetVar('child')];
			$update = array();
			foreach ($child->ds->fields as $name => $data)
			{
				if (is_array($data))
				{
					$value = GetVar($data[0]);
					if ($data[1] == 'date')
					{
						$update[$name] = $value[2].'-'.$value[0].'-'.$value[1];
					}
					else if ($data[1] == 'password')
					{
						if (strlen($value) > 0)
							$update[$data[0]] = md5($value);
					}
					else if ($data[1] == 'checkbox')
						$update[$data[0]] = ($value == 1) ? $value : 0;
					else if ($data[1] == 'selects')
					{
						$newval = 0;
						foreach ($value as $val) $newval |= $val;
						$update[$data[0]] = $newval;
					}
					else $update[$data[0]] = GetVar($data[0]);
				}
			}
			if ($this->type == CONTROL_BOUND)
				$child->ds->Update(array($this->ds->id => $ci), $update);

			if (isset($this->onupdate))
			{
				$handler = $this->onupdate;
				if (!$handler($update)) return;
			}
		}
		else if ($action == $this->name.'_swap')
		{
			global $ci;
			$ct = GetVar('ct');
			if (isset($this->onswap))
			{
				$handler = $this->onswap;
				if (!$handler($ci, $ct)) return;
			}
			$this->ds->Swap(array($this->ds->id => $ci), array($this->ds->id => $ct), 'id');
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
		global $errors, $PERSISTS, $xlpath;
		$ret = '';

		//Table
		$ret .= $this->GetTable($target, $ci);
		$ret .= $this->GetForms($target, $ci, GetVar('child'));
		return $ret;
	}

	/**
	 * Gets an UP image, usually used for sorting.
	 *
	 * @return string
	 * @access private
	 */
	function GetSwapButton($target, $src, $dst, $up)
	{
		return '<a href="'.$target.'?ca='.$this->name.'_swap&amp;ci='.$src.'&amp;ct='.$dst.'">
			<img src="lib/images/'. ($up ? 'up' : 'down'). '.png" alt='.($up ? 'Up' : 'Down').'/></a>';
	}

	/**
	 * Builds a recursive tree editable items.
	 *
	 * @param array $items Items to be inserted into the tree.
	 * @return TreeNode
	 * @see GetTable
	 */
	function BuildTree($items)
	{
		if (!empty($items))
		{
			//ids[child index] = {child table}_{id column}

			$ids = array(0 => $this->ds->table.'_'.$this->ds->id);

			if (!empty($this->ds->children))
			foreach ($this->ds->children as $ix => $child)
			{
				if ($child->ds->table != $this->ds->table)
				{
					$ids[$ix+1] = $child->ds->table.'_'.$child->ds->id;
				}
			}

			//node_link[child index + 1]
			//	array(index = {child table}_{child column})

			$node_link[0] = array("{$this->ds->table}_{$this->ds->id}");
			foreach ($this->ds->display as $disp)
				$node_link[0][] = "{$this->ds->table}_{$disp->column}";

			if (!empty($this->ds->children))
			foreach ($this->ds->children as $ix => $child)
			{
				$link = array("{$child->ds->table}_{$child->ds->id}");
				$link[] = $child->ds->table.'_'.$child->child_key;
				foreach ($child->ds->display as $disp)
					$link[] = "{$child->ds->table}_{$disp->column}";
				$node_link[$ix+1] = $link;
			}

			//flats = array(row id => treenode)

			$flats = array();

			foreach ($items as $ix => $item)
			{
				foreach ($node_link as $child_id => $link)
				{
					if (strlen($item[$ids[$child_id]]) > 0)
					{
						$data = array('_child' => $child_id);
						foreach ($link as $col) $data[$col] = $item[$col];
						$tn = new TreeNode($data);
						$tn->id = $item[$ids[$child_id]];
						$flats[$child_id][$item[$ids[$child_id]]] = $tn;
					}
				}
			}

			//Build tree of relations...

			$tree = new TreeNode('Root');

			foreach ($flats as $child_id => $items)
			{
				if ($child_id == 0) $child = $this;
				else $child = $this->ds->children[$child_id-1];

				foreach ($items as $id => $node)
				{
					if ($child_id != 0)
						$pid = $node->data[$this->ds->children[$child_id-1]->ds->table.'_'.$this->ds->id];
					$id = $node->id;
					if ($id)
					{
						if (isset($pid))
                            $flats[0][$pid]->children[] = $node;
						else
                            $tree->children[] = $node;
					}
				}
			}

			return $tree;
		}
		return null;
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
			$cols[$this->ds->table.'.'.$this->ds->id] =
				"{$this->ds->table}_{$this->ds->id}";
			if (!empty($this->ds->display))
			foreach ($this->ds->display as $ix => $disp)
			{
				$cols[$this->ds->table.'.'.$disp->column] =
					"{$this->ds->table}_{$disp->column}";
			}

			$joins = null;
			if (!empty($this->ds->children))
			foreach ($this->ds->children as $child)
			{
				$joins = array();

				//Parent column of the child...
				$cols[$child->ds->table.'.'.$child->child_key] = "{$child->ds->table}_{$child->child_key}";

					//Coming from another table, we gotta join it in.
				if ($child->ds->table != $this->ds->table)
				{
					$joins[$child->ds->table] = "{$child->ds->table}.{$child->child_key} = {$this->ds->table}.{$child->parent_key}";
					//We also need to get the column names that we'll need...
					$cols[$child->ds->table.'.'.$child->ds->id] = "{$child->ds->table}_{$child->ds->id}";
					foreach ($child->ds->display as $ix => $disp)
					{
						$cols[$child->ds->table.'.'.$disp->column] = "{$child->ds->table}_{$disp->column}";
					}
				}
			}

			$items = $this->ds->Get(null, $this->sort, $this->filter, $joins, $cols);
			//Build a whole tree out of the items and children.
			$root = $this->BuildTree($items);
		}
		else { $sel = $ci; $this->state = STATE_EDIT; }

		if (isset($root))
		{
			$cols = array();
			$atrs = array();
			foreach ($this->ds->display as $disp)
			{
				$cols[] = "<b>{$disp->text}</b>";
				$atrs[] = $disp->attribs;
			}
			
			//Gather children columns.
			if (!empty($this->ds->children)) foreach ($this->ds->children as $child)
			{
				if ($child->ds->table != $this->ds->table)
				foreach ($child->ds->display as $disp)
				{
					$cols[] = "<b>{$disp->text}</b>";
					$atrs[] = $disp->attribs;
				}
			}

			$table = new Table($this->name.'_table', $cols, $atrs);

			$rows = array();
			$this->AddRows($rows, $target, $root, 0);

			foreach ($rows as $row) $table->AddRow($row);

			$ret .= "<a name=\"{$this->name}_table\" />";
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
		global $xlpath;
		
		if (!empty($node->children))
		foreach ($node->children as $index => $cnode)
		{
			$row = array();

			$child_id = $cnode->data['_child'];
			if (isset($this->ds->children[$child_id-1]))
				$child = $this->ds->children[$child_id-1];

			//Pad any missing initial display columns...
			for ($ix = 0; $ix < $child_id; $ix++) $row[$ix] = '&nbsp;';

			//Show all displays for this item.
			foreach ($this->ds->display as $disp)
			{
				if (isset($disp->callback)) //Callback for field
				{
					$callback = $disp->callback;
					$row[$child_id] = $callback($item->data, $disp->column);
				}
				//Regular field
				else
				{
					$disp_index = $this->ds->table.'_'.$disp->column;
					if (isset($cnode->data[$disp_index]))
						$row[$child_id] = stripslashes($cnode->data[$disp_index]);
				}

				//Show all children displays...
				if (isset($child))
				if ($child->ds->table != $this->ds->table)
				foreach ($child->ds->display as $disp)
				{
					$row[$child_id] = $cnode->data[$child->ds->table.'_'.$disp->column];
				}
			}

			$url_defaults = array('editor' => $this->name, 'child' => $child_id);
			if (!empty($PERSISTS)) $url_defaults = array_merge($url_defaults, $PERSISTS);

			else $row[] = null;

			//Pad any additional display columns...
			for ($ix = $child_id+1; $ix < count($this->ds->children)+1; $ix++) $row[$ix] = "&nbsp;";

			$url_edit = MakeURI($target, array_merge(array('ca' => $this->name.'_edit', 'ci' => $cnode->id), $url_defaults));
			$url_del = MakeURI($target, array_merge(array('ca' => $this->name.'_delete', 'ci' => $cnode->id), $url_defaults));
			$row[] = "<a href=\"$url_edit#{$this->name}_editor\">Edit</a>";
			$row[] = "<a href=\"$url_del#{$this->name}_table\" onclick=\"return confirm('Are you sure?')\">Delete</a>";

			$row[0] = str_repeat('&nbsp;', $level*4).$row[0];

			if ($this->sorting && count($node->children) > 1)
			{
				if ($index > 0)
					$row[] = $this->GetSwapButton($target, $cnode->id, $node->children[$index-1]->id, true);
				else $row[] = '&nbsp;';
				if ($index < count($node->children)-1)
					$row[] = $this->GetSwapButton($target, $cnode->id, $node->children[$index+1]->id, false);
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
	 * @param int $curchild Current child by DataSet Relation.
	 * @return string
	 */
	function GetForm($target, $ci, $state, $curchild = null)
	{
		$ret = null;

		if (isset($curchild)) $child = $this->ds->children[$curchild];
		else $child = $this;

		if ($state == CONTROL_BOUND)
		{
			$sel = $state == STATE_EDIT ? $child->ds->GetOne(array($this->ds->id => $ci)) : null;
		}

		if (!empty($child->ds->fields))
		{
			$frm = new Form('form'.$this->name, array('align="right"', null, null));
			$frm->AddHidden('editor', $this->name);
			$frm->AddHidden('ca', $state == STATE_EDIT ? $this->name.'_update' : $this->name.'_create');
			if ($state == STATE_EDIT) $frm->AddHidden('ci', $ci);
			else if (isset($child)) $frm->AddHidden('parent', $ci);
			if (isset($child)) $frm->AddHidden('child', $curchild);
			foreach ($child->ds->fields as $text => $data)
			{
				if (is_array($data))
				{
					if ($data[1] == 'custom')
					{
						$fname = $data[2];
						$fname($sel, $frm);
						continue;
					}
					else if ($data[1] == 'select')
					{
						if (isset($sel)) $data[2][$sel[$data[0]]]->selected = true;
						$value = $data[2];
					}
					else if ($data[1] == 'selects')
					{
						$value = $this->GetSelMask($data[2], isset($sel) ? $sel[$data[0]] : null);
					}
					else if ($data[1] == 'password')
					{
						$value = '';
					}
					else if (isset($sel[$data[0]])) $value = $sel[$data[0]];
					else if (isset($data[2])) { $data[2]; }
					else $value = null;
					if (is_string($value)) $value = stripslashes($value);
					$frm->AddInput(
						$text,
						$data[1],
						$data[0],
						$value,
						'style="width: 100%"'. ((isset($data[3])) ? ' '.$data[3] : null),
						isset($errors[$data[0]]) ? $errors[$data[0]] : isset($data[4]) ? $data[4] : null);
				}
				else $frm->AddRow(array('&nbsp;'));
			}
			$frm->AddRow(array(
				null,
				'<input type="submit" value="'.($state == STATE_EDIT ? 'Update' : 'Create').'"/> '.
				($state == STATE_EDIT && $this->type == CONTROL_BOUND ? '<input type="button" value="Cancel" onclick="javascript: document.location.href=\''.$target.'?editor='.$this->name.'\'"/>' : null),
				null
			));
			$ret .= "<a name=\"{$this->name}_editor\"></a>";
			$ret .= $frm->Get("action=\"$target\" method=\"post\"", 'width="100%"');
			return $ret;
		}
	}

	function GetForms($target, $ci, $curchild = null)
	{
		$ret = GetBox('box_edit', 'Edit Selected Item',
			$this->GetForm($target, $ci, $this->state, $curchild),
			'templates/box.html');
		if (isset($ci))
		{
			for ($ix = 0; $ix < count($this->ds->children); $ix++)
			{
				if (isset($this->ds->children[$ix]->ds->fields))
				{
					$ret .= GetBox('box_create_child_'.$this->ds->children[$ix]->ds->table,
						'Create new child',
						$this->GetForm($target, $ci, STATE_CREATE, $ix),
						'templates/box.html');
				}
			}
		}
		return $ret;
	}
}

?>