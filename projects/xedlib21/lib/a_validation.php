<?php

/**
 * Processes validation on a form by means of regular expressions in javascript
 * and server-side php.
 * @see Form
 * @example test_display.php
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
	 * @param string $field Name of form field, see $field
	 * @param string $check Regular expression to test whether this is valid.
	 * @param string $error Error message to display if test fails.
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
	 * @param string $value Only if this field contains the value that is
	 * specified by $value will the child be checked.
	 * @param Validation $child Child validation object.
	 */
	function Add($value, $child)
	{
		$this->validators[] = array($value, $child);
	}

	/**
	 * Gets the javascript associated with this validation.
	 *
	 * @param string $id ID of associated input.
	 * @return string
	 */
	function GetJS($id = null)
	{
		$ret = null;
		if (!empty($this->validators))
			foreach ($this->validators as $v)
				$ret .= $v[1]->GetJS($id);
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
				foreach ($this->validators as $v)
					$ret .= "\t\t\t\t{$id}_{$v[1]->field}_check(0);\n";
			$ret .= "\t\t\t\treturn false;
			}";
		}
		$ret .= "\n\t\t\telse
			{\n";
				foreach ($this->validators as $v)
				{
					$ret .= "\t\t\t\t{$id}_{$v[1]->field}_check(0);\n";
				}
				$ret .= "\t\t\t\tspn_{$id}_{$this->field}.innerHTML = '';\n";
				foreach ($this->validators as $v)
				{
					$ret .= "\t\t\t\tret = {$id}_{$v[1]->field}_check(/^$v[0]$/.test(chk_{$this->field}.value));\n";
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
	 * @param string $form Name of parent form.
	 * @param bool $check Actually test the validation.
	 * @param array $ret Array of errors for anything that did not pass.
	 * @return bool Whether this object passed or not.
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
			foreach ($this->validators as $v) $v[1]->Validate($form, $check, $ret);
		}
		return $passed;
	}
}

/**
 * Validates a form and generates the errors and information in $ret.
 *
 * @param string $name Name of the form.
 * @param mixed $arr Validation(s) to check for fields of $name form.
 * @param array $ret Resulting information
 * @param bool $check Whether to actually validate the form or prepare to.
 * @return bool Whether the form failed or succeeded validation (if $check is false
 * it will always pass.)
 */
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

/**
 * Recurses requirements in order to generate proper javascript.
 * @param string $key Id of the form field to validate.
 * @param mixed $val Either a series of Validation or a single Validation.
 * @param string $checks The actual rendered javascript checks.
 */
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
		$checks .= "\tif (chk_{$key}.value.length < 1) { alert('{$val->error}'); chk_{$key}.focus(); return false; }\n";
	}
}

?>