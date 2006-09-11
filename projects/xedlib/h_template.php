<?php

/**
* A template
*/
class Template
{
	public $vars; //!< Variables that have been set with set().
	public $out; //!< Raw output to be rendered.
	public $objs; //!< Set of objects to output to.
	public $use_getvar; //!< Whether or not to use GetVar() for {{vars}}

	function Template()
	{
		$this->out = "";
		$this->objs = array();
		$this->vars = array();
		$this->use_getvar = false;
	}

	/**
	 * Begin a template tag.
	 * @param $parser Xml parser for current document.
	 * @param $tag Tag we are beginning.
	 * @param $attribs Attributes in the tag.
	 */
	function Start_Tag($parser, $tag, $attribs)
	{
		$show = true;
		$close = '';

		$output = '';

		if ($tag == 'AMP') $output = '&amp;';
		else if ($tag == 'BOX')
		{
			if (isset($attribs["HANDLER"]))
			{
				$handler = $attribs["HANDLER"];
				if (file_exists("$handler.php")) require_once("$handler.php");
				if (class_exists($handler)) $box = new $handler;
				else die("Class does not exist ($handler).<br/>\n");
			}
			else $box = new Box();
			if (isset($attribs['TITLE'])) $box->title = $attribs['TITLE'];
			if (isset($attribs['TEMPLATE'])) $box->template = $attribs['TEMPLATE'];
			if (isset($attribs['ID'])) $box->name = $attribs['ID'];
			$this->objs[] = $box;
			$show = false;
		}
		else if ($tag == 'BR') $close = ' /';
		else if ($tag == 'COPY') $output .= '&copy;';
		else if ($tag == 'DOCTYPE')
		{
			if (isset($attribs["TYPE"]))
			{
				if ($attribs['TYPE'] == 'strict')
					$output = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN"
						"http://www.w3.org/TR/html4/strict.dtd">';
				if ($attribs['TYPE'] == 'trans')
					$output = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
			}
		}
		else if ($tag == 'IMG') $close = ' /';
		else if ($tag == 'INPUT') $close = ' /';
		else if ($tag == 'LINK') $close = ' /';
		else if ($tag == 'META') $close = ' /';
		else if ($tag == 'MODULE')
		{
			require_once('modules/'.$attribs['FILE']);
			$class = $attribs['CLASS'];
			if (!class_exists($class)) Error("Class ($class) does not exist");
			$mod = new $class();
			$output = $mod->Get();
		}
		else if ($tag == 'NBSP') $output = '&nbsp;';
		else if ($tag == 'NULL') $show = false;
		else if ($tag == "TEMPLATE")
		{
			if (isset($attribs["FILE"]))
			{
				$t = new Template();
				$obj = &$this->GetCurrentObject();
				$obj->out .= $t->Get($attribs["FILE"]);
			}
		}
		else if ($tag == "XFORM")
		{
			if (isset($attribs["NAME"])) $name = $attribs["NAME"];
			else $name = "formUnnamed";
			$form = new Form($name);
			foreach ($attribs as $name => $val) $form->attribs[$name] = $val;
			$this->objs[] = $form;
		}
		else if ($tag == "XINPUT")
		{
			if (isset($attribs["TYPE"]))
			{
				if ($attribs["TYPE"] == "hidden")
				{
					$obj = &$this->GetCurrentObject();
					$obj->AddHidden($attribs["NAME"], $attribs["VALUE"]);
					return;
				}
			}
			if (isset($attribs["TEXT"])) $text = $attribs["TEXT"];
			else $text = "";
			if (isset($attribs["NAME"])) $name = $attribs["NAME"];
			else $name = "";
			if (isset($attribs["VALUE"])) $value = $attribs["VALUE"];
			else $value = "";
			if (isset($attribs["HELP"])) $help = $attribs["HELP"];
			else $help = NULL;

			$obj = &$this->GetCurrentObject();
			$obj->AddInput($text, $attribs["TYPE"], $name, $value, null, $help);
		}

		if ($show)
		{
			$obj = &$this->GetCurrentObject();
			if (strlen($output) > 0) $obj->out .= "{$output}";
			else
			{
				$obj->out .= '<'.strtolower($tag);
				foreach ($attribs as $name => $val)
					$obj->out .= ' '.strtolower($name).'="'.$val.'"';
				$obj->out .= "{$close}>";
			}
		}
	}

	/**
	 * If this is one of our tags, we're going to have
	 * to dump the output of the top level object down
	 * to the next object, if that object doesn't exist
	 * we should throw something.
	 * @param $parser Xml parser for current document.
	 * @param $tag Tag that is being terminated.
	 */
	function End_Tag($parser, $tag)
	{
		if ($tag == 'AMP') return;
		else if ($tag == 'BR') return;
		else if ($tag == 'COPY') return;
		else if ($tag == 'DOCTYPE') return;
		else if ($tag == 'IMG') return;
		else if ($tag == 'INPUT') return;
		else if ($tag == 'LINK') return;
		else if ($tag == 'META') return;
		else if ($tag == 'MODULE') return;
		else if ($tag == 'NBSP') return;
		else if ($tag == 'NULL') return;

		else if ($tag == 'BOX')
		{
			$objc = &$this->GetCurrentObject();
			$objd = &$this->GetDestinationObject();
			$objd->out .= $objc->Get();
			array_pop($this->objs);
		}
		else if ($tag == 'XFORM')
		{
			$objc = &$this->GetCurrentObject();
			$objd = &$this->GetDestinationObject();
			$objd->out .= $objc->Get();
			array_pop($this->objs);
		}
		else
		{
			$obj = &$this->GetCurrentObject();
			$obj->out .= '</'.strtolower($tag).'>';
		}
	}

	/**
	 * Parse Character Data for an xml parser.
	 * @param $parser Xml parser for current document.
	 * @param $text Actual Character Data.
	 */
	function CData($parser, $text)
	{
		$obj = &$this->GetCurrentObject();
		$obj->out .= $text;
	}

	function Process($parser, $text, $data)
	{
		ob_start();
		eval($data);
		$obj = &$this->GetCurrentObject();
		$obj->out .= ob_get_contents();
		ob_end_clean();
	}

	/**
	 * Gets the object before the last object on the stack.
	 */
	function &GetDestinationObject()
	{
		if (count($this->objs) > 1) return $this->objs[count($this->objs)-2];
		else return $tmp = &$this;
	}

	/**
	 * Gets the last object on the stack
	 */
	function &GetCurrentObject()
	{
		if (count($this->objs) > 0) return $this->objs[count($this->objs)-1];
		else return $tmp = &$this;
	}

	/**
	 * Set a variable for use on this page.
	 * @param $var Name of the variable.
	 * @param $val Value of the variable.
	 */
	function Set($var, $val = null)
	{
		if (is_array($var) && !empty($var))
		{
			$this->vars = array_merge($this->vars, $var);
		}
		else if (get_class($var) != null)
		{
			$array = get_object_vars($var);
			foreach ($array as $key => $val)
			{
				if (!is_array($var)) $this->vars[$key] = $val;
			}
		}
		else $this->vars[$var] = $val;
	}

	/**
	 * Get a rendered template.
	 * @param $template string The template file.
	 */
	function Get($template)
	{
		$this->out = "";
		if (!file_exists($template)) { trigger_error("Template not found (" . $template . ")", E_USER_ERROR); return NULL; }
		$parser = xml_parser_create_ns();
		$data = array();
		$index = array();
		xml_set_object($parser, $this);
		xml_set_element_handler($parser, "Start_Tag", "End_Tag");
		xml_set_character_data_handler($parser, "CData");
 		xml_set_default_handler($parser, "CData");
 		xml_set_processing_instruction_handler($parser, "Process");

		$lines = file($template);
		foreach ($lines as $line)
		{
			if (!xml_parse($parser, $line))
			{
				echo "XML Error: " . xml_error_string(xml_get_error_code($parser)) .
				" on line " . xml_get_current_line_number($parser) .
				" of file " . $template . "<br/>\n";
			}
		}
		xml_parser_free($parser);
		return preg_replace_callback("/\{{([^}]+)\}}/", array($this, "parse_vars"), $this->out);
	}

	/**
	 * Parse variables that have been set using set() to replace
	 * them in this template.
	 * @param $match A regexp match.
	 */
	function parse_vars($match)
	{
		$tvar = $match[1];
		global $$tvar;
		if (key_exists($tvar, $this->vars)) return $this->vars[$tvar];
		else if (isset($$tvar)) return $$tvar;
		else if (defined($tvar)) return constant($tvar);
		else if ($this->use_getvar && GetVar($tvar) != null) return GetVar($tvar);
		return $match[0];
	}
}

?>
