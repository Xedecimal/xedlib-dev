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
 * Requires $file and creates a new $class of type DisplayObject to prepare and
 * present.
 *
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

	$mod = new $class();
	$data['includes'][$class] = $mod;
	$mod->Prepare($data);
	return $mod;
}

require_once('h_display.php');

/**
 * A template
 */
class Template extends LayeredOutput
{
	/**
	 * Variables that have been set with set().
	 *
	 * @var array
	 */
	public $vars;

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
	 * If true, we're the parser is not processing the normal things.
	 *
	 * @var bool
	 */
	private $skip;

	/**
	 * Behavioral configuration for this template reader.
	 *
	 * @var TemplateBehavior
	 */
	public $Behavior;

	//private $config;

	/**
	 * Output that will be sent before the contents of this template.
	 *
	 * @var string
	 */
	private $start = '';

	/**
	 * Creates a new template parser.
	 */
	function Template()
	{
		$this->Behavior = new TemplateBehavior();
		$args = func_get_args();
		if (isset($args[0]))
		{
			$data = &$args[0];
			$this->data = &$data;

			// Handle Global Rewrites
			if (!empty($data['rewrites']))
			foreach ($data['rewrites'] as $rw) $this->ReWrite($rw[0], $rw[1]);
		}
		$this->objs = array();
		$this->vars = array();
		$this->use_getvar = false;
		$this->vars['relpath'] = GetRelativePath(dirname(__FILE__));

		if (!empty($GLOBALS['_d']['template.rewrites']))
			foreach ($GLOBALS['_d']['template.rewrites'] as $tag => $rw)
			{
				if (is_array($rw))
					$this->ReWrite($tag, array_slice($rw, 0, 2),
						array_slice($rw, 2));
				else
					$this->ReWrite($tag, $rw);
			}

		if (!empty($GLOBALS['_d']['template.transforms']))
			foreach ($GLOBALS['_d']['template.transforms'] as $tag => $tf)
				$this->Transform($tag, array_slice($tf, 0, 2), array_slice($tf, 2));

		$this->ReWrite('template', array(&$this, 'TagTemplate'));
		$this->ReWrite('repeat', array(&$this, 'TagRepeat'));
		$this->ReWrite('callback', array(&$this, 'TagCallback'));
		parent::__construct();
	}

	/**
	 * Internal use for rewriting <template> tags and mainly fixing headers.
	 *
	 * @param Template $t Associated template.
	 * @param string $guts Guts of the tag not including the actual tag.
	 * @param array $attribs Attributes of the rewritten tag.
	 * @return string Rendered html output of the target template.
	 */
	function TagTemplate($t, $g, $a)
	{
		if (isset($a["FILE"]))
		{
			return $t->ParseFile($a['FILE']);
		}
	}

	function TagRepeat($t, $g, $a)
	{
		if (empty($a['ON'])) return;
		$target = $this->FindVar($a['ON']);
		$vp = new VarParser();
		$ret = '';
		foreach ($target as $k => $v)
		{
			$ret .= $vp->ParseVars($g, $v);
		}
		return $ret;
	}

	function TagCallback($t, $g, $a)
	{
		$ds = @$GLOBALS[$a['DS']];
		return RunCallbacks(@$ds[$a['NAME']]);
	}

	/**
	 * Rewrites a tag including opening and closing tags based on one or more
	 * callbacks.
	 *
	 * @param string $tag Tag to rewrite.
	 * @param mixed $callback Callback(s) to be called.
	 * @param array $args Arguments that will be passed to the callback.
	 */
	function ReWrite($tag, $callback, $args = null)
	{
		$this->rewrites[strtoupper($tag)][] = $callback;
		$this->rewriteargs[strtoupper($tag)] = $args;
	}

	/**
	 * This function will transform an existing tag and/or attributes into
	 * another one.
	 *
	 * @param string $tag Tag to translate.
	 * @param mixed $callback The callback to be called on this tag.
	 */
	function Transform($tag, $callback, $args = null)
	{
		$this->transforms[strtoupper($tag)][] = $callback;
		$this->transformargs[strtoupper($tag)] = $args;
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
			$ret = RunCallbacks($this->transforms[$tag], $attribs, $this->transformargs[$tag]);
			$attribs = array_merge($attribs, $ret);
		}

		if (isset($this->rewrites[$tag]))
		{
			$obj = new LayeredOutput();
			$obj->attribs = $attribs;
			$obj->tag = $tag;
			$this->Push($obj);
			$show = false;
		}
		else $show = true;
		$close = '';

		$output = '';

		if ($tag == 'AMP') $output = '&amp;';
		else if ($tag == 'BOX')
		{
			require_once('h_display.php');
			if (isset($attribs["HANDLER"]))
			{
				$handler = $attribs["HANDLER"];
				if (file_exists("$handler.php")) require_once("$handler.php");
				if (class_exists($handler)) $box = new $handler;
				else die("Class does not exist ($handler).\n");
			}
			else $box = new Box();
			if (isset($attribs['PREFIX'])) $box->prefix = $attribs['PREFIX'];
			if (isset($attribs['TITLE'])) $box->title = $attribs['TITLE'];
			if (isset($attribs['TEMPLATE'])) $box->template = $attribs['TEMPLATE'];
			if (isset($attribs['ID'])) $box->name = $attribs['ID'];
			$this->Push($box);
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
					$this->Out('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">');
				if ($attribs['TYPE'] == 'trans')
					$this->Out('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">');
			}
		}
		else if ($tag == 'FORM' && $this->Behavior->MakeDynamic)
			$this->start .= $this->ProcessForm($parser, $tag, $attribs);

		if ($tag == 'HTML')
		{
			if (!empty($attribs['DOCTYPE']))
			{
				if ($attribs['DOCTYPE'] == 'strict')
					$this->Out('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">');
				if ($attribs['DOCTYPE'] == 'trans')
					$this->Out('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
						"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">');
			}
			unset($attribs['DOCTYPE']);
			$attribs['XMLNS'] = 'http://www.w3.org/1999/xhtml';
		}

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
			if (!empty($attribs['CLASS']))
			{
				$class = $attribs['CLASS'];
				RequireModule($this->data, $file, $class);
				$show = false;
			}
			else if (file_exists($file)) $output = stripslashes(file_get_contents($file));
		}
		else if ($tag == 'INPUT')
		{
			if ($this->Behavior->MakeDynamic)
				$this->Out($this->ProcessInput($parser, $tag, $attribs));
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
			$this->data['includes'][$name]->attribs = $attribs;
			$this->Push($this->data['includes'][$name]);
			$show = false;
		}
		else if ($tag == "XFORM")
		{
			if (isset($attribs["NAME"])) $name = $attribs["NAME"];
			else $name = "formUnnamed";
			$form = new Form($name);
			foreach ($attribs as $name => $val) $form->attribs[$name] = $val;
			$this->Push($form);
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
			if (strlen($output) > 0) $obj->Out($output);
			else
			{
				$obj->Out('<'.strtolower($tag));
				foreach ($attribs as $name => $val)
					$obj->Out(' '.strtolower($name).'="'.$val.'"');
				$obj->Out("{$close}>");
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
			$obj = $this->Pop();
			$objd = &$this->GetCurrentObject();

			$vp = new VarParser();
			foreach ($this->rewrites[$tag] as $rw)

			$atrs = $vp->ParseVars($obj->attribs, $this->vars);
			if (empty($atrs)) $atrs = array();

			$objd->Out(call_user_func($rw, $this, $obj->Get(), $atrs,
				$obj->tag, $this->rewriteargs[$tag]));

			return;
		}

		if ($tag == 'AMP') return;
		else if ($tag == 'BOX' || $tag == 'XFORM')
		{
			$objc = &$this->GetCurrentObject();
			$objd = &$this->GetDestinationObject();
			$objd->Out($objc->Get());
			$this->Pop();
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
			$objd->Out($objc->Get($this->data));
			$this->Pop();
		}
		else
		{
			$obj = &$this->GetCurrentObject();
			$obj->Out('</'.strtolower($tag).'>');
		}
	}

	function Push(&$obj) { $this->objs[] = $obj; }
	function Pop() { return array_pop($this->objs); }

	/**
	 * Parse Character Data for an xml parser.
	 * @param resource $parser Xml parser for current document.
	 * @param string $text Actual Character Data.
	 */
	function CData($parser, $text)
	{
		if ($this->skip) return;
		$obj = &$this->GetCurrentObject();
		$obj->Out($text);
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
		$obj->Out(ob_get_contents());
		ob_end_clean();
	}

	/**
	 * Gets the object before the last object on the stack.
	 * @return DisplayObject Destination for the last item.
	 */
	function &GetDestinationObject()
	{
		if (count($this->objs) > 1) return $this->objs[count($this->objs)-2];
		else { $tmp = &$this; return $tmp; }
	}

	/**
	 * Gets the last object on the stack
	 * @return DisplayObject Current display object.
	 */
	function &GetCurrentObject()
	{
		if (count($this->objs) > 0) { return $this->objs[count($this->objs)-1]; }
		else { $tmp = &$this; return $tmp; }
	}

	/**
	 * Set a variable for use on this page.
	 * @param string $var Name of the variable.
	 * @param mixed $val Value of the variable.
	 */
	function Set($var, $val = null)
	{
		if (is_array($var))
		{
			$this->vars = array_merge($this->vars, $var);
		}
		else if (@get_class($var) != null)
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
	function ParseFile($template)
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

	function Get()
	{
		Error('Please do not call Get on Template.');
	}

	/**
	 * Processes an entire template as string, good for file_get_contents().
	 *
	 * @param string $str Data to be processed.
	 * @return string Processed end result.
	 */
	function GetString($str)
	{
		$nstr = $this->PreProcess($str);

		if ($this->Behavior->MakeDynamic)
		{
			if (!file_exists('temp_cache')) mkdir('temp_cache');

			$md5 = md5($nstr);

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

		$this->CreateBuffer(); //Create a layer of output.

		$p = xml_parser_create();
		$this->data['template.parsers'][] = &$p;

		xml_set_object($p, $this);
		xml_set_element_handler($p, 'Start_Tag', 'End_Tag');
		xml_set_character_data_handler($p, 'CData');
		xml_set_default_handler($p, 'def');
		xml_set_processing_instruction_handler($p, 'Process');
		xml_parser_set_option($p, XML_OPTION_TARGET_ENCODING, 'UTF-8');

		if (!xml_parse($p, $nstr))
		{
			$err = "XML Error: " . xml_error_string(xml_get_error_code($p)) .
			" on line " . xml_get_current_line_number($p);
			if (!empty($this->template)) $err .= " of file " . $this->template;
			else
			{
				$err .= "<br/>Inside the following template ...<br/>\n";
				$err .= varinfo($str, true);
			}
			$err .= "<br/>\n";
			Error($err);
		}
		xml_parser_free($p);

		array_pop($this->data['template.parsers']);

		return $this->ProcessVars($this->FlushBuffer());
	}

	function ProcessVars($str)
	{
		$vp = new VarParser;
		$vp->Behavior->Bleed = $this->Behavior->Bleed;
		$vp->Behavior->UseGetVar = $this->use_getvar;
		return $vp->ParseVars($str, array_merge($this->data, $this->vars));
	}

	function def($p, $g)
	{
		if (substr($g, 0, 4) == '<!--')
		{
			$obj = &$this->GetCurrentObject();
			$obj->Out($g);
		}
	}

	/**
	 * Processes possible php code in a template.
	 *
	 * @param string $str String to process php code in.
	 * @return string Replaced information.
	 */
	function PreProcess($str)
	{
		$nret = preg_replace_callback('/(<\?php|<\?)(.+?)\?>/s', array(&$this, 'CodeCallback'), $str);
		return str_replace('&', '&amp;', $nret);
	}

	/**
	 * Internal Use
	 *
	 * @param array $m preg_replace_callback default argument.
	 * @return string Replacement string.
	 */
	function CodeCallback($m)
	{
		ob_start();
		eval($m[2]);
		return ob_get_clean();
	}
}

class TemplateBehavior
{
	/**
	 * I forgot what this does.
	 * @var bool
	 */
	public $MakeDynamic = false;

	/**
	 * Whether variables in the data {{example}} will bleed through if null.
	 *
	 * @var bool
	 */
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

	public $Behavior;

	function __construct()
	{
		$this->Behavior = new VarParserBehavior;
	}

	/**
	 * Processes variables in the given string $data for variables named as keys
	 * in the array $vars.
	 *
	 * @param string $data Data to search for variables.
	 * @param array $vars Override existing names with these.
	 * @return mixed Reformatted text with variables replaced.
	 */
	function ParseVars($data, $vars = null)
	{
		if (empty($data)) return '';
		$this->vars = $vars;
		return preg_replace_callback('/\{\{([^\}]+)\}\}/',
			array(&$this, 'var_parser'), $data);
	}

	/**
	 * Callback for each regex match, not for external use.
	 *
	 * @param array $match Matches found by preg_replace_callback calling this.
	 * @return string
	 */
	function var_parser($match)
	{
		/*$tvar = $match[1];

		$ret = null;

		//Process an array values from $this->vars
		if (is_array($this->vars) && isset($this->vars[$tvar]))
			$ret = $this->vars[$tvar];
		//Process an object property from $this->vars
		else if (is_object($this->vars))
		{
			$ov = get_object_vars($this->vars);
			if (isset($ov[$tvar])) $ret = $ov[$tvar];
		}
		else if (isset($GLOBALS[$tvar])) $ret = $GLOBALS[$tvar];
		else if (defined($tvar)) $ret = constant($tvar);
		else $ret = $this->Bleed ? $match[0] : null;

		return $ret;*/

		$tvar = $match[1];

		// This is an advanced variable.
		if (strpos($tvar, '.'))
		{
			$indices = explode('.', $tvar);
			$var = $this->FindVar($indices[0]);

			for ($ix = 1; $ix < count($indices); $ix++)
			{
				if (is_object($var)) $var = $var->$indices[$ix];
				else if (isset($var[$indices[$ix]])) $var = $var[$indices[$ix]];
			}

			if (!is_array($var)) return $var;
		}
		$ret = $this->FindVar($tvar);
		if (isset($ret)) return $ret;
		return $this->Behavior->Bleed ? $match[0] : $ret;
	}

	function FindVar($tvar)
	{
		global $$tvar;
		if (!empty($this->vars) && key_exists($tvar, $this->vars))
			return $this->vars[$tvar];
		else if (isset($$tvar)) return $$tvar;
		else if (defined($tvar)) return constant($tvar);
		else if (isset($this->data[$tvar])) return $this->data[$tvar];
		else if ($this->Behavior->UseGetVar) return GetVar($tvar);
		return null;
	}
}

class VarParserBehavior
{
	/**
	 * Whether variables in the template {{example}} will bleed through if not
	 * set.
	 *
	 * @var bool
	 */
	public $Bleed = true;

	public $UseGetVar = false;
}

function TagEmpty($t, $g, $a)
{
	if (empty($GLOBALS[$a['VAR']]) && empty($t->vars[$a['VAR']])) return $g;
}

function TagNotEmpty($t, $g, $a)
{
	if (!empty($GLOBALS[$a['VAR']])) return $g;
	if (!empty($t->vars[$a['VAR']])) return $g;
}

function TagRelativeDate($t, $g)
{
	return GetDateOffset(strtotime($t->ProcessVars($g)));
}

function TagPassthrough($t, $g)
{
	return $g;
}

function TagLoop($t, $g, $a)
{
	$vp = new VarParser;
	$ret = null;
	for ($ix = $a['START']; $ix <= $a['END']; $ix++)
	{
		$ret .= $vp->ParseVars($g, array($a['VAR'] => $ix));
	}
	return $ret;
}

function TagEach($t, $g, $a)
{
	$vp = new VarParser;
	$vp->Behavior->UseGetVar = true;
	$ret = null;
	$dat = GetVar($a['VAR']);
	for ($ix = $a['START']; $ix < count($dat); $ix++)
		$ret .= $vp->ParseVars(preg_replace("/{$a['IDX']}/i", $ix, $g));
	return $ret;
}

function TagUpper($t, $g, $a) { return strtoupper($g); }

?>
