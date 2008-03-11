<?php

/**
 * @package Presentation
 */

/**
 * Returns a callstack style template stack, showing the path that
 * processing has gone.
 * @param array $data Context information.
 * @return string Debug template stack.
 */
function GetTemplateStack(&$data)
{
	$ret = null;
	if (!empty($data['template.stack']))
	{
		$parsers = $data['template.parsers'];
		$stack = $data['template.stack'];
		for ($ix = count($data['template.stack'])-1; $ix >= 0; $ix--)
		{
			$ret .= "{$stack[$ix]} made it to line: ".
				xml_get_current_line_number($parsers[$ix])."<br/>\n";
		}
	}
	return $ret;
}

/**
 * @param array $data Context information.
 * @param string $file Filename to require.
 * @param string $class Class to prepare.
 * @return mixed Module
 */
function RequireModule(&$data, $file, $class)
{
	if (isset($data['includes'][$class]))
	{
		Trace("RequireModule: Returning already included module.
			({$class})<br/>\n");
		return $data['includes'][$class];
	}

	if (!file_exists($file))
	{
		Error("\n<b>What</b>: File ({$file}) does not exist.
		<b>Who</b>: RequireModule()
		<b>Where</b>: Template stack...\n".GetTemplateStack($data).
		"<b>Why</b>: You may have moved or deleted this file.");
	}

	require_once($file);

	if (!class_exists($class))
		Error("\n<b>What</b>: Class ({$class}) does not exist.
		<b>Who</b>: &lt;INCLUDE> tag
		<b>Where</b>: Template stack...\n".GetTemplateStack($data).
		"<b>Why</b>: You may have moved this class to another file.");

	$mod = new $class($data);
	$data['includes'][$class] = $mod;
	$mod->Prepare($data);
	return $mod;
}

/**
 * A template
 */
class Template
{
	/**
	 * Variables that have been set with set().
	 *
	 * @var array
	 */
	public $vars;
	/**
	 * Raw output to be rendered.
	 *
	 * @var string
	 */
	public $out;
	/**
	 * Set of objects to output to.
	 *
	 * @var array
	 */
	public $objs;
	/**
	 * Whether or not to use GetVar() for {{vars}}
	 *
	 * @var bool
	 */
	public $use_getvar;
	/**
	 * Handlers for specific features.
	 *
	 * @var array
	 */
	public $handlers;
	/**
	 * Current data.
	 *
	 * @var mixed
	 */
	public $data;
	/**
	 * Files that will be included.
	 *
	 * @var array
	 */
	public $includes;

	/**
	 * The local parser that this template
	 * is using to process XML files.
	 *
	 * @var resource
	 */
	private $parser;

	private $skip;

	public $Behavior;

	private $config;

	private $start = '';

	/**
	 * Creates a new template parser.
	 */
	function Template()
	{
		$this->Behavior = new TemplateBehavior();
		$args = func_get_args();
		if (isset($args[0])) $data = &$args[0];
		$this->out = '';
		$this->objs = array();
		$this->vars = array();
		$this->data = &$data;
		$this->use_getvar = false;
		$this->vars['relpath'] = GetRelativePath(dirname(__FILE__));
	}

	function ReWrite($tag, $callback, $args)
	{
		$this->rewrites[strtoupper($tag)][] = $callback;
		$this->rewriteargs[strtoupper($tag)] = $args;
	}

	function Transform($tag, $callback)
	{
		$this->transforms[strtoupper($tag)][] = $callback;
	}

	/**
	 * Begin a template tag.
	 * @param resource $parser Xml parser for current document.
	 * @param string $tag Tag we are beginning.
	 * @param array $attribs Attributes in the tag.
	 */
	function Start_Tag($parser, $tag, $attribs)
	{
		if ($this->skip) return;

		if (isset($this->transforms[$tag]))
		{
			$ret = RunCallbacks($this->transforms[$tag], $attribs);
			$attribs = array_merge($attribs, $ret);
		}

		if (isset($this->rewrites[$tag]))
		{
			$obj = new stdClass();
			$obj->attribs = $attribs;
			$obj->tag = $tag;
			$obj->out = '';
			$this->objs[] = $obj;
			$show = false;
		}
		else $show = true;
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
				else die("Class does not exist ($handler).\n");
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
			$show = false;
			if (isset($attribs['TYPE']))
			{
				if ($attribs['TYPE'] == 'strict')
					$this->start .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
				if ($attribs['TYPE'] == 'trans')
					$this->start .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
			}
		}
		else if ($tag == 'FORM' && $this->Behavior->MakeDynamic)
			$this->start .= $this->ProcessForm($parser, $tag, $attribs);
		if ($tag == 'IF')
		{
			$vp = new VarParser();
			$check = $vp->ParseVars($attribs['CHECK'], $this->vars);
			$GLOBALS['_trace'] = GetTemplateStack($this->data);
			if (!eval('return '.$check.';')) $this->skip = true;
			$show = false;
			return;
		}
		else if ($tag == 'IMG') $close = ' /';
		else if ($tag == 'INCLUDE')
		{
			$file = $attribs['FILE'];
			$class = $attribs['CLASS'];

			RequireModule($this->data, $file, $class);
			$show = false;
		}
		else if ($tag == 'INPUT')
		{
			if ($this->Behavior->MakeDynamic)
				$this->out .= $this->ProcessInput($parser, $tag, $attribs);
			$close = ' /';
		}
		else if ($tag == 'LINK') $close = ' /';
		else if ($tag == 'META') $close = ' /';
		else if ($tag == 'NBSP') $output = '&nbsp;';
		else if ($tag == 'NULL') $show = false;
		else if ($tag == 'PARAM') $close = ' /';
		else if ($tag == 'PRESENT')
		{
			$name = $attribs['NAME'];

			if (!isset($this->data['includes'][$name]))
			{
				Error("\nWhat: Attempted to present a module that doesn't exist.
				\nWho: Module ({$name})
				\nWhere: {$this->template} on line ".
				xml_get_current_line_number($parser)."
				\nWhy: Data variable has possibly been altered.");
			}
			$this->objs[] = $this->data['includes'][$name];
			$show = false;
		}
		else if ($tag == "TEMPLATE")
		{
			if (isset($attribs["FILE"]))
			{
				$t = new Template($this->data);
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
			$obj->AddInput(new FormInput($text, $attribs["TYPE"], $name,
				$value, null, $help));
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
	 * @param resource $parser Xml parser for current document.
	 * @param string $tag Tag that is being terminated.
	 */
	function End_Tag($parser, $tag)
	{
		if ($tag == 'IF') { $this->skip = false; return; }
		if ($this->skip) return;
		if (!empty($this->rewrites[$tag]))
		{
			$obj = &$this->GetCurrentObject();
			$objd = &$this->GetDestinationObject();

			$vp = new VarParser();
			$objd->out .= RunCallbacks($this->rewrites[$tag], $obj->out,
				$vp->ParseVars($obj->attribs, $this->vars), $obj->tag,
				@$this->rewriteargs[$tag]);

			array_pop($this->objs);
			return;
		}

		if ($tag == 'AMP') return;
		else if ($tag == 'BOX')
		{
			$objc = &$this->GetCurrentObject();
			$objd = &$this->GetDestinationObject();
			$objd->out .= $objc->Get();
			array_pop($this->objs);
		}
		else if ($tag == 'BR') return;
		else if ($tag == 'COPY') return;
		else if ($tag == 'DOCTYPE') return;
		else if ($tag == 'IMG') return;
		else if ($tag == 'INCLUDE') return;
		else if ($tag == 'INPUT') return;
		else if ($tag == 'LINK') return;
		else if ($tag == 'META') return;
		else if ($tag == 'NBSP') return;
		else if ($tag == 'NULL') return;
		else if ($tag == 'PARAM') return;
		else if ($tag == 'PRESENT')
		{
			$objc = &$this->GetCurrentObject();
			$objd = &$this->GetDestinationObject();
			if (!isset($objc)) Error("Current object doesn't exist.");
			if (!isset($objd)) Error("Destination object doesn't exist.");
			$objd->out .= $objc->Get($this->data);
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
	 * @param resource $parser Xml parser for current document.
	 * @param string $text Actual Character Data.
	 */
	function CData($parser, $text)
	{
		if ($this->skip) return;
		$obj = &$this->GetCurrentObject();
		$obj->out .= $text;
	}

	/**
	 * Evaluates code (Eg. <?php echo "Hello"; ?>) in a template.
	 *
	 * @param resource $parser Parser object
	 * @param string $text Unknown.
	 * @param string $data Code in question.
	 */
	function Process($parser, $text, $data)
	{
		if ($this->skip) return;
		ob_start();
		eval($data);
		$obj = &$this->GetCurrentObject();
		$obj->out .= ob_get_contents();
		ob_end_clean();
	}

	function ProcessForm($parser, $tag, $attribs)
	{
		if (!isset($this->config)) return $this->GetConfig();
	}

	function ProcessInput($parser, $tag, $attribs)
	{
		if (!isset($this->config['fields'][$attribs['NAME']]))
		{
			$this->configured = false;

			$frm = new Form('formConfig');
			$frm->AddHidden('ca', 'template_config');
			$frm->AddInput(new FormInput('Data Column:', 'text', 'host'));
			$frm->AddInput(new FormInput('Data Type:', 'text', 'user'));
			$frm->AddInput(new FormInput('Data Length:', 'password', 'pass'));
			$frm->AddInput(new FormInput(null, 'submit', null, 'Configure'));
			return GetBox('box_field', "Field {$attribs['NAME']}",
				$frm->Get('method="post" action="{{me}}"'));
		}
	}

	function GetConfig()
	{
		$frm = new Form('formConfig');
		$frm->AddHidden('ca', 'template_config');
		$frm->AddInput(new FormInput('Database Host:', 'text', 'host'));
		$frm->AddInput(new FormInput('Database User:', 'text', 'user'));
		$frm->AddInput(new FormInput('Database Pass:', 'password', 'pass'));
		$frm->AddInput(new FormInput('Database:', 'text', 'data'));
		$frm->AddInput(new FormInput(null, 'submit', null, 'Configure'));
		return GetBox('box_config', 'Template Configuration', $frm->Get('method="post" action="{{me}}"'));
	}

	/**
	 * Gets the object before the last object on the stack.
	 * @return DisplayObject Destination for the last item.
	 */
	function &GetDestinationObject()
	{
		if (count($this->objs) > 1) return $this->objs[count($this->objs)-2];
		else return $tmp = &$this;
	}

	/**
	 * Gets the last object on the stack
	 * @return DisplayObject Current display object.
	 */
	function &GetCurrentObject()
	{
		if (count($this->objs) > 0) return $this->objs[count($this->objs)-1];
		else return $tmp = &$this;
	}

	/**
	 * Set a variable for use on this page.
	 * @param string $var Name of the variable.
	 * @param mixed $val Value of the variable.
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
	 * @param string $template The template file.
	 * @return string Rendered template.
	 */
	function Get($template)
	{
		if (!file_exists($template))
		{
			trigger_error("Template not found ({$template})", E_USER_ERROR);
			return NULL;
		}

		$this->data['template.stack'][] = $template;
		$this->template = $template;
		$ret = $this->GetString(file_get_contents($template));
		array_pop($this->data['template.stack']);
		return $ret;
	}

	function GetString($str)
	{
		if ($this->Behavior->MakeDynamic)
		{
			if (!file_exists('temp_cache')) mkdir('temp_cache');

			$md5 = md5($str);

			if (file_exists('temp_cache/'.$md5))
				$this->config = unserialize(file_get_contents('temp_cache/'.$md5));

			if (GetVar('ca') == 'template_config')
			{
				$this->config['db_host'] = GetVar('host');
				$this->config['db_user'] = GetVar('user');
				$this->config['db_pass'] = GetVar('pass');
				$this->config['db_data'] = GetVar('data');
				$fp = fopen('temp_cache/'.$md5, 'w');
				fwrite($fp, serialize($this->config));
				fclose($fp);
			}
		}

		$this->out = '';
		$this->parser = xml_parser_create_ns();
		$this->data['template.parsers'][] = $this->parser;
		$data = array();
		$index = array();
		xml_set_object($this->parser, $this);
		//IF we get weird characters, this helps, but ruins other pages. Very
		//interesting. - Nick
		//xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'ISO-8859-1');
		xml_set_element_handler($this->parser, 'Start_Tag', 'End_Tag');
		xml_set_character_data_handler($this->parser, 'CData');
 		xml_set_processing_instruction_handler($this->parser, 'Process');

		if (!xml_parse($this->parser, $str))
		{
			echo "XML Error: " . xml_error_string(xml_get_error_code($this->parser)) .
			" on line " . xml_get_current_line_number($this->parser) .
			" of file " . $template . "\n";
		}
		xml_parser_free($this->parser);
		array_pop($this->data['template.parsers']);
		return preg_replace_callback("/\{{([^}]+)\}}/", array($this, "parse_vars"), $this->start.$this->out);
	}

	/**
	 * Parse variables that have been set using set() to replace
	 * them in this template.
	 * @param array $match A regexp match.
	 * @return mixed Value of named var.
	 */
	function parse_vars($match)
	{
		$tvar = $match[1];
		global $$tvar;
		if (key_exists($tvar, $this->vars)) return $this->vars[$tvar];
		else if (isset($$tvar)) return $$tvar;
		else if (defined($tvar)) return constant($tvar);
		else if (isset($this->data[$tvar])) return $this->data[$tvar];
		else if ($this->use_getvar) return GetVar($tvar);
		return $this->Behavior->Bleed ? $match[0] : null;
	}
}

class TemplateBehavior
{
	public $MakeDynamic = false;
	public $Bleed = true;
}

/**
 * Enter description here...
 */
class VarParser
{
	/**
	 * Vars specified here override all else.
	 *
	 * @var array
	 */
	public $vars;

	/**
	 * Enter description here...
	 *
	 * @param string $data Data to search for variables.
	 * @param array $vars Override existing names with these.
	 * @return string Reformatted text with variables replaced.
	 */
	function ParseVars($data, $vars)
	{
		$this->vars = $vars;
		return preg_replace_callback("/\{{([^}]+)\}}/", array($this, 'var_parser'), $data);
	}

	/**
	 * Callback for each regex match, not for external use.
	 *
	 * @param array $match
	 * @return string
	 */
	function var_parser($match)
	{
		$tvar = $match[1];
		global $$tvar;

		//Process an array values from $this->vars
		if (is_array($this->vars) && isset($this->vars[$tvar]))
			return $this->vars[$tvar];
		//Process an object property from $this->vars
		if (is_object($this->vars))
		{
			$ov = get_object_vars($this->vars);
			if (isset($ov[$tvar])) return $ov[$tvar];
		}
		else if (isset($$tvar)) return $$tvar;
		else if (defined($tvar)) return constant($tvar);
		return $match[0];
	}
}

?>
