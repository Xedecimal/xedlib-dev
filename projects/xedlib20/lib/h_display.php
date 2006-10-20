<?php

/**
 * @package Presentation
 */

/**
 * Quick macro to retreive a generated box.
 * @param string $name Name of the box (good for javascript calls to getElementById()).
 * @param string $title Title of the returned box.
 * @param string $body Raw text contents of the returned box.
 * @param string $template Template file to use for the returned box.
 * 
 * @example test_present.php
 */
function GetBox($name, $title, $body, $template = null)
{
	$box = new Box();
	$box->name = $name;
	$box->title = $title;
	$box->out = $body;
	return $box->Get($template);
}

/**
 * A simple themed box.
 */
class Box
{
	/**
	 * For unique identifier.
	 *
	 * @var String
	 */
	public $name;
	/**
	 * Title to be displayed in this box, placement depends on the theme.
	 *
	 * @var String
	 */
	public $title;
	/**
	 * Standard text to be output inside this box.
	 *
	 * @var String
	 */
	public $out;
	/**
	 * Template filename to use with this box.
	 *
	 * @var String
	 */
	public $template;

	/**
	* Constructs a new box object with empty title and body.
	*/
	function Box()
	{
		$this->title = "";
		$this->out = "";
		$this->name = "";
		$this->template = "template_box.html";
	}

	/**
	* Returns the rendered html output of this Box.
	* @param String $template Filename of template to use for display.
	*/
	function Get($template = null)
	{
		$temp = isset($template) ? $template : $this->template;
		if (file_exists($temp))
		{
			$t = new Template();
			$t->set("box_name", $this->name);
			$t->set("box_title", $this->title);
			$t->set("box_body", $this->out);
			return $t->get($temp);
		}
		$ret  = "<!-- Start Box: {$this->title} -->\n";
		$ret .= "<table class=\"box_main\">\n";
		$ret .= "  <tr class=\"box_row_even\">\n";
		$ret .= "    <th>\n";
		$ret .= "      <b>{$this->title}</b>\n";
		$ret .= "    </th>\n";
		$ret .= "  </tr>\n";
		$ret .= "  <tr class=\"box_row_odd\">\n";
		$ret .= "    <td class=\"box_body\">\n";
		$ret .= $this->out;
		$ret .= "    </td>\n";
		$ret .= "  </tr>\n";
		$ret .= "</table>\n";
		$ret .= "<!-- End Box {$this->title} -->\n";
		return $ret;
	}
}

/**
 * A generic table class to manage a top level table, with children rows and cells.
 */
class Table
{
	/**
	 * Name of this table (only used as identifer in html comments).
	 *
	 * @var string
	 */
	public $name;
	/**
	 * Column headers for this table (displayed at the top of the rows).
	 *
	 * @var array
	 */
	public $cols;
	/**
	 * Each row array that makes up the bulk of this table.
	 *
	 * @var array
	 */
	public $rows;
	/**
	 * Array of attributes on a per-column basis.
	 *
	 * @var array
	 */
	public $atrs;
	/**
	 * Array of attributes on a per-row basis.
	 *
	 * @var array
	 */
	public $rowattribs;

	/**
	 * Instantiates this table with the specified attributes.
	 * @param $name string Unique name only used in Html comments for identification.
	 * @param $cols array Default columns headers to display ( eg. array("Column1", "Column2") ).
	 * @param $attributes array An array of attributes for each column ( eg. array('width="100%"', 'valign="top"') ).
	 */
	function Table($name, $cols, $attributes = NULL)
	{
		$this->name = $name;
		$this->cols = $cols;
		$this->atrs = $attributes;
	}

	/**
	 * Adds a single row to this table, the widest row is how spanned
	 * out the complete table will be when calling GetTable().
	 * @param $row array A string array of columns.
	 * @param $attribs array Attributes to be applied to each column.
	 */
	function AddRow($row, $attribs = null)
	{
		$this->rows[] = $row;
		$this->rowattribs[] = $attribs;
	}

	/**
	 * Returns the complete html rendered table for output purposes.
	 * @param $attributes string A set of html attributes to apply to the entire table. (eg. 'class="mytableclass"')
	 * @returns The complete html rendered table.
	 */
	function Get($attributes = null)
	{
		if ($attributes != null) $attributes = " " . $attributes;
		$ret = "<!-- Start Table: {$this->name} -->\n";
		$ret .= "<table$attributes>\n";

		if ($this->cols)
		{
			$ret .= "<tr>\n";
			for ($x = 0; $x < count($this->cols); $x++)
			{
				$col = $this->cols[$x];
				if (isset($this->atrs)) $atrs = " " . $this->atrs[$x % count($this->atrs)];
				else $atrs = "";
				$ret .= "<td$atrs>{$col}</td>\n";
			}
			$ret .= "</tr>\n";
		}

		if ($this->rows)
		{
			if (!isset($this->cols)) //No columns, need to find out how many cells this table spans.
			{
				$span = 0;
				foreach ($this->rows as $row) if (count($row) > $span) $span = count($row);
				for ($ix = 0; $ix < $span; $ix++) $this->cols[] = '';
			}
			foreach ($this->rows as $ix => $row)
			{
				$ret .= "<tr {$this->rowattribs[$ix]}>\n";
				if (count($row) < count($this->cols)) $span = " colspan=\"" . (count($this->cols) - count($row) + 1) . "\"";
				else $span = "";
				$x = 0;
				if (is_array($row))
				{
					foreach ($row as $val) //$x = 0; $x < count($row); $x++)
					{
						if (isset($this->atrs)) $atrs = ' '.$this->atrs[$x % count($this->atrs)];
						else if (is_array($val)) { $atrs = ' '.$val[0]; $val = $val[1]; }
						else $atrs = "";
						$ret .= "<td$span$atrs>{$val}</td>\n";
						$x++;
					}
				}
				else $ret .= "<td{$span}{$atrs}>{$row}</td>\n";
				$ret .= "</tr>\n";
			}
		}
		$ret .= "</table>\n";
		$ret .= "<!-- End Table: {$this->name} -->\n";
		return $ret;
	}
}

/**
 * A table with columns that can sort, a bit
 * more processing with a better user experience.
 * 
 */
class SortTable extends Table
{
	/**
	* Instantiate this table with columns that can use
	* sort values.
	* @param $name Unique name only used in Html comments for identification.
	* @param $cols Default columns headers to display ( eg. array("Column1", "Column2") ).
	* @param $attributes An array of attributes for each column ( eg. array('width="100%"', 'valign="top"') ).
	*/
	function SortTable($name, $cols, $attributes = NULL)
	{
		if (!is_array($cols)) YPEError("If you are not going to specify any columns, you might as well just use Table.");

		$this->name = $name;

		$sort = GetVar("sort");
		$order = GetVar("order", "ASC");

		global $me, $cs, $ca;
		$this->cols = array();
		foreach ($cols as $id => $disp)
		{
			$append = "";
			if ($sort == $id)
			{
				$append = $order == 'ASC' ? ' &uarr;' : ' &darr;';
				($order == "ASC") ? $order = "DESC" : $order = "ASC";
			}
			$this->cols[] = "<a href=\"$me?cs=$cs&amp;ca=$ca&amp;sort=$id&amp;order=$order\">$disp</a>$append";
		}

		$this->atrs = $attributes;
	}
}

/**
 * A web page form, with functions for easy field creation and layout.
 * @todo Create sub classes for each input type.
 */
class Form extends Table
{
	/**
	 * Unique name of this form (used in html / js / identifying).
	 *
	 * @var string
	 */
	public $name;
	/**
	 * Hidden fields stored from AddHidden()
	 *
	 * @var array
	 */
	private $hiddens;
	/**
	 * Form tag attributes, "name" => "value" pairs.
	 *
	 * @var array
	 */
	public $attribs;
	/**
	 * Actual output.
	 *
	 * @var string
	 */
	public $out;
	/**
	 * Whether to use persistant vars or not.
	 *
	 * @var bool
	 */
	public $Persist;
	/**
	 * Associated validator.
	 *
	 * @var Validation
	 */
	public $Validation;

	/**
	* Instantiates this form with a unique name.
	* @param $name string Unique name only used in Html comments for identification.
	* @param $tblAttribs array Array of table's column attributes.
	*/
	function Form($name, $tblAttribs = null)
	{
		$this->Table($name, null, $tblAttribs);
		$this->name = $name;
		$this->attribs = array();

		$this->Persist = true;
	}

	/**
	* Adds a hidden field to this form.
	* @param $name string The name attribute of the html field.
	* @param $value mixed The value attribute of the html field.
	* @param $attribs string Attributes to append on this field.
	* @param $general bool Whether this is a general name. It will not
	* have the form name prefixed on it.
	*/
	function AddHidden($name, $value, $attribs = null, $general = false)
	{
		$this->hiddens[] = array($name, $value, $attribs, $general);
	}

	/**
	* An input tag wrapper with some extensibility.
	* @param $text string Text displayed left of the input field.
	* @param $type string Type attribute of the input.
	* @param $name string Name attribute of the input field.
	* @param $value mixed Default value of the input field.
	* @param $helptext string Displayed on the right side of the attribute
	* (also good for displaying errors in validation)
	* @param $attributes string Any other attributes you wish to include.
	*/
	function AddInput($text, $type, $name, $value = null, $attributes = null, $helptext = null)
	{
		if (isset($attributes)) $attributes = ' '.$attributes;
		if (isset($this->Validation))
			$helptext = $this->Validation->GetError($name).$helptext;
		switch ($type)
		{
			case "area":
				$strout = "<textarea name=\"".htmlspecialchars($name)."\"$attributes>";
				if ($value) $strout .= $value;
				$strout .= "</textarea>";
				break;
			case "select":
				$strout = '<select id="'.htmlspecialchars($name).'" name="'.$name."\"{$attributes}>\n";
				if (is_array($value))
				{
					$ogstarted = false;
					foreach ($value as $id => $opt)
					{
						$selected = $opt->selected ? ' selected="selected"' : "";
						if ($opt->group)
						{
							if ($ogstarted) $strout .= "</optgroup>";
							$strout .= "<optgroup label=\"{$opt->text}\">";
							$ogstarted = true;
						}
						else $strout .= "<option value=\"{$id}\"$selected>".htmlspecialchars($opt->text)."</option>\n";
					}
					if ($ogstarted) $strout .= "</optgroup>";
				}
				$strout .= "</select>\n";
				break;
			case 'selects':
				$strout = '<select id="'.htmlspecialchars($name).'" name="'.$name."[]\" multiple=\"multiple\"$attributes>\n";
				if (is_array($value))
				{
					$ogstarted = false;
					foreach ($value as $id => $opt)
					{
						$selected = $opt->selected ? ' selected="selected"' : "";
						if ($opt->group)
						{
							if ($ogstarted) $strout .= "</optgroup>";
							$strout .= "<optgroup label=\"{$opt->text}\">";
							$ogstarted = true;
						}
						else $strout .= "<option value=\"{$id}\"$selected>".htmlspecialchars($opt->text)."</option>\n";
					}
					if ($ogstarted) $strout .= "</optgroup>";
				}
				$strout .= "</select>\n";
				break;
			case "yesno":
				$strout =  "<input type=\"radio\" name=\"$name\" value=\"{$value[0]}\" $attributes> Yes\n";
				$strout .= "<input type=\"radio\" name=\"$name\" value=\"{$value[1]}\" checked=\"checked\" $attributes> No\n";
				break;
			case "date":
				$strout = GetInputDate($name, $value, false);
				break;
			case "datetime":
				$strout = GetInputDate($name, $value, true);
				break;
			case "image_upload":
				$strout = "<img src=\"$value\"/><br/>\n";
				$strout .= "Upload Image: <input type=\"file\" name=\"{$name}\"/>\n";
				break;
			case 'checkbox':
				$attributes .= ' value="1"';
				if ($value) $attributes .= ' checked="checked"';
				$strout = "<input id=\"$name\" type=\"$type\" name=\"$name\"$attributes />";
				break;
			case 'submit':
				if (isset($this->Validation))
					$attributes .= " onclick=\"return {$this->name}_check(1);\"";
			default:
				if (isset($value)) $val = ' value="'.htmlspecialchars($value).'"';
				else $val = "";
				$strout = "<input id=\"$name\" type=\"$type\" name=\"$name\"$attributes$val />";
				break;
		}
		if ($helptext != null) $this->AddRow(array('<label for="'.htmlspecialchars($name).'">'.$text.'</label>', $strout, $helptext));
		else $this->AddRow(array(strlen($text) > 0 ? "<label for=\"$name\">$text</label>" : null, $strout, null));
	}

	/**
	* Returns the complete html rendered form for output purposes.
	* @param $formAttribs string Additional form attributes (method, class, action, etc)
	* @param $tblAttribs string To be passed to Table::GetTable()
	* @returns The complete html rendered form.
	*/
	function Get($formAttribs = null, $tblAttribs = null)
	{
		global $PERSISTS;
		$ret  = "<!-- Begin Form: {$this->name} -->\n";
		if ($formAttribs != null) $formAttribs = " " . $formAttribs;
		$ret .= "<form$formAttribs";
		if (isset($this->attribs))
		{
			foreach ($this->attribs as $atr => $val) $ret .= " $atr=\"$val\"";
		}
		$ret .= ">\n";
		if ($this->Persist && !empty($PERSISTS))
		{
			foreach ($PERSISTS as $name => $value) $this->AddHidden($name, $value);
		}
		if ($this->hiddens)
		{
			foreach ($this->hiddens as $hidden)
			{
				$fname = $hidden[3] ? $hidden[0] : $this->name.'_'.$hidden[0];
				$ret .= "<input type=\"hidden\" id=\"$fname\" name=\"{$hidden[0]}\" value=\"{$hidden[1]}\"";
				if (isset($hidden[2])) $ret .= ' '.$hidden[2];
				$ret .= " />\n";
			}
		}
		$ret .= parent::Get($tblAttribs);
		$ret .= "</form>\n";
		$ret .= "<!-- End Form: {$this->name} -->\n";
		return $ret;
	}
}

/**
 * Enter description here...
 */
class SelOption
{
	/**
	 * The text of this option.
	 *
	 * @var string
	 */
	public $text;
	/**
	 * Whether this is a group header.
	 *
	 * @var bool
	 */
	public $group;
	/**
	 * Whether this option is selected by default.
	 *
	 * @var bool
	 */
	public $selected;

	/**
	 * Create a new select option.
	 *
	 * @param string $text The text of this option.
	 * @param unknown_type $group
	 * @param unknown_type $selected
	 * @return SelOption
	 */
	function SelOption($text, $group = false, $selected = false)
	{
		$this->text = $text;
		$this->group = $group;
		$this->selected = $selected;
	}
}

function MakeSelect($name, $value = null, $attributes = null, $selvalue = null)
{
	$strout = "<select name=\"$name\" $attributes>\n";
	$selid = 0;
	foreach ($value as $id => $option)
	{
		$selected = null;
		if (isset($selvalue))
		{
			if (isset($selvalue[$selid]) &&
			strlen($selvalue[$selid]) > 0 &&
			$selvalue[$selid] == $id)
			{
				$selected = ' selected="true"';
				$selid++;
			}
			else if ($selvalue == $id)
			{
				$selected = ' selected="true"';
			}
		}
		else if ($option->selected) $selected = ' selected="true"';
		$strout .= "<option value=\"{$id}\"$selected>{$option->text}</option>\n";
		$selected = null;
	}
	$strout .= "</select>\n";
	return $strout;
}

function DataToSel($result, $col_disp, $col_id, $default = 0, $none = null)
{
	$ret = null;
	if (isset($none)) $ret[0] = new SelOption($none, false, $default == 0);
	foreach ($result as $res)
	{
		$ret[$res[$col_id]] = new SelOption($res[$col_disp], false, $default == $res[$col_id]);
	}
	return $ret;
}

function ArrayToSelOptions($array, $default = null, $use_keys = true)
{
	$opts = array();
	foreach ($array as $ix => $item)
	{
		$o = new SelOption($item, false, $default == $item);
		$opts[$use_keys ? $ix : $item] = $o;
	}
	return $opts;
}

/**
 * Returns a DateTime picker
 * @param $name string Name of this field.
 * @param $timestamp int Date to initially display.
 * @param $include_time bool Whether or not to add time to the date.
 * @todo Get rid of this, use AddInput("", "date" / "datetime") instead.
 */
function GetInputDate($name = "", $timestamp = null, $include_time = false)
{
	if (is_array($timestamp))
	{
		if (isset($timestamp[5]))
			$timestamp = gmmktime($timestamp[3], $timestamp[4], $timestamp[5], $timestamp[0], $timestamp[1], $timestamp[2]);
		else
			$timestamp = gmmktime(0, 0, 0, $timestamp[0], $timestamp[1], $timestamp[2]);
	}
	if (is_string($timestamp))
	{
		if ($include_time) $timestamp = MyDateTimestamp($timestamp);
		else $timestamp = MyDateStamp($timestamp);
	}
	if (!isset($timestamp)) $timestamp = time();
	$strout = "";
	if ($include_time)
	{
		$strout = "<input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"" . date("H", $timestamp) . "\" alt=\"Hour\">\n";
		$strout .= ": <input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"" . date("i", $timestamp) . "\" alt=\"Minute\">\n";
	}
	$strout .= GetMonthSelect("{$name}[]", gmdate("n", $timestamp));
	$strout .= "/ <input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"" . gmdate("d", $timestamp) . "\" alt=\"Day\" />\n";
	$strout .= "/ <input type=\"text\" size=\"4\" name=\"{$name}[]\" value=\"" . gmdate("Y", $timestamp) . "\" alt=\"Year\" />\n";
	return $strout;
}

function GetInputYesNo($name, $value)
{
	return '<input type="radio" name="'.$name.'" value="0"'.
	($value ? null : ' checked="checked"').' /> No ' .
	'<input type="radio" name="'.$name.'" value="1"'.
	($value ? ' checked="checked"' : null).' /> Yes ';
}

define('STATE_CREATE', 0);
define('STATE_EDIT', 1);

define('CONTROL_SIMPLE', 0);
define('CONTROL_BOUND', 1);

function BoolCallback($val) { return $val ? 'Yes' : 'No'; }

/**
 * A node holds children.
 */
class TreeNode
{
	/**
	 * ID of this node (usually for database association)
	 *
	 * @var unknown_type
	 */
	public $id;
	/**
	 * Data associated with this node.
	 *
	 * @var unknown_type
	 */
	public $data;
	/**
	 * Child nodes of this node.
	 *
	 * @var unknown_type
	 */
	public $children;

	/**
	 * Create a new TreeNode object.
	 *
	 * @param $data mixed Data to associate with this node.
	 * @return TreeNode
	 */
	function TreeNode($data)
	{
		$this->data = $data;
		$this->children = array();
	}
}

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
			$this->ds->Swap(array('id' => $ci), array('id' => $ct), 'id');
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
		$ret .= $this->GetForm($target, $ci, GetVar('child'));
		return $ret;
	}

	/**
	 * Gets an UP image, usually used for sorting.
	 *
	 * @return string
	 * @access private
	 */
	function GetUpButton()
	{
		return '<img src="lib/images/up.png" />';
	}

	/**
	 * Gets a DOWN button, usually used for sorting.
	 *
	 * @return string
	 */
	function GetDownButton()
	{
		return '<img src="lib/images/down.png" />';
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
			$cols[$this->ds->table.'.'.$this->ds->id] = "{$this->ds->table}_{$this->ds->id}";
			foreach ($this->ds->display as $ix => $disp)
			{
				$cols[$this->ds->table.'.'.$disp->column] = "{$this->ds->table}_{$disp->column}";
			}
			if (!empty($this->ds->children)) foreach ($this->ds->children as $child)
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
			foreach ($this->ds->children as $child)
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

		if (!empty($node->children)) foreach ($node->children as $index => $cnode)
		{
			$row = array();

			$child_id = $cnode->data['_child'];
			$child = $this->ds->children[$child_id];

			//Pad any missing initial display columns...
			for ($ix = 0; $ix < $child_id; $ix++) $row[$ix] = '&nbsp;';

			//Show all displays...
			foreach ($child->ds->display as $disp)
			{
				if (isset($disp->callback)) //Callback for field
				{
					$callback = $disp->callback;
					$row[$child_id] = $callback($item->data, $disp->column);
				}
				//Regular field
				else $row[$child_id] = stripslashes($cnode->data[$child->ds->table.'_'.$disp->column]);

				if ($child->ds->table != $this->ds->table) foreach ($child->ds->display as $disp)
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

			$rows[] = $row;
			
			if ($this->sorting && count($cnode->children) > 1)
			{
				if ($index > 0) $child_row[] = $this->GetUpButton();
				if ($index < count($node->children)-1) $child_row[] = $this->GetDownButton();
			}

			$this->AddRows($rows, $target, $cnode, $level+1);
		}
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
			//Build a list of ids we are going to use (child index => child keys)

			$ids = array(0 => $this->ds->table.'_'.$this->ds->id);

			if (!empty($this->ds->children)) foreach ($this->ds->children as $ix => $child)
			{
				if ($child->ds->table != $this->ds->table)
				{
					$ids[$ix] = $child->ds->table.'_'.$child->ds->id;
				}
			}

			//Build a list of column to node associations...

			$flats = array();

			$node_link[0] = array("{$this->ds->table}_{$this->ds->id}");
			foreach ($this->ds->display as $disp) $node_link[0][] = "{$this->ds->table}_{$disp->column}";

			foreach ($this->ds->children as $ix => $child)
			{
				$link = array("{$child->ds->table}_{$child->ds->id}");
				$link[] = $child->ds->table.'_'.$child->child_key;
				foreach ($child->ds->display as $disp) $link[] = "{$child->ds->table}_{$disp->column}";
				$node_link[$ix] = $link;
			}

			//Build array of flat associations...

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
				$child = $this->ds->children[$child_id];

				foreach ($items as $id => $node)
				{
					$pid = $node->data[$child->ds->table.'_'.$child->child_key];
                    $id = $node->id;
					if ($id)
					{
						if ($pid)
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
	 * Gets the form portion of this editor.
	 *
	 * @param string $target Filename of script that uses this editor.
	 * @param mixed $ci Current Item (eg. GetVar('ci')).
	 * @param int $curchild Current child by DataSet Relation.
	 * @return string
	 */
	function GetForm($target, $ci, $curchild = null)
	{
		$ret = null;

		if (isset($curchild)) $child = $this->ds->children[$curchild];
		else $child = $this;

		if ($this->state == CONTROL_BOUND)
		{
			$sel = $this->state == STATE_EDIT ? $child->ds->GetOne(array($this->ds->id => $ci)) : null;
		}

		if (!empty($child->ds->fields))
		{
			$frm = new Form('form'.$this->name, array('align="right"', null, null));
			$frm->AddHidden('editor', $this->name);
			$frm->AddHidden('ca', $this->state == STATE_EDIT ? $this->name.'_update' : $this->name.'_create');
			if ($this->state == STATE_EDIT) $frm->AddHidden('ci', $ci);
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
						$value = $this->GetSelMask($data[2], $sel[$data[0]]);
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
				'<input type="submit" value="'.($this->state == STATE_EDIT ? 'Update' : 'Create').'"/> '.
				($this->state == STATE_EDIT && $this->type == CONTROL_BOUND ? '<input type="button" value="Cancel" onclick="javascript: document.location.href=\''.$target.'?editor='.$this->name.'\'"/>' : null),
				null
			));
			$ret .= "<a name=\"{$this->name}_editor\"></a>";
			$ret .= $frm->Get("action=\"$target\" method=\"post\"", 'width="100%"');
			if ($this->state == CONTROL_BOUND && !isset($child))
			{
				if (!empty($this->ds->children))
				{
					foreach ($this->ds->children as $ix => $child)
					{
						$de = new EditorData($this->name, $child->ds);
						$add = $de->GetForm($target, $sel[$child->parent_key], $ix);
						$ret .= $add;
					}
				}
			}
			return $ret;
		}
	}
}

define('ACCESS_GUEST', 0);
define('ACCESS_ADMIN', 1);

/**
 * Enter description here...
 */
class LoginManager
{
	/**
	 * Datasets that are associated with this LoginManager.
	 *
	 * @var array
	 */
	private $datasets;
	/**
	 * Type of this login manager. (CONTROL_SIMPLE or CONTROL_BOUND).
	 *
	 * @var int
	 */
	public $type;
	/**
	 * Static password for an unbound manager (must be MD5 prior to setting.).
	 *
	 * @var string
	 */
	public $pass;
	/**
	 * Access level of users that login with this manager. (ACCESS_GUEST or
	 * ACCESS_ADMIN)
	 *
	 * @var int
	 */
	public $access;

	/**
	 * Creates a new LoginManager.
	 *
	 * @return LoginManager
	 */
	function LoginManager()
	{
		$this->type = CONTROL_SIMPLE;
	}

	/**
	 * Processes the current login.
	 *
	 * @param $ca string Current persistant action.
	 * @param $passvar string Name of password session variable to manage.
	 * @param $uservar string Name of username session variable to manage.
	 * @return mixed Array of user data or null if bound or true or false if not bound.
	 */
	function Prepare($ca, $passvar = 'sespass', $uservar = null)
	{
		$check_user = ($this->type == CONTROL_BOUND) ? GetVar($uservar) : null;
		$check_pass = GetVar($passvar);

		if ($ca == 'login')
		{
			if ($this->type == CONTROL_BOUND)
			{
				$check_user = ($this->type == CONTROL_BOUND) ? $check_user = GetVar('auth_user') : null;
			 	SetVar($uservar, $check_user);
			}

			$check_pass = md5(GetVar('auth_pass'));
			SetVar($passvar, $check_pass);
		}
		if ($ca == 'logout')
		{
			$check_pass = null;
			UnsetVar($passvar);
			return false;
		}

		if ($this->type == CONTROL_BOUND)
		{
			foreach ($this->datasets as $ds)
			{
				$item = $ds[0]->GetOne(array(
					$ds[1] => $check_pass,
					$ds[2] => $check_user
				));
				if ($item != null) return $item;
			}
		}
		else return $this->pass == $check_pass;
		return false;
	}

	/**
	 * Returns HTML rendered login form.
	 *
	 * @param string $target Target script using this manager.
	 * @return string
	 */
	function Get($target)
	{
		global $errors, $_GET;
		foreach ($_GET as $key => $val) if ($key != 'ca' && $val != 'logout') Persist($key, $val);
		$f = new Form('login', array(null, 'width="100%"'));
		$f->AddHidden('ca', 'login');
		if ($this->type != CONTROL_SIMPLE) $f->AddInput('Login', 'text', 'auth_user');
		$f->AddInput('Password', 'password', 'auth_pass');
		$f->AddInput(null, 'submit', 'butSubmit', 'Login');
		return $f->Get('action="'.$target.'" method="post"');
	}

	/**
	 * Associates a dataset with this login manager, this will immediately
	 * turn it into a CONTROL_BOUND and render the associated static password
	 * unused.
	 *
	 * @param DataSet $ds DataSet to associate.
	 * @param string $passcol Column holding the password in $ds.
	 * @param string $usercol Column holding the username in $ds.
	 */
	function AddDataset($ds = null, $passcol = 'pass', $usercol = 'user')
	{
		$this->type = CONTROL_BOUND;
		$this->datasets[] = array($ds, $passcol, $usercol);
	}

	/**
	 * Sets a static password on this manager. Must be made into MD5 prior to
	 * setting.
	 *
	 * @param string $pass MD5 password.
	 */
	function SetPass($pass = null)
	{
		if (strlen($pass) != 32) die('Plaintext password! Use '.md5($pass)." instead.<br/>\n");
		$this->pass = $pass;
	}
}

/**
 * An easy way to display a set of tabs, they basically work like a menubar.
 * 
 * @todo Get rid of this thing or revise it.
 */
class Tabs
{
	/**
	* Get the tab output.
	* @param $tabs Array of tabs to be displayed.
	* @param $body Body of currently selected tab.
	* @param $advars Additional URI variables.
	* @param $right Text to be displayed on the right side of the tabs.
	*/
	function GetTabs($tabs, $body, $advars = "", $right = "")
	{
		global $s;
		if ($advars != "") $advars = "&" . $advars;
		$ret = "<table border=\"0\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">\n";
		$ret .= "<tr><td>\n";
		$ret .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">\n";
		$ret .= "<tr><td>&nbsp;</td>\n";
		for ($x = 0; $x < count($tabs); $x++)
		{
			if (GetVar("ct") == $x) $class = "tab_active";
			else $class = "tab_inactive";
			$ret .= "<td class=\"$class\" nowrap>\n";
			$ret .= "<a href=\"$s?ct=$x$advars\"><b>{$tabs[$x]}</b></a>\n";
			$ret .= "</td>\n";
		}
		$ret .= "<td class=\"light\" width=\"100%\" align=\"right\">\n";
		$ret .= $right;
		$ret .= "</td></tr></table>\n";
		$ret .= "</td></tr><tr>\n";
		$ret .= "<td class=\"tab_active\">\n";
		$ret .= "<table cellpadding=\"5\" width=\"100%\"><tr><td>$body\n";
		$ret .= "</td></tr></table>\n";
		$ret .= "</td>\n";
		$ret .= "</tr>\n";
		$ret .= "</table>\n";
		return $ret;
	}
}

/**
 * A generic page, associated with h_main.php and passed on to index.php .
 */
class DisplayObject
{
	/**
	 * Creates a new display object.
	 *
	 * @return DisplayObject
	 */
	function DisplayObject() { }

	/**
	 * Gets name of this page.
	 * @returns The name of this page for the browser's titlebar.
	 */
	function Get(&$data)
	{
		return "Class " . get_class($this) . " does not overload Get().";
	}

	/**
	 * Prepare this object for output.
	 *
	 * @param $data string
	 */
	function Prepare(&$data) { }
}

//Form Functions

/**
 * Processes validation on a form by means of regular expressions in javascript
 * and server-side php.
 *
 * @example test_present.php
 */
class Validation
{
	/**
	 * Associated form field, this uses the ID and the NAME attribute so make
	 * sure you include both on your form.
	 *
	 * @var string
	 */
	public $field;
	/**
	 * Regular expression to check validation. When applicable, this will
	 * use $regex.test($field.value) in javascript and in php it will use
	 * preg_match($regex, $values[$field])
	 *
	 * @var string
	 */
	public $regex;
	/**
	 * Error text that will be displayed if this field does not pass the
	 * test.
	 *
	 * @var string
	 */
	public $error;
	/**
	 * Array of children validators that will only be tested if this validator
	 * passes.
	 *
	 * @var array
	 */
	public $validators;

	/**
	 * Creates a new Validation object.
	 *
	 * @param $field string Name of form field, see $field
	 * @param $regex string Regular expression to test whether this is valid.
	 * @param $error string Error message to display if test fails.
	 * @return Validation
	 */
	function Validation($field, $regex, $error)
	{
		$this->field = $field;
		$this->regex = $regex;
		$this->error = $error;
		$this->validators = array();
	}

	/**
	 * Adds a child validation to this validation, child validations will only
	 * be tested if this validation succeeds.
	 *
	 * @param $value string Only if this field contains the value that is
	 * specified by $value will the child be checked.
	 * @param $child Validation Child validation object.
	 */
	function Add($value, $child)
	{
		$this->validators[$value] = $child;
	}

	/**
	 * Gets the javascript associated with this validation.
	 *
	 * @access private
	 * @return string
	 */
	function GetJS()
	{
		if (!empty($this->validators)) foreach ($this->validators as $v)
		{
			$ret .= $v->GetJS();
		}
		$ret .= "\t\tfunction {$this->field}_check(validate) \n\t\t{\n
			ret = true;
			chk_{$this->field} = document.getElementById('{$this->field}');
			spn_{$this->field} = document.getElementById('span_{$this->field}');
			if (!validate) { spn_{$this->field}.innerHTML = ''; return ret; }
			if (!/^{$this->regex}$/.test(chk_{$this->field}.value))
			{
				spn_{$this->field}.innerHTML = '{$this->error}';
				chk_{$this->field}.focus();
				ret = false;\n";
				foreach ($this->validators as $reg => $v)
				{
					$ret .= "\t\t\t\t{$v->field}_check(0);\n";
				}
			$ret .= "\t\t\treturn false;
			}
			else
			{";
				foreach ($this->validators as $reg => $v)
				{
					$ret .= "\t\t\t\t{$v->field}_check(0);\n";
				}
				$ret .= "spn_{$this->field}.innerHTML = '';\n";
				foreach ($this->validators as $reg => $v)
				{
					$ret .= "\t\t\t\tret = {$v->field}_check(/^$reg$/.test(chk_{$this->field}.value));\n";
					$ret .= "\t\t\t\tif (!ret) return false\n";
				}
			$ret .= "\t\t\t}
			return ret;
		}\n";
		return $ret;
	}

	/**
	 * Get an associated error recursively from specified field name.
	 *
	 * @access private
	 * @param string $field Name of the field that contains the error.
	 * @return string
	 */
	function GetError($field)
	{
		if ($this->field == $field) return '<span class="error" id="span_'.$this->field.'"></span>';
		foreach ($this->validators as $val)
		{
			$ret = $val->GetError($field);
			if (isset($ret)) return $ret;
		}
		return null;
	}

	/**
	 * Requests that this validator checks for valdiation. You must send
	 * true in $check if you wish it to actually test, otherwise it will
	 * just set this object up.
	 *
	 * @param bool $check Actually test the validation.
	 * @param array $ret Array of errors for anything that did not pass.
	 */
	function Validate($check, &$ret)
	{
		if ($check)
		{
		}
		else
		{
			$ret['errors'][$this->field] = '<span class="error" id="span_'.$this->field.'"></span>';
			foreach ($this->validators as $v) $v->Validate($check, $ret);
		}
	}
}

function FormValidate($name, $arr, $check)
{
	$ret = array();
	$checks = null;
	if (is_array($arr)) foreach ($arr as $key => $val)
	{
		$rec = RecurseReq($key, $val, $checks);
		if ($check && strlen(GetVar($key)) < 1) $ret['errors'][$key] = $val;
		else $ret['errors'][$key] = '<span class="error" id="span_'.$key.'"></span>';
		$ret['js'] .= $v->GetJS();
	}
	else
	{
		$arr->Validate($check, $ret);
		$ret['js'] .= $arr->GetJS($name);
	}
	$ret['js'] .= "\t\tfunction {$name}_check(validate)\n\t\t{\n";
	if (is_array($arr)) foreach ($arr as $v) $ret['js'] .= "\t\t\t{$v->field}_check(validate);\n";
	else $ret['js'] .= "\t\t\tret = {$arr->field}_check(validate);\n";
	$ret['js'] .= "\t\t\treturn ret;\n\t\t}\n";
	return $ret;
}

function RecurseReq($key, $val, &$checks)
{
	if (is_array($val))
	{
		foreach ($val as $newkey => $newval)
		{
			$checks .= "\tchk_{$key} = document.getElementById('{$key}')\n";
			$checks .= "\tif (chk_{$key}.value == '{$newkey}')\n\t{\n";
			RecurseReq($newkey, $newval, $checks);
			$checks .= "\t}\n";
		}
	}
	else
	{
		$checks .= "\tchk_{$key} = document.getElementById('{$key}')\n";
		$checks .= "\tif (chk_{$key}.value.length < 1) { alert('{$val}'); chk_{$key}.focus(); return false; }\n";
	}
}

function GetMonthSelect($name, $default, $attribs = null)
{
	$ret = "<select name=\"$name\"";
	if ($attribs != null) $ret .= " $attribs";
	$ret .= ">";
	for ($ix = 1; $ix < 13; $ix++)
	{
		$ts = gmmktime(0, 0, 0, $ix);
		if ($ix == $default) $sel = " selected=\"selected\"";
		else $sel = "";
		$ret .= "<option value=\"$ix\"$sel> " . gmdate("F", $ts) . "</option>\n";
	}
	$ret .= "</select>\n";
	return $ret;
}

function GetYearSelect($name, $year)
{
	$ret = "<select name=\"$name\">";
	$ret .= "<option value=\"" . ($year-11) . "\"> &lt;&lt; </option>\n";
	for ($ix = $year-10; $ix < $year+10; $ix++)
	{
		if ($ix == $year) $sel = " selected=\"selected\"";
		else $sel = "";
		$ret .= "<option value=\"$ix\"$sel>$ix</option>\n";
	}
	$ret .= "<option value=\"" . ($year+11) . "\"> &gt;&gt; </option>\n";
	$ret .= "</select>\n";
	return $ret;
}

function GetStateSelect($name, $state)
{
	$options = array(
		new SelOption(0, 'Alabama'),
		new SelOption(1, 'Alaska'),
		new SelOption(2, 'Arizona'),
		new SelOption(3, 'Arkansas'),
		new SelOption(4, 'California'),
		new SelOption(5, 'Colorado'),
		new SelOption(6, 'Connecticut'),
		new SelOption(7, 'Delaware'),
		new SelOption(8, 'Florida'),
		new SelOption(9, 'Georgia'),
		new SelOption(10, 'Hawaii'),
		new SelOption(11, 'Idaho'),
		new SelOption(12, 'Illinois'),
		new SelOption(13, 'Indiana'),
		new SelOption(14, 'Iowa'),
		new SelOption(15, 'Kansas'),
		new SelOption(16, 'Kentucky'),
		new SelOption(17, 'Louisiana'),
		new SelOption(18, 'Maine'),
		new SelOption(19, 'Maryland'),
		new SelOption(20, 'Massachusetts'),
		new SelOption(21, 'Michigan'),
		new SelOption(22, 'Minnesota'),
		new SelOption(23, 'Mississippi'),
		new SelOption(24, 'Missouri'),
		new SelOption(25, 'Montana'),
		new SelOption(26, 'Nebraska'),
		new SelOption(27, 'Nevada'),
		new SelOption(28, 'New Hampshire'),
		new SelOption(29, 'New Jersey'),
		new SelOption(30, 'New Mexico'),
		new SelOption(31, 'New York'),
		new SelOption(32, 'North Carolina'),
		new SelOption(33, 'North Dakota'),
		new SelOption(34, 'Ohio'),
		new SelOption(35, 'Oklahoma'),
		new SelOption(36, 'Oregon'),
		new SelOption(37, 'Pennsylvania'),
		new SelOption(38, 'Rhode Island'),
		new SelOption(39, 'South Carolina'),
		new SelOption(40, 'South Dakota'),
		new SelOption(41, 'Tennessee'),
		new SelOption(42, 'Texas'),
		new SelOption(43, 'Utah'),
		new SelOption(44, 'Vermont'),
		new SelOption(45, 'Virginia'),
		new SelOption(46, 'Washington'),
		new SelOption(47, 'West Virginia'),
		new SelOption(48, 'Wisconsin'),
		new SelOption(49, 'Wyoming')
	);

	return MakeSelect($name, $options, null, $state);
}

?>