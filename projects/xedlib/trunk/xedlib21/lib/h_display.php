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
 * @example test_display.php
 * @return string Rendered box.
 */
function GetBox($name, $title, $body, $template = null)
{
	$box = new Box();
	$box->name = $name;
	$box->title = $title;
	$box->Out($body);
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
	 * @var string
	 */
	public $name;
	/**
	 * Title to be displayed in this box, placement depends on the theme.
	 *
	 * @var string
	 */
	public $title;
	/**
	 * Standard text to be output inside this box.
	 *
	 * @var string
	 */
	public $out;
	/**
	 * Template filename to use with this box.
	 *
	 * @var string
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
		$this->template = ifset(@$GLOBALS['__xedlib_box_template'],
			'template_box.html');
	}

	function Out($t) { $this->out .= $t; }

	/**
	* Returns the rendered html output of this Box.
	* @param string $template Filename of template to use for display.
	* @return string Rendered box
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
			return $t->ParseFile($temp);
		}
		$ret  = '<!-- Start Box: '.$this->title.' -->';
		$ret .= '<div ';
		if (!empty($this->name)) $ret .= " id=\"{$this->name}\"";
		$class = (!empty($this->prefix)?$this->prefix.'_box':'box');
		$ret .= " class=\"{$class}\">";
		$ret .= '<div class="box_title">'.$this->title.'</div>';
		$ret .= '<div class="box_body">'.$this->out.'</div>';
		$ret .= '</div>';
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
	 * @param string $name Unique name only used in Html comments for identification.
	 * @param array $cols Default columns headers to display ( eg. array("Column1", "Column2") ).
	 * @param array $attributes An array of attributes for each column ( eg. array('width="100%"', 'valign="top"') ).
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
	 * @param array $row A string array of columns.
	 * @param array $attribs Attributes to be applied to each column.
	 */
	function AddRow($row, $attribs = null)
	{
		$this->rows[] = $row;
		$this->rowattribs[] = $attribs;
	}

	/**
	 * Returns the complete html rendered table for output purposes.
	 * @param string $attributes A set of html attributes to apply to the entire table. (eg. 'class="mytableclass"')
	 * @return string The complete html rendered table.
	 */
	function Get($attributes = null)
	{
		$ret = "<!-- Start Table: {$this->name} -->\n";
		$ret .= '<table';
		$ret .= GetAttribs($attributes);
		$ret .= ">\n";

		$atrs = null;

		if ($this->cols)
		{
			$ret .= "<thead><tr>\n";
			$ix = 0;
			foreach ($this->cols as $col)
			{
				if (isset($this->atrs)) $atrs = " ".
					$this->atrs[$ix++ % count($this->atrs)];
				else $atrs = "";
				$ret .= "<th $atrs>{$col}</th>\n";
			}
			$ret .= "</tr></thead>\n";
		}

		if ($this->rows)
		{
			$ret .= "<tbody>\n";
			if (!isset($this->cols))
			{
				$span = 0;
				foreach ($this->rows as $row) if (count($row) > $span) $span = count($row);
				for ($ix = 0; $ix < $span; $ix++) $this->cols[] = null;
			}
			foreach ($this->rows as $ix => $row)
			{
				$ret .= '<tr';
				if (!empty($this->rowattribs))
					$ret .= GetAttribs($this->rowattribs[$ix]);
				$ret .= ">\n";
				if (count($row) < count($this->cols))
					$span = " colspan=\"".
						(count($this->cols) - count($row) + 1)
						/*."\"{$this->rowattribs[$ix]}"*/;
				else $span = '';
				$x = 0;
				$atrs = null;

				if (is_array($row))
				{
					foreach ($row as $val)
					{
						if (is_array($val))
						{
							$atrs = GetAttribs($val[1]);
							$val = $val[0];
						}
						else if (isset($this->atrs))
							$atrs = ' '.$this->atrs[$x % count($this->atrs)];
						else $atrs = null;
						$ret .= "<td$span$atrs>{$val}</td>\n";
						$x++;
					}
				}
				else $ret .= "<td{$span}{$atrs}>{$row}</td>\n";
				$ret .= "</tr>\n";
			}
			$ret .= "</tbody>\n";
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
	* @param string $name Unique name only used in Html comments for identification.
	* @param array $cols Default columns headers to display ( eg. array("Column1", "Column2") ).
	* @param array $attributes An array of attributes for each column ( eg. array('width="100%"', 'valign="top"') ).
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

			$uri_defaults = !empty($PERSISTS) ? $PERSISTS : array();
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
class Form
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
	 * @var string
	 */
	public $FormStart = '<table>';

	/**
	 * @var string
	 */
	public $RowStart = array('<tr class="even">', '<tr class="odd">');

	public $FirstStart = '<td align="right">';

	public $FirstEnd = '</td>';

	public $StringStart = '<td colspan="2">';

	/**
	 * @var string
	 */
	public $CellStart = '<td>';

	/**
	 * @var string
	 */
	public $CellEnd = '</td>';

	/**
	 * @var string
	 */
	public $RowEnd = '</tr>';

	/**
	 * @var string
	 */
	public $FormEnd = '</table>';

	/**
	 * @var array
	 */
	public $words = array(
		'complete', 'finish', 'end', 'information'
	);

	public $rowx = 0;

	private $multipart = false;

	/**
	* Instantiates this form with a unique name.
	* @param string $name Unique name only used in Html comments for identification.
	* @param array $colAttribs Array of table's column attributes.
	* @param bool $persist Whether or not to persist the values in this form.
	*/
	function Form($name, $persist = true)
	{
		$this->name = $name;
		$this->attribs = array();
		$this->Persist = $persist;
		$this->Template = file_get_contents(dirname(__FILE__).'/temps/form.xml');
	}

	/**
	* Adds a hidden field to this form.
	* @param string $name The name attribute of the html field.
	* @param mixed $value The value attribute of the html field.
	* @param string $attribs Attributes to append on this field.
	* @param bool $general Whether this is a general name. It will not
	* have the form name prefixed on it.
	*/
	function AddHidden($name, $value, $attribs = null, $general = false)
	{
		$this->hiddens[] = array($name, $value, $attribs, $general);
	}

	/**
	 * Adds an input item to this form. You can use a single FormInput object,
	 * a string, an array or a series of arguments of strings and FormInputs and
	 * this will try to sort it all out vertically or horizontally.
	 */
	function AddInput()
	{
		if (func_num_args() < 1) Error("Not enough arguments.");
		$args = func_get_args();

		if (!empty($args))
		{
			$this->out .= $this->RowStart[$this->rowx++%2];
			foreach ($args as $ix => $item)
				$this->out .= $this->IterateInput($ix == 0, $item);
			$this->out .= $this->RowEnd;
		}
	}

	/**
	 * @param mixed $input FormInput, multiple FormInputs, arrays, whatever.
	 * @return string Rendered input field.
	 */
	function IterateInput($start, $input)
	{
		if (is_array($input))
		{
			if (empty($input)) return;
			$out = null;
			foreach ($input as $item)
			{
				$out .= $this->IterateInput($start, $item);
				$start = false;
			}
			return $out;
		}

		$helptext = null;

		if (is_string($input))
		{
			$this->inputs[] = $input;
			return $this->StringStart.$input.
				($start ? $this->FirstEnd : $this->CellEnd);
		}

		if (!is_object($input)) Error("Form input is not an object.");

		$this->inputs[] = $input;

		if ($input->type == 'submit' && isset($this->Validation))
		{
			$input->atrs['ONCLICK'] = "return {$this->name}_check(1);";
		}
		if ($input->type == 'file') $this->multipart = true;

		$right = false;
		if ($input->type == 'checkbox') $right = true;
		if ($input->type == 'spamblock')
		{
			//This form has been submitted.
			$b = GetVar('block_'.$input->name);
			if (isset($b) && GetVar($input->name) != $this->words[$b])
				$this->Errors[$input->name] = ' '.GetImg('error.png', 'Error',
					'style="vertical-align: text-bottom"').
					"<span class=\"error\"> Invalid phrase.</span>";
			$rand = rand(0, count($this->words)-1);
			$input->valu = $this->words[$rand];
			$this->AddHidden('block_'.$input->name, $rand);
		}

		$out = !empty($input->text)?$input->text:null;

		$helptext = $input->help;
		if (isset($this->Errors[$input->name]))
			$helptext .= $this->Errors[$input->name];

		return ($start ? $this->FirstStart : $this->CellStart).
			($input->labl ? '<label for="'.CleanID($this->name, $input)
			.'">' : null).
			($right ? null : $out).
			($input->labl ? '</label>' : null).$this->CellEnd.
			$this->CellStart.$input->Get($this->name, $this->Persist).
			($right ? $out : null).$helptext.
			($start ? $this->FirstEnd : $this->CellEnd);
	}

	function TagForm($t, $g, $a)
	{
		global $PERSISTS;
		$atrs = GetAttribs($a);
		$ret = "<form {$this->formAttribs}{$atrs}";
		if ($this->multipart) $ret .= ' enctype="multipart/form-data"';
		$ret .= ">\n";

		if ($this->Persist && !empty($PERSISTS))
		foreach ($PERSISTS as $name => $value)
			$this->AddHidden($name, $value, null, true);

		if (!empty($this->hiddens))
		foreach ($this->hiddens as $hidden)
		{
			$fname = $hidden[3] ? $hidden[0] : $this->name.'_'.$hidden[0];
			$ret .= "<input type=\"hidden\" id=\"".CleanID($fname)."\"
				name=\"{$hidden[0]}\" value=\"{$hidden[1]}\"";
			if (isset($hidden[2])) $ret .= ' '.$hidden[2];
			$ret .= " />\n";
		}

		return $ret.$g.'</form>';
	}

	function TagField($t, $g)
	{
		$ret = '';
		$vp = new VarParser();
		$ix = 0;
		if (!empty($this->inputs))
		foreach ($this->inputs as $in)
		{
			$d['even_odd'] = ($ix++ % 2) ? 'even' : 'odd';
			$d['text'] = !empty($in->text) ? $in->text : '';
			if (strtolower(get_class($in)) == 'forminput')
			{
				$d['field'] = $in->Get($this->name);
				$d['help'] = $in->help;
			}
			else
			{
				$d['field'] = $in;
				$d['help'] = '';
			}

			$ret .= $vp->ParseVars($g, $d);
		}
		return $ret;
	}

	/**
	* Returns the complete html rendered form for output purposes.
	* @param string $formAttribs Additional form attributes (method, class, action, etc)
	* @param string $tblAttribs To be passed to Table::GetTable()
	* @return string The complete html rendered form.
	*/
	function Get($formAttribs = null)
	{
		require_once('h_template.php');
		$this->formAttribs = $formAttribs;
		$t = new Template();
		$t->Behavior->Bleed = false;
		$t->Set('form_name', $this->name);
		$t->ReWrite('form', array($this, 'TagForm'));
		$t->ReWrite('field', array($this, 'TagField'));
		return $t->GetString($this->Template);
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
	 * Whether or not to attach a label to this field.
	 * @var bool
	 */
	public $labl;

	/**
	 * Whether or not this input ends it's own label.
	 * @var bool
	 */
	public $EndLabel;

	public $append;

	/**
	 * Creates a new input object with many properties pre-set.
	 *
	 * @param string $text
	 * @param string $type
	 * @param string $name
	 * @param string $valu
	 * @param string $atrs
	 * @param string $help
	 */
	function FormInput($text, $type = 'text', $name = null, $valu = null, $atrs = null,
		$help = '')
	{
		$this->text = $text;
		$this->type = $type;
		$this->name = $name;
		$this->valu = $valu;
		if (is_array($atrs)) $this->atrs = $atrs;
		else $this->atrs = ParseAtrs($atrs);
		$this->help = $help;
		if ($type == 'date' || $type == 'time' || $type == 'datetime')
			$this->EndLabel = true;
		else $this->EndLabel = false;
	}

	/**
	 * This is eventually going to need to be an array instead of a substr for
	 * masked fields that do not specify length, we wouldn't know the length
	 * for the substr. Works fine for limited lengths for now though.
	 */
	function mask_callback($m)
	{
		$ret = '<input type="text" maxlength="'.$m[1].'" size="'.$m[1].
			'" name="'.$this->name.'[]"';
		if (!empty($this->valu))
			$ret .= ' value="'.substr($this->valu, $this->mask_walk, $m[1]).'"';
		$this->mask_walk += $m[1];
		return $ret.' />';
	}

	/**
	 * Returns this input object rendered in html.
	 *
	 * @param string $parent name of the parent.
	 * @param bool $persist Whether or not to persist the value in this field.
	 * @return string
	 */
	function Get($parent = null, $persist = true)
	{
		$id = !empty($parent) ? $parent.'_' : null;
		$id .= !empty($this->atrs['ID']) ? $this->atrs['ID'] : $this->name;
		$id = CleanID($id);

		if ($this->type == 'custom')
			return call_user_func($this->valu, $this);
		if ($this->type == 'label') return $this->valu;
		if ($this->type == 'mask')
		{
			$this->mask_walk = 0;
			return preg_replace_callback('/t([0-9]+)/',
				array($this, 'mask_callback'), @$this->mask);
		}
		if ($this->type == 'spamblock')
		{
			$this->atrs['TYPE'] = 'text';
			$this->atrs['CLASS'] = 'input_generic';
			$this->atrs['NAME'] = $this->name;
			$this->atrs['VALUE'] = $this->GetValue($persist);
			$this->atrs['ID'] = $id;
			$this->labl = false;
			$atrs = GetAttribs($this->atrs);
			return '<label>To verify your request, please type the word <u>'.
				$this->valu.'</u>:<br/>'.
				"<input{$atrs} /></label>";
		}
		if ($this->type == 'yesno')
		{
			return GetInputYesNo($parent, $this->name,
				!isset($this->valu) ? GetVar($this->name) : $this->valu);
		}
		if ($this->type == 'select' || $this->type == 'selects')
		{
			$this->atrs['NAME'] = $parent.'_'.$this->name;
			if (!isset($this->atrs['ID']))
				$this->atrs['ID'] = CleanID($parent.'_'.$this->name);
			if ($this->type == 'selects')
			{
				$this->atrs['MULTIPLE'] = 'multiple';
				$this->atrs['NAME'] .= '[]';
			}
			if (!isset($this->attrs['CLASS'])) $this->atrs['CLASS'] = 'input_select';

			$ret = "<select".GetAttribs($this->atrs).'>';
			if (!empty($this->valu))
			{
				$newsels = $this->GetValue($persist);
				$ogstarted = false;
				foreach ($newsels as $id => $opt)
				{
					$selected = $opt->selected ? ' selected="selected"' : null;
					if ($opt->group)
					{
						if ($ogstarted) $ret .= "</optgroup>";
						$ret .= "<optgroup label=\"{$opt->text}\">";
						$ogstarted = true;
					}
					else $ret .= "<option
						value=\"{$id}\"$selected>".
						htmlspecialchars($opt->text).
						"</option>";
				}
				if ($ogstarted) $ret .= "</optgroup>";
			}
			return $ret.'</select>';
		}
		if ($this->type == 'checks')
		{
			$this->labl = false;

			$ret = null;
			$vp = new VarParser();
			if (!empty($this->valu))
			{
				$atrs = GetAttribs($this->atrs);
				$ret .= "<div {$atrs}>";
				$newsels = $this->GetValue($persist);
				foreach ($newsels as $id => $val)
				{
					$selected = $val->selected ? 'checked="checked"' : null;
					if ($val->group)
						$ret .= "<b><i>{$val->text}</i></b><br/>\n";
					else
						$ret .= "<label><input
							type=\"checkbox\"
							name=\"{$this->name}[]\" value=\"{$id}\"
							id=\"".CleanID($this->name.'_'.$id)."\"{$selected} />
							{$val->text}</label>";
					if (!empty($this->append))
					{
						$dat = $this;
						$dat->valu = $id;
						$ret .= $vp->ParseVars($this->append, $dat);
					}
					$ret .= "<br />\n";
				}
				$ret .= '</div>';
			}
			$this->EndLabel = true;
			return $ret;
		}
		if ($this->type == 'radios')
		{
			$this->labl = false;

			$ret = null;
			if (!empty($this->valu))
			{
				$newsels = $this->GetValue($persist);
				foreach ($newsels as $id => $val)
				{
					$selected = $val->selected ? 'checked="checked"' : null;
					if ($val->group)
						$ret .= "<b><i>{$val->text}</i></b>\n";
					else
						$ret .= "<label><input
							type=\"radio\"
							name=\"{$this->name}\"
							value=\"{$id}\"
							id=\"".CleanID($this->name.'_'.$id)."\"{$selected}{$this->atrs}/>
							{$val->text}</label>";
					if (empty($this->Horizontal)) $ret .= '<br/>';
				}
			}
			$this->EndLabel = true;
			return $ret;
		}
		if ($this->type == 'date')
		{
			$this->labl = false;
			return GetInputDate($parent.'_'.$this->name, $this->valu);
		}
		if ($this->type == 'time')
		{
			$this->labl = false;
			return GetInputTime($parent.'_'.$this->name, $this->valu);
		}
		if ($this->type == 'datetime')
		{
			$this->labl = false;
			return GetInputDate($parent.'_'.$this->name, $this->valu, true);
		}
		if ($this->type == 'area')
		{
			if (empty($this->atrs['ROWS'])) $this->atrs['ROWS'] = 3;
			if (empty($this->atrs['COLS'])) $this->atrs['COLS'] = 25;
			if (empty($this->atrs['CLASS'])) $this->atrs['CLASS'] = 'input_area';
			if (empty($this->atrs['NAME'])) $this->atrs['NAME'] = $parent.'_'.$this->name;
			if (empty($this->atrs['ID'])) $this->atrs['ID'] = $id;
			$atrs = GetAttribs($this->atrs);
			return "<textarea$atrs>".$this->GetValue($persist).'</textarea>';
		}
		if ($this->type == 'checkbox')
		{
			return "<input type=\"checkbox\"
				name=\"{$parent}_{$this->name}\"
				id=\"{$id}\"
				value=\"1\" ".$this->GetValue($persist).
				$this->atrs." />";
		}
		if ($this->type == 'state')
		{
			$this->atrs['NAME'] = @$parent.'_'.$this->name;
			return GetInputState($this->atrs, @$this->valu);
		}
		if ($this->type == 'fullstate')
		{
			$this->atrs['NAME'] = @$parent.'_'.$this->name;
			return GetInputState($this->atrs, @$this->valu, false);
		}
		if ($this->type == 'shortstate')
		{
			$this->atrs['NAME'] = @$parent.'_'.$this->name;
			return GetInputSState($this->atrs, @$this->valu);
		}

		$val = $this->GetValue($persist && $this->type != 'radio');

		$atrs = GetAttribs($this->atrs);
		return "<input type=\"{$this->type}\"
			name=\"{$parent}_{$this->name}\"".
			" value=\"{$val}\" {$atrs}/>";
	}

	/**
	 * @param bool $persist Whether or not to persist the data in this field.
	 * @return mixed Value of this field.
	 */
	function GetValue($persist = true)
	{
		switch ($this->type)
		{
			//Definate Failures...
			case 'password':
			case 'file':
			case 'spamblock':
				return null;
			//Single Selectables...
			case 'select':
			case 'radios':
				$newsels = array_clone($this->valu);
				if ($persist)
				{
					$sel = GetVar($this->name);
					if ($sel && isset($newsels[$sel]))
						$newsels[$sel]->selected = true;
				}
				return $newsels;
			//Multi Selectables...
			case 'selects':
			case 'checks':
				$newsels = array_clone($this->valu);
				if ($persist)
				{
					$svalus = GetVar($this->name);
					if (!empty($svalus))
					foreach ($svalus as $val) $newsels[$val]->selected = true;
				}
				return $newsels;
			//Simple Checked...
			case 'checkbox':
				return $persist && $this->valu ? ' checked="checked"' : null;
			//May get a little more complicated if we don't know what it is...
			default:
				return stripslashes(htmlspecialchars($persist ? GetVars($this->name, $this->valu) : $this->valu));
		}
	}

	static function GetPostValue($name)
	{
		$v = $_POST[$name];
		if (isset($_POST['type_'.$name]))
		{
			switch ($_POST['type_'.$name])
			{
				case 'date':
					return sprintf('%04d-%02d-%02d', $v[2], $v[0], $v[1]);
			}
		}

		return $v;
	}

	function GetData($val = null)
	{
		$val = GetVar($this->name, $val);
		switch ($this->type)
		{
			case 'mask':
				$ret = implode(null, $val);
				return !empty($ret) ? $ret : 0;
				break;
			case 'date':
				return sprintf('%04d-%02d-%02d', $val[2], $val[0], $val[1]);
			case 'checks':
			case 'selects':
				varinfo($val);
			default:
				return $val;
			break;
		}
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
	 * @param bool $group
	 * @param bool $selected
	 */
	function SelOption($text, $group = false, $selected = false)
	{
		$this->text = $text;
		$this->group = $group;
		$this->selected = $selected;
	}

	function __tostring() { return $this->text; }
}

/**
 * Returns a rendered <select> form input.
 * @param array $atrs eg: 'SIZE' => '5', 'MULTIPLE' => 'multiple'
 * @param array $value array of SelOption objects.
 * @param mixed $selvalue default selected seloption id.
 * @return string rendered select form input.
 */
function MakeSelect($atrs = null, $value = null, $selvalue = null)
{
	if (isset($atrs['VALUE'])) $selvalue = $atrs['VALUE'];
	unset($atrs['VALUE']);

	$strout = '<select';
	$strout .= GetAttribs($atrs);
	$strout .= ">\n";

	foreach ($value as $id => $option)
	{
		$selected = null;
		if ($id == $selvalue) $selected = ' selected="selected"';
		if ($option->selected) $selected = ' selected="selected"';
		$strout .= "<option value=\"{$id}\"$selected>{$option->text}</option>\n";
		$selected = null;
	}
	$strout .= "</select>\n";
	return $strout;
}

/**
 * Converts data retrieved from a DataSet into manageable SelOption objects.
 * @param array $result Rows retrieved from Get()
 * @param string $col_disp Column used for display.
 * @param string $col_id Column used for identification.
 * @param mixed $default Default selection.
 * @param string $none Text for unselected item (id of 0)
 * @return array SelOption array.
 */
function DataToSel($result, $col_disp, $col_id, $default = 0, $none = null)
{
	$ret = null;
	if (isset($none)) $ret[0] = new SelOption($none, false, $default == 0);
	if (!empty($result)) foreach ($result as $res)
	{
		$ret[$res[$col_id]] = new SelOption($res[$col_disp], false,
			strcmp($default, $res[$col_id]) == 0);
	}
	return $ret;
}

/**
 * Converts array('id' => 'text') items into SelOption objects.
 * @param array $array Array of items to convert.
 * @param mixed $default Default selected item id.
 * @param bool $use_keys Whether to use array keys or indices.
 * @return array Array of SelOption objects.
 */
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
 * @param string $name Name of this field.
 * @param int $timestamp Date to initially display.
 * @param bool $include_time Whether or not to add time to the date.
 * @return string Rendered date input.
 */
function GetInputDate($name = "", $timestamp = null, $include_time = false)
{
	if (is_array($timestamp))
	{
		if (isset($timestamp[5]))
			$timestamp = mktime($timestamp[3], $timestamp[4], $timestamp[5],
				$timestamp[0], $timestamp[1], $timestamp[2]);
		else
			$timestamp = mktime(0, 0, 0, $timestamp[0], $timestamp[1],
				$timestamp[2]);
	}
	else if (!is_numeric($timestamp) && !empty($timestamp))
	{
		$timestamp = MyDateTimestamp($timestamp, $include_time);
	}
	if (!isset($timestamp)) $timestamp = time();
	$strout = '<label>'.GetMonthSelect("{$name}[]", date('n', $timestamp)).
		'</label>';
	$strout .= "/ <input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"".
		date('d', $timestamp)."\" alt=\"Day\" />\n";
	$strout .= "/ <input type=\"text\" size=\"4\" name=\"{$name}[]\" value=\"".
		date('Y', $timestamp)."\" alt=\"Year\" />\n";
	$strout .= $include_time ? GetInputTime($name.'[]', $timestamp) : null;
	return $strout;
}

/**
 * Returns a series of 3 text boxes for a given timestamp.
 * @param string $name Name of these inputs are converted into name[] array.
 * @param int $timestamp Epoch timestamp for default value.
 * @return string Rendered form inputs.
 */
function GetInputTime($name, $timestamp)
{
	$strout = "<input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"".
		date('g', $timestamp)."\" alt=\"Hour\" />\n";
	$strout .= ": <input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"".
		date('i', $timestamp)."\" alt=\"Minute\" />\n";
	$strout .= "<select name=\"{$name}[]\">
		<option value=\"0\">AM</option>
		<option value=\"1\">PM</option>
		</select>";
	return $strout;
}

/**
 * Returns two radio buttons for selecting yes or no (1 or 0).
 * @param string $parent Name of the parent form if one is available.
 * @param array $atrs Array of HTML attributes.
 * @return string Rendered time input.
 */
function GetInputYesNo($parent, $attribs)
{
	$id = CleanID((isset($parent) ? $parent.'_' : null).$attribs['NAME']);
	if (!isset($attribs['ID'])) $attribs['ID'] = $id;
	if (!isset($attribs['VALUE'])) $attribs['VALUE'] = 0;
	if (!isset($attribs['TEXTNO'])) $attribs['TEXTNO'] = 'No';
	if (!isset($attribs['TEXTYES'])) $attribs['TEXTYES'] = 'Yes';
	return '<label><input type="radio" id="'.$attribs['ID'].'"
	name="'.$attribs['NAME'].'" value="0"'.
	($attribs['VALUE'] ? null : ' checked="checked"').' /> '.$attribs['TEXTNO'].'</label> ' .
	'<label><input type="radio" name="'.$attribs['NAME'].'" value="1"'.
	($attribs['VALUE'] ? ' checked="checked"' : null).' /> '.$attribs['TEXTYES'].'</label>';
}

define('STATE_CREATE', 0);
define('STATE_EDIT', 1);

define('CONTROL_SIMPLE', 0);
define('CONTROL_BOUND', 1);

/**
 * Simply returns yes or no depending on the positivity of the value.
 * @param array $val Value array, usually a row from a dataset.
 * @param mixed $col Index of $val to test for yes or no.
 * @return string 'Yes' or 'No'.
 */
function DBoolCallback($ds, $val, $col) { return BoolCallback($val[$col]); }

function BoolCallback($val) { return $val ? 'Yes' : 'No'; }

/**
 * @param array $val Value array, usually a row from a dataset.
 * @param mixed $col Index of $val to test for a unix epoch timestamp.
 */
function TSCallback($ds, $val, $col) { return date('m/d/Y', $val[$col]); }

/**
 * @param array $val Value array, usually a row from a dataset.
 * @param mixed $col Index of $val to test for a mysql formatted date.
 */
function DateCallbackD($ds, $val, $col) { return DateCallback($val[$col]); }
function DateCallback($val) { return date('m/d/Y', MyDateTimestamp($val)); }

function DateTimeCallbackD($ds, $val, $col) { return DateTimeCallback($val[$col]); }
function DateTimeCallback($val) { return date('m/d/Y h:i:s a', $val); }

/**
 * A node holds children.
 */
class TreeNode
{
	/**
	 * ID of this node (usually for database association)
	 *
	 * @var mixed
	 */
	public $id;
	/**
	 * Data associated with this node.
	 *
	 * @var mixed
	 */
	public $data;
	/**
	 * Child nodes of this node.
	 *
	 * @var array
	 */
	public $children;

	public $parent;

	/**
	 * Create a new TreeNode object.
	 *
	 * @param mixed $data Data to associate with this node.
	 */
	function TreeNode($data = null, $id = null)
	{
		$this->data = $data;
		$this->id = $id;
		$this->children = array();
	}

	function AddChild(&$tn)
	{
		$this->children[] = $tn;
		$tn->parent = $this;
	}

	function Find($id)
	{
		foreach ($this->children as $c)
		{
			if ($c->id == $id) return $c;
			else
			{
				$ret = $c->Find($id);
				if (isset($ret)) return $ret;
			}
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
	public $Name;

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
	 */
	function LoginManager($name)
	{
		@session_start();

		$this->Name = $name;
		$this->type = CONTROL_SIMPLE;

		$this->View = new LoginManagerView();
	}

	/**
	 * Processes the current login.
	 *
	 * @return mixed Array of user data or null if bound or true or false if not bound.
	 */
	function Prepare($conditions = null)
	{
		$passvar = $this->Name.'_sespass';
		$uservar = $this->Name.'_sesuser';

		$act = GetVar($this->Name.'_action');

		$check_user = ($this->type == CONTROL_BOUND && isset($_SESSION[$uservar]))
			? $_SESSION[$uservar] : null;

		$check_pass = isset($_SESSION[$passvar]) ? $_SESSION[$passvar] : null;

		if ($act == 'login')
		{
			if ($this->type == CONTROL_BOUND)
			{
				$check_user = ($this->type == CONTROL_BOUND) ? $check_user =
					GetVar($this->Name.'_auth_user') : null;
			 	SetVar($uservar, $check_user);
			}

			$check_pass = md5(GetVar($this->Name.'_auth_pass'));
			SetVar($passvar, $check_pass);
		}
		if ($act == 'logout')
		{
			$check_pass = null;
			UnsetVar($passvar);
			return false;
		}

		if ($this->type == CONTROL_BOUND)
		{
			if (empty($check_user) || empty($check_pass)) return;

			foreach ($this->datasets as $ds)
			{
				if (!isset($ds[0]))
					Error("<br />What: Dataset is not set.
					<br />Who: LoginManager::Prepare()
					<br />Why: You may have set an incorrect dataset in the
					creation of this LoginManager.");
				$match = array(
					$ds[1] => $check_pass,
					$ds[2] => $check_user
				);
				if (!empty($conditions))
					$match = array_merge($match, $conditions);
				$item = $ds[0]->GetOne($match);
				if ($item != null) return $item;
			}
		}
		else return $this->pass == $check_pass;
		return false;
	}

	/**
	 * Returns HTML rendered login form.
	 *
	 * @return string
	 */
	function Get($template = null)
	{
		global $me;
		if ($template == null)
			$template = dirname(__FILE__).'/temps/login_manager.xml';

		$f = new Form($this->Name, array(null, 'width="100%"'));
		$f->AddHidden($this->Name.'_action', 'login');
		if ($this->type != CONTROL_SIMPLE)
			$f->AddInput(new FormInput($this->View->TextLogin, 'text', 'auth_user'));
		$f->AddInput(new FormInput($this->View->TextPassword, 'password', 'auth_pass'));
		$f->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Login'));
		$f->Template = file_get_contents($template);
		return $f->Get('action="'.$me.'" method="post"');
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
	 * Sets a static password on this manager. If not MD5, you will recieve
	 * the proper md5 for this password to replace it with.
	 *
	 * @param string $pass Password.
	 */
	function SetPass($pass = null)
	{
		if (strlen($pass) != 32) die('Plaintext password! Use '.md5($pass)." instead.<br/>\n");
		$this->pass = $pass;
	}
}

class LoginManagerView
{
	public $TextLogin = 'Login';
	public $TextPassword = 'Password';
}

/**
 * An easy way to display a set of tabs, they basically work like a menubar.
 * @todo Get rid of this thing or revise it.
 */
class Tabs
{
	/**
	* Get the tab output.
	* @param array $tabs Array of tabs to be displayed.
	* @param string $body Body of currently selected tab.
	* @param string $advars Additional URI variables.
	* @param string $right Text to be displayed on the right side of the tabs.
	* @return string Rendered tabs.
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
	 */
	function DisplayObject() { }

	/**
	 * Gets name of this page.
	 * @param array $data Context data.
	 * @return string The name of this page for the browser's titlebar.
	 */
	function Get()
	{
		return "Class " . get_class($this) . " does not overload Get().";
	}

	/**
	 * Prepare this object for output.
	 * @param array $data Context data.
	 */
	function Prepare() { }
}

//Form Functions

/**
 * Returns a rendered <select> for a series of months.
 * @param string $name Name of the input.
 * @param int $default Default month.
 * @param string $attribs Additional attributes for the <select> field.
 * @return string Rendered month selection.
 */
function GetMonthSelect($name, $default, $attribs = null)
{
	$ret = "<select name=\"$name\"";
	if ($attribs != null) $ret .= " $attribs";
	$ret .= ">";
	for ($ix = 1; $ix <= 12; $ix++)
	{
		$ts = mktime(0, 0, 0, $ix, 1);
		if ($ix == $default) $sel = " selected=\"selected\"";
		else $sel = "";
		$ret .= "<option value=\"$ix\"$sel> " . date('F', $ts) . "</option>\n";
	}
	$ret .= "</select>\n";
	return $ret;
}

/**
 * Returns a rendered selection for picking years.
 * @param string $name Name of this inputs.
 * @param int $year Default selection.
 * @return string Rendered year selection.
 */
function GetYearSelect($name, $attribs)
{
	// Handle Attributes

	$year = strtoval(@$attribs['VALUE'], date('Y'));
	$shownav = strtoval(@$attribs['SHOWNAV'], true);
	$step = strtoval(@$attribs['STEP'], 5);
	$shownext = strtoval(@$attribs['SHOWNEXT'], true);
	$showprev = strtoval(@$attribs['SHOWPREV'], true);

	$from = $showprev ? $year-$step : $year;
	$next = $shownext ? $year+$step : $year;

	$ret = "<select name=\"$name\">";
	if ($shownav)
		$ret .= "<option value=\"".($from-1)."\"> &laquo; </option>\n";

	for ($ix = $from; $ix < $next; $ix++)
	{
		if ($ix == $year) $sel = " selected=\"selected\"";
		else $sel = "";
		$ret .= "<option value=\"$ix\"$sel>$ix</option>\n";
	}
	if ($shownav)
		$ret .= "<option value=\"".($next+1)."\"> &raquo; </option>\n";
	$ret .= "</select>\n";
	return $ret;
}

/**
 * @param array $atrs Default state number.
 * @return string Rendered <select> box.
 */
function GetInputState($atrs = null, $keys = true)
{
	global $StateNames;
	return MakeSelect($atrs, ArrayToSelOptions($StateNames, null, $keys));
}

/**
 * @param array $atrs Default state number.
 * @return string Rendered <select> box.
 */
function GetInputSState($atrs = null, $keys = true)
{
	global $StateSNames;
	return MakeSelect($atrs, ArrayToSelOptions($StateSNames, null, $keys));
}

$StateNames = array(
	0 => 'None',
	1 => 'Alabama',
	2 => 'Alaska',
	3 => 'Arizona',
	4 => 'Arkansas',
	5 => 'California',
	6 => 'Colorado',
	7 => 'Connecticut',
	8 => 'Delaware',
	9 => 'Florida',
	10 => 'Georgia',
	11 => 'Hawaii',
	12 => 'Idaho',
	13 => 'Illinois',
	14 => 'Indiana',
	15 => 'Iowa',
	16 => 'Kansas',
	17 => 'Kentucky',
	18 => 'Louisiana',
	19 => 'Maine',
	20 => 'Maryland',
	21 => 'Massachusetts',
	22 => 'Michigan',
	23 => 'Minnesota',
	24 => 'Mississippi',
	25 => 'Missouri',
	26 => 'Montana',
	27 => 'Nebraska',
	28 => 'Nevada',
	29 => 'New Hampshire',
	30 => 'New Jersey',
	31 => 'New Mexico',
	32 => 'New York',
	33 => 'North Carolina',
	34 => 'North Dakota',
	35 => 'Ohio',
	36 => 'Oklahoma',
	37 => 'Oregon',
	38 => 'Pennsylvania',
	39 => 'Rhode Island',
	40 => 'South Carolina',
	41 => 'South Dakota',
	42 => 'Tennessee',
	43 => 'Texas',
	44 => 'Utah',
	45 => 'Vermont',
	46 => 'Virginia',
	47 => 'Washington',
	48 => 'West Virginia',
	49 => 'Wisconsin',
	50 => 'Wyoming',
	51 => 'District of Columbia',
	52 => 'Canada',
	53 => 'Armed Forces Africa / Canada / Europe / Middle East',
);

$StateSNames = array(
	0 => 'NA',
	1 => 'AL',
	2 => 'AK',
	3 => 'AZ',
	4 => 'AR',
	5 => 'CA',
	6 => 'CO',
	7 => 'CT',
	8 => 'DE',
	9 => 'FL',
	10 => 'GA',
	11 => 'HI',
	12 => 'ID',
	13 => 'IL',
	14 => 'IN',
	15 => 'IA',
	16 => 'KS',
	17 => 'KY',
	18 => 'LA',
	19 => 'ME',
	20 => 'MD',
	21 => 'MA',
	22 => 'MI',
	23 => 'MN',
	24 => 'MS',
	25 => 'MO',
	26 => 'MT',
	27 => 'NE',
	28 => 'NV',
	29 => 'NH',
	30 => 'NJ',
	31 => 'NM',
	32 => 'NY',
	33 => 'NC',
	34 => 'ND',
	35 => 'OH',
	36 => 'OK',
	37 => 'OR',
	38 => 'PA',
	39 => 'RI',
	40 => 'SC',
	41 => 'SD',
	42 => 'TN',
	43 => 'TX',
	44 => 'UT',
	45 => 'VT',
	46 => 'VA',
	47 => 'WA',
	48 => 'WV',
	49 => 'WI',
	50 => 'WY',
	51 => 'DC',
	52 => 'CN',
	52 => 'AE',
);

function StateCallback($ds, $data, $col)
{
	global $__states;
	return $__states[$data[$col]]->text;
}

function TagIToState($t, $g, $a)
{
	global $StateNames; return @$StateNames[$a['STATE']];
}
function TagIToSState($t, $g, $a)
{
	global $StateSNames; return @$StateSNames[$a['STATE']];
}

/**
 * Converts any type of FormInput into a usable string, for example in text
 * only emails and suchs.
 *
 * @param FormInput $field
 * @return string Converted field.
 */
function InputToString($field)
{
	$val = GetVar($field->name);

	if ($field->type == 'time')
		return "{$val[0]}:{$val[1]}".($val[2] == 0 ? ' AM' : ' PM');
	else if ($field->type == 'checks')
	{
		$out = null;
		if (!empty($val))
		foreach (array_keys($val) as $ix)
			$out .= ($ix > 0?', ':'').$field->valu[$ix]->text;
		return $out;
	}
	else if ($field->type == 'radios') return $field->valu[$val]->text;
	else if ($field->type == 'yesno') return $val == 1 ? 'yes' : 'no';
	else if ($field->type == 'select') return $field->valu[$val]->text;
	else Error("Unknown field type.");
}

function ArrayToSelText($array, $sel)
{
	$ret = null;
	foreach ($array as $ix => $v)
		$ret .= ($ix > 0?', ':null).$sel[$v]->text;
	return $ret;
}

function GetHiddenPost($name, $val)
{
	$ret = '';
	if (is_array($val))
		foreach ($val as $n => $v)
			$ret .= GetHiddenPost($name.'['.$n.']', $v);
	else if (!empty($val)) $ret .= '<input type="hidden" name="'.$name.'" value="'.$val."\" />\n";
	return $ret;
}

/**
 * A SelOption callback, returns the value by the integer.
 */
function SOCallback($ds, $item, $icol, $col = null)
{
	if (isset($ds->FieldInputs[$col]->valu[$item[$icol]]))
		return $ds->FieldInputs[$col]->valu[$item[$icol]]->text;
	return $item[$icol];
}

function TagInput($t, $guts, $attribs, $tag, $args)
{
	// Handle Persistent Values

	if ($args['persist'])
	{
		switch (strtolower($attribs['TYPE']))
		{
			case 'radio':
				if (GetVar($attribs['NAME']) == $attribs['VALUE'])
					$attribs['CHECKED'] = 'checked';
				break;
			default:
				if (!isset($attribs['VALUE']))
				$attribs['VALUE'] = GetVar($attribs['NAME']);
				break;
		}
	}

	$searchable = $attribs['TYPE'] != 'hidden' && $attribs['TYPE'] != 'radio'
		&& $attribs['TYPE'] != 'checkbox' && $attribs['TYPE'] != 'submit';

	if (!empty($attribs['TYPE']))
	switch (strtolower($attribs['TYPE']))
	{
		case 'date':
			$field = '<input type="hidden" name="type_'.$attribs['NAME'].'" value="'.$attribs['TYPE'].'" />';
			$field .= GetInputDate($attribs['NAME'], @$attribs['VALUE']);
			break;
		case 'datetime':
			$field = GetInputDate($attribs['NAME'], @$attribs['VALUE'], true);
			break;
		case 'year':
			$field = GetYearSelect($attribs['NAME'], $attribs);
			break;
		case 'month':
			$field = GetMonthSelect($attribs['NAME'], @$attribs['VALUE']);
			break;
		case 'mask':
			$in = new FormInput(null, 'mask', $attribs['NAME'], @$attribs['VALUE']);
			$in->mask = $attribs['MASK'];
			$field = $in->Get();
			break;
		case 'yesno':
			$field = GetInputYesNo(null, $attribs);
			break;
		case 'radio':
			$field = '<input'.GetAttribs($attribs).' /> '.@$attribs['TEXT'];
			break;
		case 'checkbox':
			if (@$attribs['VALUE']) $attribs['CHECKED'] = 'checked';
			$attribs['VALUE'] = '1';
			$field = '<input'.GetAttribs($attribs).' /> '.@$attribs['TEXT'];
			break;
		case 'state':
			unset($attribs['TYPE']);
			$field = GetInputState($attribs);
			break;
		default:
			$field = '<input'.GetAttribs($attribs).' />';
			break;
	}

	$ret = '';

	if ($args == 'search' && $searchable)
	{
		if (!isset($attribs['ID'])) $attribs['ID'] = 'in'.$attribs['NAME'];

		$ret .= "<input name=\"search[{$attribs['NAME']}]\" type=\"checkbox\"
			onclick=\"$('#div{$attribs['ID']}').toggle('slow');\" />";
		$ret .= '<div id="div'.$attribs['ID'].'" style="display: none">';
	}

	$ret .= $field;

	if ($args == 'search' && $searchable) $ret .= '</div>';
	return $ret;
}

function TagUpper($t, $g, $a)
{
	return strtoupper($g);
}


function GetAttribs($attribs)
{
	$ret = '';
	if (is_array($attribs))
	foreach ($attribs as $n => $v) $ret .= ' '.strtolower($n).'="'.$v.'"';
	else return ' '.$attribs;
	return $ret;
}

function TagInputData(&$atrs)
{
	global $binds;

	if (!empty($atrs['NAME']))
	{
		if (!empty($binds[0][$atrs['NAME']]))
		{
			switch (strtolower($atrs['TYPE']))
			{
				case 'password':
					$atrs['VALUE'] = null; break;
				case 'date':
					$atrs['VALUE'] = MyDateTimestamp($binds[0][$atrs['NAME']]);
					break;
				case 'radio':
				case 'checkbox':
					if (!isset($atrs['VALUE'])) $atrs['VALUE'] = 1;
					if ($atrs['VALUE'] == $binds[0][$atrs['NAME']])
						$atrs['CHECKED'] = 'checked';
					break;
				default:
					$atrs['VALUE'] = $binds[0][$atrs['NAME']];
			}
		}
	}
	return $atrs;
}

function TagInputDisplay($t, $guts, $tag)
{
	switch (strtolower($tag['TYPE']))
	{
		case 'hidden':
		case 'submit':
		case 'button':
			break;
		case 'password':
			return '********';
		case 'text':
			return @$tag['VALUE'];
		case 'date':
			return date('m/d/Y', $tag['VALUE']);
		case 'checkbox':
			return @$tag['CHECKED'] == 'checked' ? 'Yes' : 'No';
		default:
			echo "Unknown type: {$tag}<br/>\n";
	}
}

/**
 * @param string $t Target page that this should link to.
 */
function GetNav($t, $links, $attribs = null, $curpage = null, &$pinfo, &$stack)
{
	$ret = "\n<ul".GetAttribs($attribs).">\n";
	foreach ($links->children as $ix => $link)
	{
		$stack[] = $ix;
		if (isset($link->data['page']))
			if (substr($_SERVER['REQUEST_URI'], -strlen($link->data['page']))
			== $link->data['page'])
				$pinfo = $stack;
		$ret .= '<li>';

		if (isset($link->data['page'])) $ret .= '<a href="'.$link->data['page'].'">';
		$ret .= $link->data['text'];
		if (isset($link->data['page'])) $ret .= '</a>';
		if (!empty($link->children))
			$ret .= GetNav($t, $link, null, $curpage, $pinfo, $stack);
		$ret .= "</li>\n";
		array_pop($stack);
	}
	return $ret."</ul>\n";
}

function GetNavPath($t, $tree, $pinfo)
{
	$ret = '';
	$tn = $tree;
	if (!empty($pinfo))
	foreach ($pinfo as $level => $idx)
	{
		$tn = $tn->children[$idx];
		$ret .= ($level ? ' &raquo; ' : null);
		if (!empty($tn->data['page'])) $ret .= '<a href="'.$tn->data['page'].'">';
		$ret .= $tn->data['text'];
		if (!empty($tn->data['page'])) $ret .= '</a>';
	}
	return $ret;
}

function TagSum(&$t, $guts, $attribs)
{
	//Concatination with string based names.
	if (!empty($attribs['NAMES']))
	{
		$names = $GLOBALS[$attribs['NAMES']];
		$ret = '';
		$ix = 0;
		$m = null;
		foreach ($t->vars as $n => $v)
			if (!empty($v) && preg_match($attribs['VALUE'], $n, $m))
			{
				if ($ix++ > 0) $ret .= ', ';
				$ret .= (count($m) > 1 ? $names[$m[1]]->text : $names[$v]->text);
			}
		return $ret;
	}

	//Collect total numeric sum.
	else
	{
		$sum = null;
		foreach ($t->vars as $n => $v)
			if (preg_match($attribs['VALUE'], $n))
				$sum += $v;
		return $sum;
	}
}

function AddResultTags(&$t)
{
	$t->ReWrite('sum', 'TagSum');
}

function TagForm($t, $g, $a)
{
	global $PERSISTS;
	$ret = '<form'.GetAttribs($a).'>';
	if (is_array($PERSISTS))
	foreach ($PERSISTS as $n => $v)
		$ret .= '<input type="hidden" name="'.$n.'" value="'.$v.'" />';
	$ret .= $g;
	$ret .= '</form>';
	return $ret;
}

class DataDisplay
{
	function DataDisplay($name, $ds)
	{
		$this->Name = $name;
		$this->ds = $ds;
	}

	function Get($temp)
	{
		$this->ci = GetVar($this->Name.'_ci');

		$tg = new Template();
		$tg->Set('name', $this->Name);
		$tg->ReWrite('listitem', array(&$this, 'TagListItem'));
		$tg->ReWrite('detailitem', array(&$this, 'TagDetailItem'));
		return $tg->GetString($temp);
	}

	/**
	 * This is repeated for each item in the associated dataset.
	 *
	 * @param Template $t Associated template.
	 * @param string $guts Contents of the tag.
	 */
	function TagListItem($t, $guts)
	{
		$ret = '';

		if (empty($this->ci))
		{
			$vp = new VarParser();

			foreach ($this->ds->Get() as $i)
			{
				$ret .= $vp->ParseVars($guts, $i);
			}
		}

		return $ret;
	}

	/**
	 * This is called for a detail tag in the template.
	 *
	 * @param Template $t Associated template.
	 * @param string $guts Contents of the tag.
	 */
	function TagDetailItem($t, $guts)
	{
		$vp = new VarParser();

		if (!empty($this->ci))
		{
			$item = $this->ds->GetOne(array($this->ds->id => $this->ci));

			return $vp->ParseVars($guts, array_merge($item));
		}
	}
}

?>
