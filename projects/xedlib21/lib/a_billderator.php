<?php

class Billderator
{
	var $inputs;
	var $DisplayGrid = false;

	function __construct()
	{
		$this->inputs = array();
	}

	function Prepare($action)
	{
		if ($action == 'add') $this->AddInput(new Input($_GET['type']));
		else if ($action == 'clear') $this->Clear();
		else if ($action == 'grid') $this->ToggleGrid();
		else if ($action == 'del') $this->Delete($_GET['ci']);
		else if ($action == 'prop') {}
		else if ($action == 'save') $this->SaveProperties(GetVar('ci'));
	}

	function Get($target)
	{
		$toggle = '';
		if ($this->DisplayGrid)
			$toggle = 'style="border: solid 1px #000000; padding: 5px;
			width: 300px; height: 50px;"';
		else $toggle = 'style="width: 300px; height: 50px;"';

		$ret = '<div class="menu"><p style="text-align: center"><b>Tool Box</b></p><hr />';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=textarea", 'billderator/textarea.png', 'Textarea').' Text Area<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=radio", 'billderator/radio.png', 'Radio Button').' Radio Button<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=checkbox", 'billderator/checkbox.png', 'Checkbox').' Checkbox<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=button", 'billderator/button.png', 'Button').' Button<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=file", 'billderator/button.png', 'File Upload').' File Upload<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=image", 'billderator/image.png', 'Image Button').' Image Button<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=reset", 'billderator/button.png', 'Reset Button').' Reset Button<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=submit", 'billderator/button.png', 'Submit Button').' Submit Button<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=text", 'billderator/text.png', 'Textbox').' Textbox<br/>';
		$ret .= "\n".GetButton("{$target}?ca=add&amp;type=password", 'billderator/password.png', 'Password').' Password<br/>';
		$ret .= "\n</div><table>\n";

		for($x = 0; $x < count($this->inputs); $x++)
		{
			$ret .= '<tr><td '.$toggle.'>';
			$ret .= $this->inputs[$x]->GetInput();
			$ret .= '</td><td>'.$this->GetEditButtons($target, $x).'</td></tr>';
		}

		return $ret.'</table>';
	}

	function AddInput($input) { array_push($this->inputs, $input); }

	function GetEditButtons($target, $current_input)
	{
		$props = $this->GetInputProperties($current_input);

		$butProp = GetButton("{$target}?ca=prop&amp;ci={$current_input}",
			'edit.png', 'Control Properties',
			"onclick=\"toggle('prop_{$current_input}'); return false;\"");

		$butMove = GetButton("{$target}?ca=move&amp;ci={$current_input}",
			'move.png', "Move this input to a different location on the form");

		$butDelete = GetButton("{$target}?ca=del&amp;ci={$current_input}",
			'delete.png', 'Delete this input from the form',
			"onclick=\"return confirm('Are you sure?');\"");

		return <<<EOF
		<table style="border:solid 1px #000000;">
			<tr>
				<td colspan="3" style="background-color:#ededed;
				font-size:11px;
				text-align:center;">Options</td>
			</tr><tr>
				<td style="padding:3px;">$butProp</td>
				<td style="padding:3px;">$butMove</td>
				<td style="padding:3px;">$butDelete</td>
			</tr>
		</table>
		<div style="display:none; padding:4px; background-color:#ededed;" id="prop_{$current_input}">
		{$props}
		</div>
EOF;
	}

	function GetInputProperties($current_input)
	{
		$input = $this->inputs[$current_input];
		$props = array();

		$frm = new Form('frmProps');
		$frm->AddHidden('ca', 'save');
		$frm->AddHidden('ci', $current_input);

		switch ($input->type)
		{
			case 'textarea':
				$frm->AddInput(new FormInput('Inline Style Rules', 'text', 'props[style]'));
				$frm->AddInput(new FormInput('Rows', 'text', 'props[rows]'));
				$frm->AddInput(new FormInput('Columns', 'text', 'props[cols]'));
				$frm->AddInput(new FormInput('Read Only', 'yesno', 'props[ro]'));
				$frm->AddInput(new FormInput('Required', 'yesno', 'props[req]'));
				break;
			case 'button':
				$frm->AddInput(new FormInput('Value', 'text', 'props[value]'));
		}

		$frm->AddInput(new FormInput(null, 'submit', 'butUpdate', 'Update'));

		global $me;
		return $frm->Get('method="post" action="'.$me.'"');
	}

	function SaveProperties($current_input)
	{
		$input = $this->inputs[$current_input];
		$input->attributes = array_merge($input->attributes, GetVar('props'));
	}

	function Delete($input)
	{
		unset($this->inputs[$input]);
		array_splice($this->inputs, 0, 0, array());
	}

	function Clear()
	{
		$this->inputs = array();
	}
}

class Input
{
	/**
	 * Test Description
	 *
	 * @var unknown_type
	 */
	var $name;
	var $type;
	var $attributes;
	function Input($type)
	{
		//$this->name = $name;
		$this->type = $type;
		$this->attributes = array();
		$this->SetAttributes($type);
		//echo $_SESSION['counter'];

	}

	function GetInput()
	{
		return $this->GetHTMLInput();
	}


	function SetAttributes($type)
	{
		$this->attributes['type'] = $type;
		$this->attributes['id'] = '';//some uique ID possibly
		$this->attributes['class'] = '';
		$this->attributes['style'] = '';

		switch ($type)
		{
			case 'button':
				$this->attributes['value'] = '';
				break;
			case 'checkbox':
				$this->attributes['value'] = '';
				break;

			case 'file':
				$this->attributes['size'] = '';
				break;

			case 'image':
				$this->attributes['src'] = '';
				break;

			case 'password':
				$this->attributes['value'] = '';
				$this->attributes['size'] = '';
				$this->attributes['readonly'] = '';
				break;

			case 'radio':
				$this->attributes['value'] = '';
				break;

			case 'reset':
				$this->attributes['value'] = '';
				break;

			case 'submit':
				$this->attributes['value'] = '';
				break;

			case 'text':
				$this->attributes['value'] = '';
				$this->attributes['size'] = '';
				$this->attributes['readonly'] = '';
				break;

			case 'textarea':
				//$this->attributes['value'] = '';
				//$this->attributes['size'] = '';
				$this->attributes['rows'] = '';
				$this->attributes['cols'] = '';
				$this->attributes['readonly'] = '';
				break;
		}
	}

	//returns the inputs with advanced options if any.
	function GetHTMLInput()
	{
		/*
		$this->attributes['type'] = $type;
		$this->attributes['id'] = '';//some uique ID possibly
		$this->attributes['class'] = '';
		$this->attributes['style'] = '';
		*/

		$ret = <<<EOF
		<input type="{$this->attributes['type']}"
		 id="{$this->attributes['id']}"
		 class="{$this->attributes['class']}"
		 style="{$this->attributes['style']}"
EOF;

		switch ($this->type)
		{
			case 'button': $ret .= " value=\"{$this->attributes['value']}\""; break;
			case 'checkbox': $ret .= " value=\"{$this->attributes['value']}\""; break;
			case 'file': $ret .= " size=\"{$this->attributes['size']}\""; break;
			case 'image': " src=\"{$this->attributes['src']}\""; break;
			case 'password':
				$ret .= " value=\"{$this->attributes['value']}\"";
				$ret .= " size=\"{$this->attributes['size']}\"";
				if ($this->attributes['readonly'] == 'readonly')
					$ret .= ' readonly="readonly"';
			break;
			case 'radio': $ret .= " value=\"{$this->attributes['value']}\""; break;
			case 'reset': $ret .= " value=\"{$this->attributes['value']}\""; break;
			case 'submit': $ret .= " value=\"{$this->attributes['value']}\""; break;
			case 'text':
				$ret .= " value=\"{$this->attributes['value']}\"";
				$ret .= " size=\"{$this->attributes['size']}\"";
				if($this->attributes['readonly'] == 'readonly')
					$ret .= ' readonly="readonly"';
			break;
			case 'textarea':
				$area =  <<<EOF
			<textarea
			rows="{$this->attributes['rows']}"
			cols="{$this->attributes['cols']}"
			class="{$this->attributes['class']}"
			style="{$this->attributes['style']}"
EOF;
			if($this->attributes['readonly'] == 'readonly')
			{
				$area .= 'readonly></textarea>';
			}
			else
				$area .= '></textarea>';

			return $area;
				break;
		}

		//echo $ret;

		return $ret . ' />';
	}
}


?>