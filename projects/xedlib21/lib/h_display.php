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
			$d = null;
			$t = new Template($d);
			$t->set("box_name", $this->name);
			$t->set("box_title", $this->title);
			$t->set("box_body", $this->out);
			return $t->get($temp);
		}
		$ret  = "<!-- Start Box: {$this->title} -->\n";
		$ret .= "<div class=\"box_title\">{$this->title}</div>\n";
		$ret .= "<div class=\"box_body\">{$this->out}</div>\n";
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

		$atrs = null;

		if ($this->cols)
		{
			$ret .= "<tr>\n";
			$ix = 0;
			foreach ($this->cols as $id => $col)
			{
				if (isset($this->atrs)) $atrs = " ".
					$this->atrs[$ix++ % count($this->atrs)];
				else $atrs = "";
				$ret .= "<td$atrs>{$col}</td>\n";
			}
			$ret .= "</tr>\n";
		}

		if ($this->rows)
		{
			if (!isset($this->cols))
			{
				$span = 0;
				foreach ($this->rows as $row) if (count($row) > $span) $span = count($row);
				for ($ix = 0; $ix < $span; $ix++) $this->cols[] = null;
			}
			foreach ($this->rows as $ix => $row)
			{
				$ret .= "<tr>\n";
				if (count($row) < count($this->cols))
					$span = " colspan=\"".
						(count($this->cols) - count($row) + 1).
						"\"{$this->rowattribs[$ix]}";
				else $span = " {$this->rowattribs[$ix]}";
				$x = 0;
				$atrs = null;
				
				if (is_array($row))
				{
					foreach ($row as $val)
					{
						if (isset($this->atrs)) $atrs = ' '.$this->atrs[$x % count($this->atrs)];
						else if (is_array($val)) { $atrs = ' '.$val[0]; $val = $val[1]; }
						else $atrs = null;
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
		if (!is_array($cols)) Error("If you are not going to specify any
			columns, you might as well just use Table.");
		
		$this->name = $name;

		$sort = GetVar("sort");
		$order = GetVar("order", "ASC");

		global $me, $PERSISTS;
		$this->cols = array();

		$imgUp = '<img src="'.GetRelativePath(dirname(__FILE__)).
		'/images/up.png" style="vertical-align: text-bottom;"
		alt="Ascending" title="Ascending" />';
		
		$imgDown = '<img src="'.GetRelativePath(dirname(__FILE__)).
		'/images/down.png" style="vertical-align: text-bottom;"
		alt="Descending" title="Descending" align="middle"/>';

		foreach ($cols as $id => $disp)
		{
			$append = "";
			if ($sort == $id)
			{
				$append = $order == 'ASC' ? $imgUp : $imgDown;
				($order == "ASC") ? $order = "DESC" : $order = "ASC";
			}

			$uri_defaults = $PERSISTS;
			$uri_defaults = array_merge($uri_defaults, array(
				'sort' => $id,
				'order' => $order
			));

			$this->cols[] = "<a href=\"".
				URL($me, $uri_defaults).
				"\">$disp</a>$append";
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
	 * Associated validator. Make sure you set this BEFORE you use AddInput or
	 * they will not be prepared for validation. You can also specify an array
	 * as $form->Validation = array($val1, $val2, $val3);
	 * @var Validation
	 */
	public $Validation;
	/**
	 * Associated errors that are previously gotten with FormValidate().
	 *
	 * @var array
	 */
	public $Errors;

	/**
	* Instantiates this form with a unique name.
	* @param $name string Unique name only used in Html comments for identification.
	* @param $colAttribs array Array of table's column attributes.
	*/
	function Form($name, $colAttribs = null)
	{
		$this->Table($name, null, $colAttribs);
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
//	function AddInput($text, $type, $name,
//	$value = null, $attributes = null, $helptext = null)
//	{
//		if (isset($attributes)) $attributes = ' '.$attributes;
//		if (isset($this->Validation))
//		{
//			if (is_array($this->Validation))
//			{
//				foreach ($this->Validation as $val)
//				{
//					if ($val->field == $name)
//					{
//						$helptext = $this->Errors[$name].$helptext;
//						break;
//					}
//				}
//			}
//			else $helptext =
//				(isset($this->Errors[$name]) ? $this->Errors[$name] : null).
//				$helptext;
//		}
//		switch ($type)
//		{
//			case "area":
//				$strout = "<textarea name=\"".htmlspecialchars($name)."\"$attributes>";
//				if ($value) $strout .= $value;
//				$strout .= "</textarea>";
//				break;
//			case "select":
//				$strout = '<select id="'.$this->CleanID($this->name.'_'.$name).'" name="'.$name."\"{$attributes}>\n";
//				if (is_array($value))
//				{
//					$ogstarted = false;
//					foreach ($value as $id => $opt)
//					{
//						$selected = $opt->selected ? ' selected="selected"' : "";
//						if ($opt->group)
//						{
//							if ($ogstarted) $strout .= "</optgroup>";
//							$strout .= "<optgroup label=\"{$opt->text}\">";
//							$ogstarted = true;
//						}
//						else $strout .= "<option value=\"{$id}\"$selected>".htmlspecialchars($opt->text)."</option>\n";
//					}
//					if ($ogstarted) $strout .= "</optgroup>";
//				}
//				$strout .= "</select>\n";
//				break;
//			case 'selects':
//				$strout = '<select id="'.$this->CleanID($this->name.'_'.$name).'" name="'.$name."[]\" multiple=\"multiple\"$attributes>\n";
//				if (is_array($value))
//				{
//					$ogstarted = false;
//					foreach ($value as $id => $opt)
//					{
//						$selected = $opt->selected ? ' selected="selected"' : "";
//						if ($opt->group)
//						{
//							if ($ogstarted) $strout .= "</optgroup>";
//							$strout .= "<optgroup label=\"{$opt->text}\">";
//							$ogstarted = true;
//						}
//						else $strout .= "<option value=\"{$id}\"$selected>".htmlspecialchars($opt->text)."</option>\n";
//					}
//					if ($ogstarted) $strout .= "</optgroup>";
//				}
//				$strout .= "</select>\n";
//				break;
//			case 'checkboxes':
//				$strout = null;
//				if (is_array($value))
//				{
//					$vals = GetVar($name);
//					foreach ($value as $id => $opt)
//					{
//						$selected = $opt->selected ? ' checked="checked"' : null;
//						if (isset($vals[$id])) $selected = ' checked="checked"';
//						if ($opt->group) $strout .= "{$opt->text}<br/>\n";
//						else $strout .= "<input type=\"checkbox\" id=\"{$name}_{$id}\" name=\"{$name}[{$id}]\" value=\"1\"$selected /><label for=\"{$name}_{$id}\">".htmlspecialchars($opt->text)."</label><br/>\n";
//					}
//				}
//				break;
//			case "yesno":
//				$strout =  "<input type=\"radio\" name=\"$name\" value=\"{$value[0]}\" $attributes> Yes\n";
//				$strout .= "<input type=\"radio\" name=\"$name\" value=\"{$value[1]}\" checked=\"checked\" $attributes> No\n";
//				break;
//			case "date":
//				$strout = GetInputDate($name, $value, false);
//				break;
//			case "datetime":
//				$strout = GetInputDate($name, $value, true);
//				break;
//			case "image_upload":
//				$strout = "<img src=\"$value\"/><br/>\n";
//				$strout .= "Upload Image: <input type=\"file\" name=\"{$name}\"/>\n";
//				break;
//			case 'checkbox':
//				$attributes .= ' value="1"';
//				if ($value) $attributes .= ' checked="checked"';
//				$strout = "<input id=\"{$this->name}_$name\" type=\"$type\" name=\"$name\"$attributes />";
//				break;
//			case 'submit':
//				if (isset($this->Validation))
//					$attributes .= " onclick=\"return {$this->name}_check(1);\"";
//			default:
//				if (isset($value)) $val = ' value="'.htmlspecialchars($value).'"';
//				else if (isset($this->Errors)) $val = ' value="'.htmlspecialchars(GetVar($name)).'"';
//				else $val = null;
//				$strout = "<input id=\"".$this->CleanID("{$this->name}_$name").
//					"\" type=\"$type\" name=\"$name\"$attributes$val />";
//				break;
//		}
//		if ($helptext != null) $this->AddRow(array('<label for="'.$this->name.'_'.htmlspecialchars($name).'">'.$text.'</label>', $strout, $helptext));
//		else $this->AddRow(array(strlen($text) > 0 ? "<label for=\"{$this->name}_$name\">$text</label>" : null, $strout, null));
//	}


	/**
	 * Adds an input item to this form.
	 * 
	 */
	function AddInput()
	{
		if (func_num_args() < 1) Error("Not enough arguments.");
		$args = func_get_args();
		foreach ($args as $item)
		{
			$helptext = null;

			if ($item->type == 'submit' && isset($this->Validation))
			{
				$item->atrs .= " onclick=\"return {$this->name}_check(1);\"";
			}

			$out = isset($item->text) ? '<label for="'.CleanID($this->name.'_'.
				$item->name).'">'.$item->text.
				'</label><br/>' : '';

			$helptext = $item->help;
			if (isset($this->Validation))
			{
				//if (is_array($this->Validation))
				//{
					//foreach ($this->Validation as $val)
					//{
						//if ($val->field == $item->name)
						//{
							//$helptext .= $this->Errors[$item->name];
							//break;
						//}
					//}
				//}
				/*else*/ $helptext .=
					(isset($this->Errors[$item->name]) ?
						$this->Errors[$item->name] : null);
			}
			
			$row[] = $out.$item->Get($this->name)."<br/>$helptext";
		}
		$this->AddRow($row, ' valign="top"');
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
		$ret .= "<form class=\"form\"$formAttribs";
		if (isset($this->attribs))
		{
			foreach ($this->attribs as $atr => $val) $ret .= " $atr=\"$val\"";
		}
		$ret .= ">\n";
		if ($this->Persist && !empty($PERSISTS))
		{
			foreach ($PERSISTS as $name => $value) $this->AddHidden($name, $value, null, true);
		}
		if (!empty($this->hiddens))
		{
			foreach ($this->hiddens as $hidden)
			{
				$fname = $hidden[3] ? $hidden[0] : $this->name.'_'.$hidden[0];
				$ret .= "<input type=\"hidden\" id=\"{$this->name}_{$fname}\" name=\"{$hidden[0]}\" value=\"{$hidden[1]}\"";
				if (isset($hidden[2])) $ret .= ' '.$hidden[2];
				$ret .= " />\n";
			}
		}
		$ret .= parent::Get($tblAttribs);
		$ret .= "</form>\n";
		$ret .= "<!-- End Form: {$this->name} -->\n";
		return $ret;
	}

	/**
	 * Returns the properly scripted up submit button, should be used in place
	 * of AddInput(null, 'submit').
	 *
	 * @param string $name Name of this button.
	 * @param string $text Text displayed on this button.
	 * @return string
	 */
	function GetSubmitButton($name, $text)
	{
		$ret = '<input type="submit" name="'.$name.'" value="'.$text.'"';
		if (isset($this->Validation))
			$ret .= " onclick=\"return {$this->name}_check(1);\"";
		return $ret.' />';
	}
}

class FormInput
{
	/**
	 * Text of this input object, displayed above the actual field.
	 *
	 * @var string
	 */
	public $text;
	/**
	 * Name of this input object, used in conjunction with the associated form.
	 *
	 * @var string
	 */
	public $name;
	/**
	 * Type of this input object. (text, select, string, checks, etc)
	 *
	 * @var string
	 */
	public $type;
	/**
	 * Manual HTML attributes for this input object.
	 *
	 * @var string
	 */
	public $atrs;
	/**
	 * Help text is displayed below the form field. Usually in case of error or
	 * to provide better information on the input wanted.
	 *
	 * @var string
	 */
	public $help;
	/**
	 * Default value of this input object.
	 *
	 * @var string
	 */
	public $valu;

	/**
	 * Creates a new input object with many properties pre-set.
	 *
	 * @param string $text
	 * @param string $type
	 * @param string $name
	 * @param string $valu
	 * @param string $atrs
	 * @param string $help
	 * @return FormInput
	 */
	function FormInput($text, $type, $name, $valu = null, $atrs = null,
		$help = null)
	{
		$this->text = $text;
		$this->type = $type;
		$this->name = $name;
		$this->valu = $valu;
		$this->atrs = $atrs;
		$this->help = $help;
	}

	/**
	 * Returns this input object rendered in html.
	 *
	 * @param string $parent name of the parent.
	 * @return string
	 */
	function Get($parent = null)
	{
		if ($this->type == 'yesno')
		{
			return GetInputYesNo($this->name, $this->valu);
		}
		if ($this->type == 'date')
		{
			return GetInputdate($this->name, $this->valu);
		}
		if ($this->type == 'select')
		{
			$ret = "<select class=\"input_select\" name=\"{$this->name}\"
				id=\"".CleanID($parent.'_'.$this->name)."\">";
			if (!empty($this->valu))
				foreach ($this->valu as $id => $opt)
				{
					$selected = $opt->selected ? ' selected="selected"' : null;
					$ret .= "<option
						value=\"{$id}\"$selected>".
						htmlspecialchars($opt->text).
						"</option>";
				}
			return $ret.'</select>';
		}
		if ($this->type == 'checks')
		{
			$ret = null;
			if (!empty($this->valu))
				foreach ($this->valu as $id => $val)
				{
					$selected = $val->selected ? ' selected="selected"' : null;
					$ret .= "<label><input
						type=\"checkbox\"
						name=\"{$this->name}[{$id}]\"
						id=\"".CleanID($this->name.'_'.$id)."\"{$this->atrs}/>
						{$val->text}</label><br />";
				}
			return $ret;
		}
		if ($this->type == 'selects')
		{
			$ret = '<select
				id="'.CleanID($parent.'_'.$this->name).'"
				name="'.$this->name."[]\"
				multiple=\"multiple\"$this->atrs>\n";
			if (!empty($this->valu))
			{
				$ogstarted = false;
				foreach ($this->valu as $id => $opt)
				{
					$selected = $opt->selected ? ' selected="selected"' : null;
					if ($opt->group)
					{
						if ($ogstarted) $ret .= "</optgroup>";
						$ret .= "<optgroup label=\"{$opt->text}\">";
						$ogstarted = true;
					}
					else $ret .=
						"<option value=\"{$id}\"{$selected}>".
						htmlspecialchars($opt->text)."</option>\n";
				}
				if ($ogstarted) $ret .= "</optgroup>";
			}
			$ret .= "</select>\n";
			return $ret;
		}
		if ($this->type == 'area')
			return "<textarea
				class=\"input_area\"
				name=\"{$this->name}\"
				id=\"".CleanID($parent.'_'.$this->name)."\"
				{$this->atrs}>{$this->valu}</textarea>";

		return "<input type=\"{$this->type}\"
			class=\"".($this->type == 'button' || $this->type == 'submit' ? 'input_button' : 'input_generic')."\"
			name=\"{$this->name}\"
			id=\"".CleanID($parent.'_'.$this->name)."\"".
			(isset($this->valu) ? " value=\"{$this->valu}\"" : null).
			"{$this->atrs}/>";
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
	if (!empty($result)) foreach ($result as $res)
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
		$timestamp = MyDateTimestamp($timestamp, $include_time);
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
	function TreeNode($data = null)
	{
		$this->data = $data;
		$this->children = array();
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
				if (!isset($ds[0]))
					Error("<br />What: Dataset is not set.
					<br />Who: LoginManager::Prepare()
					<br />Why: You may have set an incorrect dataset in the
					creation of this LoginManager.");
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
		if ($this->type != CONTROL_SIMPLE)
			$f->AddInput(new FormInput('Login', 'text', 'auth_user'));
		$f->AddInput(new FormInput('Password', 'password', 'auth_pass'));
		$f->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Login'));
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
 * @see Form
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
	public $check;
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
	function Validation($field, $check, $error)
	{
		$this->field = $field;
		$this->check = $check;
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
	function GetJS($id = null)
	{
		$ret = null;
		if (!empty($this->validators))
		foreach ($this->validators as $v)
			$ret .= $v->GetJS($id);
		$ret .= "\t\tfunction {$id}_{$this->field}_check(validate) \n\t\t{
			ret = true;
			chk_{$this->field} = document.getElementById('{$id}_{$this->field}');
			spn_{$id}_{$this->field} = document.getElementById('span_{$id}_{$this->field}');
			if (!validate) { spn_{$id}_{$this->field}.innerHTML = ''; return ret; }";
		if (is_array($this->check))
		{
			$ret .= "\n\t\t\tix = 0;";
			foreach ($this->check[0] as $ix => $opt)
			{
				$ret .= "\n\t\t\tif (document.getElementById('{$this->field}_{$ix}').checked == true) ix++;";
			}
			$ret .= "\n\t\t\tif (ix < {$this->check[1]})
			{
				spn_{$id}_{$this->field}.innerHTML = '{$this->error}';
				return false;
			}";
		}
		else
		{
			$ret .= "\n\t\t\tif (!/^{$this->check}$/.test(chk_{$this->field}.value))
			{
				spn_{$id}_{$this->field}.innerHTML = '{$this->error}';
				chk_{$this->field}.focus();
				ret = false;\n";
				foreach ($this->validators as $reg => $v)
					$ret .= "\t\t\t\t{$id}_{$v->field}_check(0);\n";
			$ret .= "\t\t\t\treturn false;
			}";
		}
		$ret .= "\n\t\t\telse
			{\n";
				foreach ($this->validators as $reg => $v)
				{
					$ret .= "\t\t\t\t{$id}_{$v->field}_check(0);\n";
				}
				$ret .= "\t\t\t\tspn_{$id}_{$this->field}.innerHTML = '';\n";
				foreach ($this->validators as $reg => $v)
				{
					$ret .= "\t\t\t\tret = {$id}_{$v->field}_check(/^$reg$/.test(chk_{$this->field}.value));\n";
					$ret .= "\t\t\t\tif (!ret) return false\n";
				}
			$ret .= "\t\t\t}
			return ret;
		}\n";
		return $ret;
	}

	/**
	 * Requests that this validator checks for valdiation. You must send
	 * true in $check if you wish it to actually test, otherwise it will
	 * just set this object up.
	 *
	 * @param bool $check Actually test the validation.
	 * @param array $ret Array of errors for anything that did not pass.
	 */
	function Validate($form, $check, &$ret)
	{
		$passed = true;
		if ($check)
		{
			if (is_array($this->check))
			{
				$vals = GetVar($this->field);
				if (count($vals) < $this->check[1])
				{
					$ret['errors'][$this->field] =
						'<span class="error"
						id="span_'.$form.'_'.$this->field.'">'.
						$this->error.'</span>';
					$passed = false;
				}
			}
			else
			{
				if (!preg_match("/$this->check/", GetVar($this->field)))
				{
					$ret['errors'][$this->field] =
						'<span class="error"
						id="span_'.$form.'_'.$this->field.'">'.
						$this->error.'</span>';
					$passed = false;
				}
			}
		}
		else
		{
			$ret['errors'][$this->field] =
				'<span class="error"
				id="span_'.$form.'_'.$this->field.'"></span>';
			foreach ($this->validators as $v) $v->Validate($form, $check, $ret);
		}
		return $passed;
	}
}

function FormValidate($name, $arr, &$ret, $check)
{
	$ret['js'] = null;
	$checks = null;
	$passed = true;
	if (is_array($arr))
	foreach ($arr as $key => $val)
	{
		$rec = RecurseReq($key, $val, $checks);
		if (!$val->Validate($name, $check, $ret))
			$passed = false;
		else
			$ret['errors'][$val->field] = '<span class="error"
			id="span_'.$name.'_'.$val->field.'"></span>';
		$ret['js'] .= $val->GetJS($name);
	}
	else
	{
		if (!$arr->Validate($name, $check, $ret)) $passed = false;
		$ret['js'] .= $arr->GetJS($name);
	}
	$ret['js'] .= "\t\tfunction {$name}_check(validate)\n\t\t{";
	if (is_array($arr)) foreach ($arr as $v)
	{
		$ret['js'] .= "\n\t\t\tret = {$name}_{$v->field}_check(validate);";
		$ret['js'] .= "\n\t\t\tif (!ret) return ret;";
	}
	else $ret['js'] .= "\t\t\tret = {$name}_{$arr->field}_check(validate);\n";
	$ret['js'] .= "\n\t\t\treturn ret;\n\t\t}\n";

	return $passed;
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
		new SelOption('Alabama'),
		new SelOption('Alaska'),
		new SelOption('Arizona'),
		new SelOption('Arkansas'),
		new SelOption('California'),
		new SelOption('Colorado'),
		new SelOption('Connecticut'),
		new SelOption('Delaware'),
		new SelOption('Florida'),
		new SelOption('Georgia'),
		new SelOption('Hawaii'),
		new SelOption('Idaho'),
		new SelOption('Illinois'),
		new SelOption('Indiana'),
		new SelOption('Iowa'),
		new SelOption('Kansas'),
		new SelOption('Kentucky'),
		new SelOption('Louisiana'),
		new SelOption('Maine'),
		new SelOption('Maryland'),
		new SelOption('Massachusetts'),
		new SelOption('Michigan'),
		new SelOption('Minnesota'),
		new SelOption('Mississippi'),
		new SelOption('Missouri'),
		new SelOption('Montana'),
		new SelOption('Nebraska'),
		new SelOption('Nevada'),
		new SelOption('New Hampshire'),
		new SelOption('New Jersey'),
		new SelOption('New Mexico'),
		new SelOption('New York'),
		new SelOption('North Carolina'),
		new SelOption('North Dakota'),
		new SelOption('Ohio'),
		new SelOption('Oklahoma'),
		new SelOption('Oregon'),
		new SelOption('Pennsylvania'),
		new SelOption('Rhode Island'),
		new SelOption('South Carolina'),
		new SelOption('South Dakota'),
		new SelOption('Tennessee'),
		new SelOption('Texas'),
		new SelOption('Utah'),
		new SelOption('Vermont'),
		new SelOption('Virginia'),
		new SelOption('Washington'),
		new SelOption('West Virginia'),
		new SelOption('Wisconsin'),
		new SelOption('Wyoming')
	);

	return MakeSelect($name, $options, null, $state);
}

?>
