<?php

require_once("h_template.php");
require_once("h_utility.php");

/**
 * @package Display
 */

/**
 * Quick macro to retreive a generated box.
 * @param $name string Name of the box (good for javascript calls to getElementById()).
 * @param $title string Title of the returned box.
 * @param $body string Raw text contents of the returned box.
 * @param $template string Template file to use for the returned box.
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
	public $name; //!< For unique identifier.
	public $title; //!< Title to be displayed in this box, placement depends on the theme.
	public $out; //!< Standard text to be output inside this box.
	public $template; //!< Template to use with this box.

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
	public $name; //!< Name of this table (only used as identifer in html comments).
	public $cols; //!< Column headers for this table (displayed at the top of the rows).
	public $rows; //!< Each row array that makes up the bulk of this table.
	public $atrs; //!< Array of attributes on a per-column basis.
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
	public $name; //!< Unique name of this form (used in Html comments).
	public $hiddens; //!< Array of hidden fields stored from AddHidden()
	public $attribs; //!< Form tag attributes, "name" => "value" pairs.
	public $out; //!< Actual output.
	public $Persist; //Whether to use persistant vars or not.

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
		switch ($type)
		{
			case "area":
				$strout = "<textarea name=\"".htmlspecialchars($name)."\"$attributes>";
				if ($value) $strout .= $value;
				$strout .= "</textarea>";
				break;
			case "select":
				$strout = "<select id=\"$name\" name=\"$name\"$attributes>\n";
				if (is_array($value))
				{
					$ogstarted = false;
					foreach ($value as $opt)
					{
						$selected = $opt->selected ? ' selected="selected"' : "";
						if ($opt->group)
						{
							if ($ogstarted) $strout .= "</optgroup>";
							$strout .= "<optgroup label=\"{$opt->text}\">";
							$ogstarted = true;
						}
						else $strout .= "<option value=\"{$opt->id}\"$selected>".htmlspecialchars($opt->text)."</option>\n";
					}
					if ($ogstarted) $strout .= "</optgroup>";
				}
				$strout .= "</select>\n";
				break;
			case 'selects':
				$strout = "<select id=\"$name\" name=\"{$name}[]\" multiple=\"multiple\"$attributes>\n";
				if (is_array($value))
				{
					$ogstarted = false;
					foreach ($value as $opt)
					{
						$selected = $opt[1] ? ' selected="selected"' : "";
						if ($opt[0]->group)
						{
							if ($ogstarted) $strout .= "</optgroup>";
							$strout .= "<optgroup label=\"{$opt[0]->text}\">";
							$ogstarted = true;
						}
						else $strout .= "<option value=\"{$opt[0]->id}\"$selected>".htmlspecialchars($opt[0]->text)."</option>\n";
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
			default:
				if (isset($value)) $val = ' value="'.htmlspecialchars($value).'"';
				else $val = "";
				$strout = "<input id=\"$name\" type=\"$type\" name=\"$name\"$attributes$val />";
				break;
		}
		if ($helptext != null) $this->AddRow(array("<label for=\"$name\">$text</label>", $strout, $helptext));
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

class SelOption
{
	public $id;
	public $text;
	public $group;
	public $selected;

	function SelOption($id, $text, $group = false, $selected = false)
	{
		$this->id = $id;
		$this->text = $text;
		$this->group = $group;
		$this->selected = $selected;
	}
}

function MakeSelect($name, $value = null, $attributes = null, $selvalue = null)
{
	$strout = "<select name=\"$name\" $attributes>\n";
	$selid = 0;
	foreach ($value as $option)
	{
		$selected = null;
		if (isset($selvalue))
		{
			if (is_array($selvalue) &&
			isset($selvalue[$selid]) &&
			strlen($selvalue[$selid]) > 0 &&
			$selvalue[$selid] == $option->id)
			{
				$selected = ' selected="true"';
				$selid++;
			}
			else if ($selvalue == $option->id) $selected = ' selected="true"';
		}
		else if ($option->selected) $selected = ' selected="true"';
		$strout .= "<option value=\"{$option->id}\"$selected>{$option->text}</option>\n";
		$selected = null;
	}
	$strout .= "</select>\n";
	return $strout;
}

function DataToSel($result, $col_disp, $col_id, $default = 0, $none = null)
{
	$ret = null;
	if (isset($none)) $ret[] = new SelOption(0, $none, false, $default == 0);
	foreach ($result as $res)
	{
		$ret[] = new SelOption($res[$col_id], $res[$col_disp], false, $default == $res[$col_id]);
	}
	return $ret;
}

function ArrayToSel($array)
{
	$ret = null;
	foreach ($array as $ix => $item) $ret[] = new SelOption($ix, $item);
	return $ret;
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
	if (!isset($timestamp)) $timestamp = time();
	if (is_array($timestamp))
	{
		if (isset($timestamp[5]))
			$timestamp = gmmktime($timestamp[3], $timestamp[4], $timestamp[5], $timestamp[0], $timestamp[1], $timestamp[2]);
		else
			$timestamp = gmmktime(0, 0, 0, $timestamp[0], $timestamp[1], $timestamp[2]);
	}
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
 * A complex data editor.
 */
class EditorData
{
	public $name;
	/**
	 * Dataset to interact with.
	 *
	 * @var DataSet
	 */
	public $ds;
	public $filter;
	public $sort;
	public $state;
	public $sorting;
	public $oncreate;
	public $onupdate;
	public $ondelete;
	public $onswap;
	public $idcol;

	/**
	 * Default constructor.
	 *
	 * @param $name string Name of this editor
	 * @param $idcol string Name of column that is the primary key.
	 * @param $ds DataSet Dataset for this editor to interact with.
	 * @param $fields array Array of items to allow editing.
	 * @param $display array Array of items to display in top table.
	 * @param $filter array Array to constrain editing to a given expression.
	 * @return EditorData
	 */
	function EditorData($name, $idcol = null, $ds = null, $filter = null, $sort = null)
	{
		$this->name = $name;
		$this->idcol = $idcol;
		if ($ds != null)
		{
			$this->ds = $ds;
			$this->sort = $sort;
			$this->type = CONTROL_BOUND;
		}
		else $this->type = CONTROL_SIMPLE;

		$this->state = GetVar('ca') == $this->name.'_edit' ? STATE_EDIT : STATE_CREATE;
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
		if ($action == $this->name.'_create')
		{
			$insert = array();
			foreach ($this->ds->fields as $name => $data)
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
			$this->ds->Add($insert);
		}
		else if ($action == $this->name.'_delete')
		{
			global $ci;
			if (isset($this->ondelete))
			{
				$handler = $this->ondelete;
				if (!$handler($ci)) return;
			}
			$this->ds->Remove(array($this->idcol => $ci));
		}
		else if ($action == $this->name.'_update')
		{
			global $ci;
			$update = array();
			foreach ($this->ds->fields as $name => $data)
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
				$this->ds->Update(array($this->idcol => $ci), $update);

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

	function GetSelArray($items, $sel)
	{
		$ret = array();
		foreach ($items as $i)
		{
			$ret[$i->id] = array($i, $i->id == $sel);
		}
		return $ret;
	}

	function GetSelMask($items, $sel)
	{
		$ret = array();
		foreach ($items as $i)
		{
			$ret[$i->id] = array($i, ($i->id & $sel) > 0);
		}
		return $ret;
	}

	function Get($target, $ci = null)
	{
		global $errors, $PERSISTS, $xlpath;
		$ret = '';
		if ($this->type == CONTROL_BOUND)
		{
			$sel = $this->state == STATE_EDIT ? $this->ds->GetOne(array($this->idcol => $ci)) : null;
			$items = $this->ds->Get($this->filter, $this->sort);
		}
		else { $sel = $ci; $this->state = STATE_EDIT; }

		//Table

		if (!empty($items) && !empty($this->ds->display))
		{
			$cols = array();
			foreach ($this->ds->display as $name => $field) $cols[] = "<b>{$name}</b>";

			$table = new Table($this->name.'_table', $cols);
			$last_id = -1;
			foreach ($items as $ix => $i)
			{
				$data = array();
				foreach ($this->ds->display as $name => $field)
				{
					if (is_array($field)) //Callback for field
					{
						$callback = $field[0];
						$data[] = $callback($i, $field[1]);
					}
					//Regular field
					else $data[] = stripslashes($i[$field]);
				}

				$url_defaults = array('editor' => $this->name);
				if (!empty($PERSISTS)) $url_defaults = array_merge($url_defaults, $PERSISTS);
				$url_edit = MakeURI($target, array_merge(array('ca' => $this->name.'_edit', 'ci' => $i[$this->idcol]), $url_defaults));
				$url_del = MakeURI($target, array_merge(array('ca' => $this->name.'_delete', 'ci' => $i[$this->idcol]), $url_defaults));

				if ($last_id > -1 && $this->sorting)
				{
					$url_up = MakeURI($target, array_merge(array('ca' => $this->name.'_swap', 'ci' => $i[$this->idcol], 'ct' => $last_id), $url_defaults));
					$data[] = "<a href=\"{$url_up}\"><img src=\"{$xlpath}/up.gif\" border=\"0\"/></a>";
				}
				else $data[] = null;

				if ($ix < count($items)-1 && $this->sorting)
				{
					$url_down = MakeURI($target, array_merge(array('ca' => $this->name.'_swap', 'ci' => $i[$this->idcol], 'ct' => $items[$ix+1][$this->idcol]), $url_defaults));
					$data[] = "<a href=\"$url_down\"><img src=\"{$xlpath}/down.gif\" border=\"0\" /></a>";
				}
				else $data[] = null;

				$data[] = "<a href=\"$url_edit#{$this->name}_editor\">Edit</a>";
				$data[] = "<a href=\"$url_del#{$this->name}_table\" onclick=\"return confirm('Are you sure?')\">Delete</a>";
				$table->AddRow($data);
				$last_id = $i[$this->idcol];
			}
			$ret .= "<a name=\"{$this->name}_table\">&nbsp;</a>";
			$ret .= $table->Get('class="editor"');
		}

		//Form

		if (!empty($this->ds->fields))
		{
			$frm = new Form('form'.$this->name, array('align="right"', 'width="100%"', null));
			$frm->AddHidden('editor', $this->name);
			$frm->AddHidden('ca', $this->state == STATE_EDIT ? $this->name.'_update' : $this->name.'_create');
			if ($this->state == STATE_EDIT) $frm->AddHidden('ci', $ci);
			foreach ($this->ds->fields as $text => $data)
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
						$value = $this->GetSelArray($data[2], $sel[$data[0]]);
					}
					else if ($data[1] == 'selects')
					{
						$value = $this->GetSelMask($data[2], $sel[$data[0]]);
					}
					else if ($data[1] == 'password')
					{
						$value = '';
					}
					else $value = $sel[$data[0]];

					if (!is_array($value)) $value = stripslashes($value);
					$frm->AddInput(
						$text,
						$data[1],
						$data[0],
						$value,
						'style="width: 100%"'. ((isset($data[3])) ? ' '.$data[3] : null),
						isset($errors[$data[0]]) ? $errors[$data[0]] : null);
				}
				else $frm->AddRow(array('&nbsp;'));
			}
			$frm->AddRow(array(
				null,
				'<input type="submit" value="'.($this->state == STATE_EDIT ? 'Update' : 'Create').'"/> '.
				($this->state == STATE_EDIT && $this->type == CONTROL_BOUND ? '<input type="button" value="Cancel" onclick="javascript: document.location.href=\''.$target.'?editor='.$this->name.'\'"/>' : null),
				null
			));
			$ret .= "<a name=\"{$this->name}_editor\">&nbsp;</a>";
			$ret .= $frm->Get("action=\"$target\" method=\"post\"", 'width="100%"');
		}
		return $ret;
	}
}

define('ACCESS_GUEST', 0);
define('ACCESS_ADMIN', 1);

class LoginManager
{
	public $datasets;
	public $type;
	public $pass;
	public $access;

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

	function AddDataset($ds = null, $passcol = 'pass', $usercol = 'user')
	{
		$this->type = CONTROL_BOUND;
		$this->datasets[] = array($ds, $passcol, $usercol);
	}

	function SetPass($pass = null)
	{
		if (strlen($pass) != 32) die('Plaintext password! Use '.md5($pass)." instead.<br/>\n");
		$this->pass = $pass;
	}
}

/**
 * An easy way to display a set of tabs, they basically work like a menubar.
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
 * A simple to use calander display.
 */
class Calendar
{
	public $events; //!< Array of events in this calendar.
	public $dates; //!< Array of dates in this calendar.
	public $datesbyday; //!< Array indexed by day in this calendar.

	/**
	* Adds an item to the calendar to be displayed in a given period of time
	* @param $ID int Numeric identifier to be used for ci variable.
	* @param $FromTimeStamp int Timestamp for start of event.
	* @param $ToTimeStamp int Timestamp for end of event.
	* @param $Title string Title of event
	* @param $Link string Link of event, TODO: Displayed where?
	* @param $Desc string Description of event.
	*/
	function AddItem($ID, $FromTimeStamp, $ToTimeStamp, $Title, $Link, $Desc)
	{
		$fromyear  = gmdate("Y", $FromTimeStamp);
		$frommonth = gmdate("n", $FromTimeStamp);
		$fromday   = gmdate("j", $FromTimeStamp);
		$toyear    = gmdate("Y", $ToTimeStamp);
		$tomonth   = gmdate("n", $ToTimeStamp);
		$today     = gmdate("j", $ToTimeStamp);
		$fromkey   = gmmktime(0, 0, 0, $frommonth, $fromday, $fromyear);
		$tokey     = gmmktime(0, 0, 0, $tomonth, $today, $toyear);

		$this->events[count($this->events)] = array($ID, $FromTimeStamp, $ToTimeStamp, $Title, $Link, $Desc);
		$DaysSpanned = ($tokey - $fromkey) / 86400;

		for ($ix = 0; $ix <= $DaysSpanned; $ix++)
		{
			//Store reference to timestamp.
			$key = $fromkey+(86400*$ix);
			if (!isset($this->dates[$key])) $this->dates[$key] = array();
			$this->dates[$key][] = count($this->events)-1;

			//Store reference to day.
			$keyday = gmdate("j-m-Y", $key);
			if (!isset($this->datesbyday[$keyday])) $this->datesbyday[$keyday] = array();
			$this->datesbyday[$keyday][] = count($this->events)-1;
		}
	}

	/**
	* Gets an html rendered calander display relative to the given
	* timestamp.
	* @param $timestamp int Time to display the calendar relavant to.
	* @param $admin bool [DEPRICATED] Whether or not to have administration buttons on the calendar.
	*/
	function Get($timestamp = null, $admin = false)
	{
		global $me, $cs;

		if ($timestamp != null)
		{
			$thismonth = gmdate("n", $timestamp);
			$thisyear = gmdate("Y", $timestamp);
		}
		else
		{
			$thismonth = GetVar('calmonth', gmdate("n"));
			$thisyear = GetVar('calyear', gmdate("Y"));
		}

		$ts = gmmktime(0, 0, 0, $thismonth, 1, $thisyear); //Get timestamp for first day of this month.

		$month = new CalendarMonth($ts);

		$off = gmdate("w", $ts); //Gets the offset of the first  day of this month.
		$days = gmdate("t", $ts); //Get total amount of days in this month.
$ret = <<<EOF
<form action="$me" method="post">
<div><input type="hidden" name="cs" value="$cs"></div>
<table border="0" width="100%" cellspacing="0">
	<tr class="CalendarHead">
		<td valign="top" colspan="7">
EOF;
$ret .= "			Year: " . GetYearSelect("calyear", $thisyear) . "\n";
$ret .= "			Month: " . GetMonthSelect("calmonth", $thismonth) . "\n";
$ret .= <<<EOF
			<input type="submit" value="Go">
		</td>
	</tr>
	<tr class="CalendarWeekTitle">
		<td>Sunday</td>
		<td>Monday</td>
		<td>Tuesday</td>
		<td>Wednesday</td>
		<td>Thursday</td>
		<td>Friday</td>
		<td>Saturday</td>
	</tr>
	<tr>
		<td class="CalendarPadding" colspan="{$month->Pad}">&nbsp;</td>

EOF;
		foreach ($month->Days as $day)
		{
			if ($day->StartWeek) $ret .= "\t<tr>\n";
			$ret .= "\t\t<td valign=\"top\" class=\"CalendarDay\">\n";
			$ret .= "\t\t\t<div class=\"CalendarDayTitle\">\n";
			if ($admin)
			{
				$addurl = MakeURI($me, array('ca' => 'cal_add', 'ts' => $day->TimeStamp));
				$ret .= "\t\t\t[<a href=\"{$addurl}\">+</a>]\n";
			}
			$ret .= "\t\t\t{$day->Day}</div>\n";

			if (isset($this->dates[$day->TimeStamp]))
			{
				foreach ($this->dates[$day->TimeStamp] as $eventid)
				{
					$event = $this->events[$eventid];
					$ret .= "\t\t\t<p class=\"CalendarDayBody\">\n";
					if ($admin)
					{
						$delurl = MakeURI($me, array("ca" => "cal_del", "ci" => $event[0], "ts" => $ts));
						$ret .= "\t\t\t[<a href=\"$delurl\" OnClick=\"return confirm('Are you sure?')\" title=\"Delete\"><b>X</b></a>]\n";
						$editurl = MakeURI($me, array("ca" => "cal_edit", "ci" => $event[0], "ts" => $ts));
						$ret .= "\t\t\t[<a href=\"$editurl\" title=\"Edit\"><b>E</b></a>]\n";
					}
					$ret .= "\t\t\t<a href=\"{$event[4]}\">{$event[3]}</a><br/>\n";
					$ret .= "\t\t\t{$event[5]}</p>\n";
				}
			}
			$ret .= "\t\t</td>\n";
			if ($day->LastDay) $ret .= "\t\t<td class=\"CalendarPadding\" colspan=\"".(6 - $day->WeekDay)."\">&nbsp;</td>\n";
			if ($day->EndWeek) $ret .= "\t</tr>\n";
		}
		$ret .= "</tr></table></form>\n";
		return $ret;
	}

	/**
	 * A different style of calandar display, displays
	 * every event horizontally instead of just one
	 * month.
	 * @param $admin bool Whether to include administration links.
	 */
	function GetVert($admin = false)
	{
		global $me;

		$curdate = 0;
		if (is_array($this->dates))
		{
			foreach ($this->dates as $key => $date)
			{
				if ($key < $curdate) echo "Date invalid? from ($curdate) to ($key)<br/>\n";
				$curdate = $key;
			}
		}
		$thists = GetVar("ts", time());
		//$ret = "<table class=\"CalendarYear\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
		$ret = "";
		$yearx = 0;
		$curyear = -1;
		$monthx = 0;
		$curmonth = -1;
		$dayx = 0;
		$curday = -1;
		if (!is_array($this->dates)) return null;
		foreach ($this->dates as $key => $eventids)
		{
			$year  = gmdate("Y", $key);
			$month = gmdate("n", $key);
			$day   = gmdate("j", $key);

			if ($year != $curyear || $month != $curmonth)
			{
				//Pad in the rest of the week.
				if ($curday != -1)
				{
					if ($dayx < 3) $ret .= str_repeat("\t\t\t<td>&nbsp;</td>\n", 7-$dayx);
					$dayx = 0;
				}
			}

			if ($year != $curyear)
			{
				//Terminate the last month
				if ($curmonth != -1) $ret .= "\t</tr></table><img src=\"/images/pixel_red.gif\" width=\"482\" height=\"1\" />";
				//Begin the next year.
				//if ($yearx % 2 == 0) $id = "odd";
				//else $id = "even";
				//$ret .= "\t<tr>\n\t\t<td class=\"CalendarYearTitle\" id=\"$id\">$year</td>\n\t</tr><tr>\n";
				$curyear = $year;
				$yearx++;
				$curmonth = -1;
			}

			if ($month != $curmonth)
			{
				//New month in same year
				if ($curmonth != -1)
				{
					//Terminate the last month
					$ret .= "</td></tr></table><img src=\"/images/pixel_red.gif\" width=\"482\" height=\"1\" />\n";
				}
				//New month in new year
				//else $ret .= "\t<td>";

				//Begin the next month
				if ($monthx % 2 == 0) $class = "CalendarMonthOdd";
				else $class = "CalendarMonthEven";
				$ret .= "<table border=\"0\" class=\"{$class}\" cellspacing=\"2\" cellpadding=\"3\" width=\"100%\">\n";
				$ret .= "\t<tr>\n";
				$ret .= "\t\t<td colspan=\"7\">\n";
				$ret .= "\t\t\t<b>" . gmdate("F", $key) . " $year</b>\n";
				$ret .= "\t\t</td>\n";
				$ret .= "\t</tr>\n";
				$curmonth = $month;
				$monthx++;
				$curday = -1;
			}

			if ($day != $curday)
			{
				if ($curday != -1) $ret .= "\t\t</td>\n";
				else $ret .= "\t<tr>\n";
				if ($dayx % 3 == 0 && $curday != -1) $ret .= "\t</tr><tr>\n";
				$ret .= "\t\t<td class=\"CalendarDay\" valign=\"top\">\n";
				//$ret .= "\t\t\t<p class=\"CalendarDayTitle\">$day</p>\n";
				$curday = $day;
				$dayx++;
			}

			foreach ($eventids as $eventid)
			{
				$event = $this->events[$eventid];

				//Calendar day content.
				$ret .= "\t\t\t<p class=\"CalendarDayBody\">\n";
				if ($admin)
				{
					$ret .= "\t\t\t[<a class=\"link\" href=\"$me?ca=cal_del&amp;ci={$event[0]}&amp;ts=$thists\" OnClick=\"return confirm('Are you sure?')\"><b>X</b></a>]\n";
					$ret .= "\t\t\t[<a class=\"link\" href=\"$me?ca=cal_edit&amp;ci={$event[0]}&amp;ts=$thists\"><b>E</b></a>]\n";
				}
				$ret .= "\t\t\t<a class=\"link\" href=\"{$me}?ca=viewe&amp;ci={$event[0]}\">".stripslashes($event[3])."</a><br/>\n";
				$ret .= "\t\t\t{$event[5]}\n";
			}
		}

		//Pad in the rest of the last week.
		if ($curday != -1)
		{
			$ret .= "\t\t</td>\n";
			if ($dayx < 3) $ret .= "\t\t<td colspan=\"". (3 - $dayx) . "\">&nbsp;</td>\n";
			$dayx = 0;
		}

		$ret .= "\t</tr>\n";
		$ret .= "</table>\n";

		return $ret;
	}
}

class CalendarMonth
{
	public $Year; //Year this month is on.
	public $Month; //Numeric month.
	public $Pad; //Amount of blank days at start.
	public $Days; //Array of CalendarDay objects.

	function CalendarMonth($timestamp)
	{
		$this->Year = gmdate('Y', $timestamp);
		$this->Month = gmdate('n', $timestamp);
		$this->Pad = gmdate('w', $timestamp);
		$daycount = gmdate('t', $timestamp);

		for ($ix = 1; $ix < $daycount+1; $ix++)
		{
			$this->Days[] = new CalendarDay(gmmktime(0, 0, 0, $this->Month, $ix, $this->Year));
		}
	}
}

class CalendarDay
{
	public $TimeStamp;
	public $StartWeek;
	public $EndWeek;
	public $Day;
	public $WeekDay;
	public $LastDay;

	function CalendarDay($timestamp)
	{
		$this->TimeStamp = $timestamp;
		if (gmdate('w', $timestamp) == 0) $this->StartWeek = true;
		if (gmdate('w', $timestamp) == 6) $this->EndWeek = true;
		$this->Day = gmdate('j', $timestamp);
		$this->WeekDay = gmdate('w', $timestamp);
		if (gmdate('t', $timestamp) == gmdate('j', $timestamp)) $this->LastDay = true;
	}
}

/**
 * A generic page, associated with h_main.php and passed on to index.php .
 */
class Page
{
	/**
	 * Gets name of this page.
	 * @returns The name of this page for the browser's titlebar.
	 */
	function GetName()
	{
		return "Class " . get_class($this) . " does not overload GetName().";
	}

	function Prepare() { }

	/**
	 * Returns an array of links this page supplies for the CUserBox object.
	 */
	function GetLinks()
	{
		return null;
	}
}

?>
