<?php

/**
 * Make a way to generate misc statistics.
 * Shortest doc comment.
 * Longest doc comment.
 * Largest class.
 * @package Code
 */

define('CODE_GET_NONE', 0);
define('CODE_GET_NAME', 1);
define('CODE_GET_DEFINE_NAME', 2);
define('CODE_GET_DEFINE_VALUE', 3);
define('CODE_GET_EXTENDS', 4);
define('CODE_GET_VERIFY_RETURN', 5);

define('T_DEFINE', 900);

/**
 * Returns the name of a specified token type.
 * @param int $type Token definition.
 * @return string
 */
function GetTypeName($type)
{
	switch ($type)
	{
		case T_FUNCTION: return 'function';
		case T_CLASS: return 'class';
		case T_VARIABLE: return 'variable';
		case T_DEFINE: return 'define';
		case T_PUBLIC: return 'public';
		case T_PRIVATE: return 'private';
		case T_PROTECTED: return 'protected';
		default: return 'unknown';
	}
}


/**
 * PHP source code reader.
 */
class CodeReader
{
	/**
	 * Current file being parsed.
	 * @var string
	 */
	private $file;

	/**
	 * Current line being parsed.
	 * @var int
	 */
	private $line;

	/**
	 * Current data parsed from a doc comment for the coming object.
	 * @var mixed
	 */
	private $curdoc;

	/**
	 * Type of item currently getting.
	 * @var int
	 */
	private $getting;

	/**
	 * Current package, changes as new packages are found.
	 * @var string
	 */
	private $curpackage;

	/**
	 * Current function / method we are working with.
	 * @var CodeObject
	 */
	private $curfunction;

	/**
	 * Line that the current function is defined on.
	 * @var int
	 */
	private $funcline;

	/**
	 * Array of name => class objects.
	 * @var array
	 */
	private $class_array;

	/**
	 * Whether or not to attempt to output a re-creation of the code structure.
	 * @var bool
	 */
	public $Refactor = false;

	/**
	 * Keywords that are allowed to be used as types by @var.
	 * @var array
	 */
	public $keywords = array(
		'array',
		'bool',
		'callback',
		'char',
		'int',
		'mixed',
		'resource',
		'string',
	);

	/**
	 * Processes a file and returns the structure of it as a CodeObject.
	 *
	 * @param string $filename
	 * @return CodeObject Code object containing all the information.
	 */
	function Parse($filename)
	{
		//Basic vars
		$this->curpackage = 'Default';
		$this->nametable = array();

		$this->file = $filename;
		$this->line = 0;

		$parsing = true;
		$this->tree = array();
		$tokens = token_get_all(file_get_contents($filename));

		$class = null;
		$doc = null;
		$this->getting = CODE_GET_NONE;
		$this->ret = null;
		$this->current = null;
		$this->modifier = 0;

		//Misc Stats
		$this->ret->misc['longest']['len'] = 0;

		foreach ($tokens as $tok)
		{
			//This is something getting returned.
			if ($this->getting == CODE_GET_VERIFY_RETURN && is_string($tok) && $tok == ';')
			{
				$this->curfunction->returning = true;
				$this->getting = $this->oget;
			}
			else if ($this->getting == CODE_GET_VERIFY_RETURN &&
				!isset($this->curfunction->doc->return))
			{
				$this->getting = $this->oget;
			}

			if (!is_array($tok))
			{
				switch ($tok)
				{
					case "'":
					case '"':
						$parsing = !$parsing;
						break;
				}
				if ($parsing)
				{
					switch ($tok)
					{
						case '(':
							array_push($this->tree, $this->current);
							break;
						case ')':
							$this->current = array_pop($this->tree);
							if (isset($this->current->type) &&
								$this->current->type == T_FUNCTION)
									$this->current = null;
							break;
						case '{':
							if ($this->Refactor) echo str_repeat('  ', count($this->tree))."{\n";
							array_push($this->tree, $this->current);

							//We're done with function arguments.
							if (@$this->current->type == T_FUNCTION)
							{
								$this->ProcEndFunction();
								$this->current = null;
							}
							break;
						case '}':
							$this->current = array_pop($this->tree);
							if ($this->Refactor) echo str_repeat('  ', count($this->tree))."}\n";
							break;
					}
				}
			}
			else if ($parsing)
			{
				$this->line = $tok[2];
				switch ($tok[0])
				{
					case T_PUBLIC:
					case T_PRIVATE:
					case T_PROTECTED: $this->modifier = $tok[0]; break;

					case T_VAR: $this->modifier = 0; break;

					case T_VARIABLE: $this->ProcVariable($tok); break;
					case T_FUNCTION: $this->ProcFunction($tok); break;
					case T_CLASS: $this->ProcClass($tok); break;

					case T_CONSTANT_ENCAPSED_STRING: $this->ProcConstant($tok); break;
					case T_CURLY_OPEN: $this->ProcCurlyOpen($tok); break;
					case T_DOC_COMMENT: $this->ProcDocComment($tok); break;
					case T_EXTENDS: $this->getting = CODE_GET_EXTENDS; break;
					case T_LNUMBER: $this->ProcNumber($tok); break;
					case T_RETURN: $this->ProcReturn($tok); break;
					case T_STRING: $this->ProcString($tok); break;
					default: break;
				}
			}
		}

		if (isset($this->ret)) $this->ret->file = $filename;
		return array('names' => $this->nametable, 'data' => $this->ret);
	}

	/**
	 * Verify all types and such.
	 * @param array $data Context data.
	 * @param array $names Current nametable.
	 */
	function Validate($data, $names)
	{
		foreach ($data->members as $name => $co)
		{
			$this->ValidateRecurse($data, $names, $co);
		}
	}

	/**
	 * @param array $data Context data.
	 * @param array $names Current nametable.
	 * @param CodeObject $member Current member we are iterating.
	 */
	function ValidateRecurse($data, $names, $member)
	{
		if ($member->type == T_FUNCTION)
		{
			$file = $member->file;
			$line = $member->line;

			//Check Return
			if (!empty($member->doc->return))
			{
				if (!$member->returning)
					echo "[DOC]: Return tag specified without".
					" returning a value for ".$this->GetName($member)."\n";
			}
			if (@$member->doc->return == 0)
			{
				//Method, may be overloaded.
				if (isset($member->parent) &&
					$member->parent->type == T_CLASS)
				{
					$ol = $member;
					do
					{
						if (!empty($ol->doc->return))
						{
							$member->doc->return = $ol->doc->return;
							$file = $ol->file;
							$line = $ol->line;
							break;
						}
					}
					while ($ol = $this->GetOverload($data, $ol));
				}
			}

			if (!empty($member->doc->return))
			{
				if ($member->doc->return == 0)
				{
					echo "[DOC]: Missing @return tag for ".
					$this->GetName($member)."\n";
				}

				$t = $member->doc->return['type'];
				if (!$this->VerifyType($names, $t))
				{
					echo "[DOC]: Invalid return type '{$t}' for ".
					$this->GetName($member)."\n";
				}
			}

			//Check params
			if (isset($member->doc->params))
			foreach ($member->doc->params as $name => $param)
			{
				if (empty($param))
				{
					//Method, may be overloaded.
					if (@$member->parent->type == T_CLASS)
					{
						$ol = $member;
						do
						{
							if (!empty($ol->doc->params[$name]))
							{
								$param = $member->doc->params[$name] = $ol->doc->params[$name];
								break;
							}
						}
						while ($ol = $this->GetOverload($data, $ol));
					}

					if (empty($param))
						echo "[DOC]: Argument '$name' not documented for ".
						$this->GetName($member)."\n";
				}

				if (!empty($param))
				{
					if (!isset($member->members[$name]))
						echo "[DOC]: No such argument '{$name}' ".
						$this->GetName($member)."\n";
					$t = $member->doc->params[$name]['type'];
					if (!$this->VerifyType($names, $t))
						echo "[DOC]: No such type '{$t}' ".
						$this->GetName($member)."\n";
				}
			}
		}
		else if ($member->type == T_VARIABLE)
		{
			if (!isset($member->doc->type))
				echo "[DOC]: Missing @var tag for ".
				$this->GetName($member)."\n";

			$t = @$member->doc->type;
			if (!$this->VerifyType($names, $t))
				echo "[DOC]: No such type '{$t}' for ".
				$this->GetName($member)."\n";
		}
		else if ($member->type == T_CLASS)
		{
			//Check properties and methods
			foreach ($member->members as $c)
			{
				$this->ValidateRecurse($data, $names, $c);
			}
		}
	}

	/**
	 * Processes a return keyword.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcReturn($tok)
	{
		$this->oget = $this->getting;
		$this->getting = CODE_GET_VERIFY_RETURN;
	}

	/**
	 * Processes the related function when the curly bracket is terminated.
	 */
	function ProcEndFunction()
	{
	}

	/**
	 * Processes a constant, mainly defines.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcConstant($tok)
	{
		if ($this->getting == CODE_GET_DEFINE_VALUE)
		{
			$this->current->value = str_replace('"', '',
				str_replace("'", '', $tok[1]));
			$this->current = array_pop($this->tree);
			$this->getting = CODE_GET_NONE;
		}
		if ($this->getting == CODE_GET_DEFINE_NAME)
		{
			array_push($this->tree, $this->current);
			$this->current = new CodeObject(T_DEFINE);
			$this->current->file = $this->file;
			$this->current->line = $this->line;
			$this->current->name = str_replace('"', '',
				str_replace("'", '', $tok[1]));
			@$this->current->doc->package = $this->curpackage;
			$this->ret->members[$this->current->name] = $this->current;
			$this->getting = CODE_GET_DEFINE_VALUE;
		}
	}

	/**
	 * Processes an opening curly brace.
	 */
	function ProcCurlyOpen()
	{
		if ($this->Refactor) echo str_repeat('  ', count($this->tree))."{\n";
		array_push($this->tree, $this->current);
	}

	/**
	 * Processes an evaluated function.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcFunction($tok)
	{
		$this->current = new CodeObject($tok[0]);
		$this->current->file = $this->file;
		$this->current->line = $this->line;
		$this->current->doc = $this->curdoc;
		@$this->current->doc->package = $this->curpackage;
		$this->current->returning = false;

		$this->curfunction = $this->current;
		$this->funcline = $this->line;

		$this->curdoc = null;

		$this->getting = CODE_GET_NAME;
	}

	/**
	 * Process an evaluated class.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcClass($tok)
	{
		$this->getting = CODE_GET_NAME;
		$this->current = new CodeObject($tok[0]);
		$this->current->file = $this->file;
		$this->current->line = $this->line;
		$this->current->doc = $this->curdoc;
		@$this->current->doc->package = $this->curpackage;
		$this->curdoc = null;
	}

	/**
	 * Process an evaluated string.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcString($tok)
	{
		if ($this->getting == CODE_GET_DEFINE_VALUE)
		{
			$this->current->value = $tok[1];
			$this->current = array_pop($this->tree);
			$this->getting = CODE_GET_NONE;
		}
		if ($this->getting == CODE_GET_NONE &&
			strtolower($tok[1]) == 'define')
		{
			$this->getting = CODE_GET_DEFINE_NAME;
		}
		if ($this->getting == CODE_GET_EXTENDS)
		{
			$this->current->extends = $tok[1];
			$this->getting = CODE_GET_NONE;
		}
		if ($this->getting == CODE_GET_NAME)
		{
			$this->current->name = $tok[1];
			$this->nametable[$tok[1]] = 1;

			if (isset($this->setnext))
			{
				$this->setnext = $this->GetName($this->current);
				unset($this->setnext);
			}

			if ($this->current->type == T_FUNCTION)
			{
				if (count($this->tree) == 1) //Method
				{
					$parent = array_get($this->tree);
					$this->current->parent = $parent;
					$parent->members[$tok[1]] = $this->current;
				}
				else //Function
					$this->ret->members[$tok[1]] = $this->current;
			}
			if ($this->current->type == T_CLASS)
			{
				$this->ret->members[$tok[1]] = $this->current;
			}
			$this->getting = CODE_GET_NONE;
		}
	}

	/**
	 * Process an evaluated document comment.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcDocComment($tok)
	{
		$this->curdoc = new stdClass();

		//I have no clue how the hell I pulled this off, but it works amazingly.
		//Suggest not changing it cause it's really really tweaked in there.
		$split = preg_split('/\n\s*\* @/s', $tok[1], 0, 4);
		$this->curdoc->body = trim(preg_replace('#\s*/*\*+\s*\n*#s', ' ',
			$split[0][0]));

		if (!empty($this->curdoc->body))
		{
			if (strlen($this->curdoc->body) > $this->ret->misc['longest']['len'])
			{
				$this->ret->misc['longest']['len'] = strlen($this->curdoc->body);
				$this->ret->misc['longest']['name'] = '';
				$this->setnext = &$this->ret->misc['longest']['name'];
			}
		}
		if (!empty($this->curdoc->body))
		{
			if (!isset($this->ret->misc['shortest']['len']))
			{
				$this->ret->misc['shortest']['len'] = strlen($this->curdoc->body);
				$this->ret->misc['shortest']['name'] = '';
				$this->setnext = &$this->ret->misc['shortest']['name'];
			}
			else if (strlen($this->curdoc->body) < $this->ret->misc['shortest']['len'])
			{
				$this->ret->misc['shortest']['len'] = strlen($this->curdoc->body);
				$this->ret->misc['shortest']['name'] = '';
				$this->setnext = &$this->ret->misc['shortest']['name'];
			}
		}

		for ($ix = 1; $ix < count($split); $ix++)
		{
			$clean = preg_replace('#\s*\*/*\n*#s', '', $split[$ix][0]);

			if (preg_match('/example ([^\s]+)/', $clean, $m))
			{
					if (!file_exists($m[1]) || !is_file($m[1]))
					{
						Error("File does not exist for example tag: ".
							"{$m[1]} (".$item->file.':'.$item->line.')');
						continue;
					}
					$data = highlight_file($m[1], true);
					$this->curdoc->example = $data;
			}
			else if (preg_match("/param ([^\s]+) ([^\s]+)(.*)/", $clean, $m))
			{
					$this->curdoc->params[$m[2]]['type'] = $m[1];
					$this->curdoc->params[$m[2]]['desc'] = substr($m[3], 1);
			}
			else if (preg_match('/return ([^\s]+)([^\n]*)/', $clean, $m))
				$this->curdoc->return = array('type' => $m[1], 'desc' => $m[2]);
			else if (preg_match('/var ([^\s]+)/', $clean, $m))
				$this->curdoc->type = $m[1];
			else if (preg_match('/package (.+)/', $clean, $m))
				$this->curpackage = $m[1];
			else if (preg_match('/todo (.+)/', $clean, $m))
				$this->todos[] = array($this->file, $this->line, $m[1]);
			else if (preg_match('/access (.+)/', $clean, $m))
				$this->curdoc->access = $m[1];
			else if (preg_match('/see (.+)/', $clean, $m))
				$this->curdoc->see[] = $m[1];
			else if (preg_match('/deprecated/', $clean))
				$this->todos[] = array($this->file, $this->line, "You should"
					." probably eliminate this item soon, it's marked"
					." depricated.");
			else if (preg_match('/version (.+)/', $clean, $m))
				$this->curdoc->version = $m[1];
			else if (preg_match('/since (.+)/', $clean, $m))
				$this->curdoc->since = $m[1];
			else if (preg_match('/([^\s]+)(.*)/', $clean, $m))
			{
				echo "Unknown doc tag: {$m[1]}\n";
				$elTag = $this->current->doc->tags[$m[1]] =
						isset($m[2]) ? $m[2] : null;
			}
		}
	}

	/**
	 * @param CodeObject $member Code object to find.
	 * @return string Name and location of $member.
	 */
	function GetName($member)
	{
		$ret = null;
		if (isset($member))
		{
			if (@$member->parent->type == T_CLASS)
				$ret =  "{$member->parent->name}::";
			else if ($member->type == T_CLASS) $ret = 'class ';
			else if ($member->type == T_FUNCTION) $ret = 'function ';
			else if ($member->type == T_VARIABLE) $ret = 'variable ';
			else echo "What're you trying to GetName on? {$member->name}\n";
			$ret .= "{$member->name} at <b>{$member->file}:{$member->line}</b>";
		}
		return $ret;
	}

	/**
	 * Verifies if a given type exists.
	 * @param array $names Current nametable.
	 * @param string $type Name of type to verify.
	 * @return bool True if the type exists, otherwise false.
	 */
	function VerifyType($names, $type)
	{
		global $keywords;
		if (defined($type)
		|| in_array($type, $this->keywords)
		|| in_array($type, $names)
		|| class_exists($type))
			return true;
		return false;
	}

	/**
	 * Process an evaluated variable.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcVariable($tok)
	{
		$d = new CodeObject(T_VARIABLE);
		$d->file = $this->file;
		$d->line = $this->line;
		$d->name = $tok[1];
		$d->doc = $this->curdoc;
		@$d->doc->package = $this->curpackage;
		$this->curdoc = null;

		if (isset($this->setnext))
		{
			$this->setnext = $this->GetName($d);
			unset($this->setnext);
		}

		//Function / Method Argument
		if (@$this->current->type == T_FUNCTION)
		{
			//Doc doesn't exist, can't find inhereted docs either.
			if (!isset($this->current->doc->params[$tok[1]]))
			{
				//Not documented, prepare it for validation later.
				$this->current->doc->params[$tok[1]] = 0;
			}
			$d->parent = $this->current;
			$this->current->members[$tok[1]] = $d;
		}

		//Member Variable
		else if (count($this->tree) == 1 && @$this->current->type == T_CLASS)
		{
			$d->parent = $this->current;
			$d->modifier = $this->modifier;
			$this->current->members[$tok[1]] = $d;
		}

		//Global Variable
		else if (empty($this->tree))
		{
			$d->parent = $this->ret;
			$this->ret->members[$tok[1]] = $d;
		}
	}

	/**
	 * Process an evaluated number.
	 * @param mixed $tok Token to be evaluated.
	 */
	function ProcNumber($tok)
	{
		if ($this->getting == CODE_GET_DEFINE_VALUE)
		{
			$this->current->value = str_replace('"', '',
				str_replace("'", '', $tok[1]));
			$this->current = array_pop($this->tree);
			$this->getting = CODE_GET_NONE;
		}
	}

	/**
	 * Returns the next level of possible overload.
	 * @param array $data Context data.
	 * @param CodeObject $obj Object to find overload for.
	 * @return CodeObject Overloaded code object.
	 */
	function GetOverload($data, $obj)
	{
		if (!isset($obj)) return;
		if ($obj->type == T_FUNCTION)
		{
			//Method
			if (isset($obj->parent) && $obj->parent->type == T_CLASS)
			{
				if (isset($obj->parent->extends))
				{
					$target = $data->members[$obj->parent->extends];
					if (isset($target->members[$obj->name]))
					{
						return $target->members[$obj->name];
					}
				}
			}
		}
	}
}

class CodeObject
{
	/**
	 * Type of this object.
	 *
	 * @var int
	 */
	public $type;

	/**
	 * Name of this object.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Array of children that this object holds.
	 *
	 * @var array
	 */
	public $members;

	/**
	 * Parent of this object.
	 *
	 * @var CodeObject
	 */
	public $parent;

	/**
	 * @var stdClass
	 */
	public $doc;

	/**
	 * Creates a new document object.
	 * @param int $type
	 */
	function CodeObject($type)
	{
		$this->type = $type;
	}

}

?>