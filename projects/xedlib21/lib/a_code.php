<?php

define('CODE_GET_NONE', 0);
define('CODE_GET_NAME', 1);
define('CODE_GET_DEFINE_NAME', 2);
define('CODE_GET_DEFINE_VALUE', 3);
define('CODE_GET_EXTENDS', 4);

define('T_DEFINE', 900);

/**
 * PHP source code reader.
 *
 */
class CodeReader
{
	private $file;
	private $line;
	private $curdoc;
	private $getting;

	/**
	 * Processes a file and returns the structure of it as a CodeObject.
	 *
	 * @param string $filename
	 * @return CodeObject
	 */
	function Parse($filename)
	{
		$this->file = $filename;

		$parsing = true;
		$this->tree = array();
		$tokens = token_get_all(file_get_contents($filename));

		$class = null;
		$doc = null;
		$this->getting = CODE_GET_NONE;
		$this->ret = null;
		$this->current = null;
		$this->modifier = 0;
		$line = 1;

		foreach ($tokens as $tok)
		{
			$this->MoveCaret($tok);

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
						case '{':
							Trace(str_repeat("\t",
								count($this->tree))."{$tok}\n");
							array_push($this->tree, $this->current);

							//We're done with function arguments.
							if (isset($this->current) &&
								$this->current->type == T_FUNCTION)
								$this->current = null;
							break;
						case '}':
							$this->current = array_pop($this->tree);
							Trace(str_repeat("\t",
								count($this->tree))."{$tok}\n");
							break;
					}
				}
			}
			else if ($parsing)
			{
				if ($tok[0] == T_PUBLIC) $this->modifier = T_PUBLIC;
				if ($tok[0] == T_PRIVATE) $this->modifier = T_PRIVATE;
				if ($tok[0] == T_PROTECTED) $this->modifier = T_PROTECTED;
				if ($tok[0] == T_VAR) $this->modifier = 0;
				if ($tok[0] == T_CONSTANT_ENCAPSED_STRING)
					$this->ProcConstant($tok);
				if ($tok[0] == T_CURLY_OPEN) $this->ProcCurlyOpen($tok);
				if ($tok[0] == T_FUNCTION) $this->ProcFunction($tok);
				if ($tok[0] == T_CLASS) $this->ProcClass($tok);
				if ($tok[0] == T_STRING) $this->ProcString($tok);
				if ($tok[0] == T_DOC_COMMENT) $this->ProcDocComment($tok);
				if ($tok[0] == T_VARIABLE) $this->ProcVariable($tok);
				if ($tok[0] == T_LNUMBER) $this->ProcNumber($tok);
				if ($tok[0] == T_EXTENDS) $this->getting = CODE_GET_EXTENDS;
			}
		}

		if (isset($this->ret)) $this->ret->file = $filename;
		return $this->ret;
	}

	/**
	 * Moves the internal caret position for locating the token later.
	 *
	 * @param mixed $token
	 * @param int $line
	 */
	function MoveCaret($token)
	{
		if (is_array($token)) $this->line += substr_count($token[1], "\n");
		else $this->line += substr_count($token, "\n");
	}

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
			$this->current->filename = $this->file;
			$this->ret->members[$this->current->name] = $this->current;
			$this->getting = CODE_GET_DEFINE_VALUE;
		}
	}

	function ProcessDocComment($data)
	{
	}

	function ProcCurlyOpen()
	{
		Trace(str_repeat("\t", count($this->tree))."{\n");
		array_push($this->tree, $this->current);
	}

	function ProcFunction($tok)
	{
		Trace(str_repeat("\t", count($this->tree))."function ");
		$this->current = new CodeObject($tok[0]);
		$this->current->file = $this->file;
		$this->current->line = $this->line;
		$this->current->doc = $this->curdoc;
		$this->curdoc = new stdClass();
		$this->getting = CODE_GET_NAME;
	}

	function ProcClass($tok)
	{
		Trace("class ");
		$this->getting = CODE_GET_NAME;
		$this->current = new CodeObject($tok[0]);
		$this->current->file = $this->file;
		$this->current->line = $this->line;
		$this->current->doc = $this->curdoc;
		$this->curdoc = new stdClass();
	}

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
			if ($this->current->type == T_FUNCTION)
			{
				if (count($this->tree) == 1)
				{
					$parent = array_get($this->tree);
					$this->current->parent = $parent;
					$parent->members[$tok[1]] = $this->current;
				}
				else $this->ret->members[$tok[1]] = $this->current;
			}
			if ($this->current->type == T_CLASS)
			{
				$this->ret->members[$tok[1]] = $this->current;
			}
			$this->getting = CODE_GET_NONE;
			Trace($tok[1]."\n");
		}
	}

	function ProcDocComment($tok)
	{
		$this->curdoc = new stdClass();

		preg_match_all("#^[ \t*/]*(.*)[/]*$#m", $tok[1], $matches);
		foreach ($matches[1] as $line)
		{
			$line = chop($line);
			if (strlen($line) < 1) continue;
			//Tag
			if (preg_match('#@(.+)#', $line, $match))
			{
				$tag = $match[1];
				if (preg_match('#example (.+)#', $tag, $match))
				{
					if (!file_exists($match[1]) || !is_file($match[1]))
					{
						Error("File does not exist for example tag: ".
							"{$match[1]} (".$item->file.':'.$item->line.')');
						continue;
					}
					$data = highlight_file($match[1], true);
					$this->current->doc->example = $data;
				}
				else if (preg_match('#param ([^ ]+) ([^ ]+)(.*)#', $tag, $match))
				{
					$this->curdoc->params[$match[2]] =
						array($match[1], $match[3]);
				}
				else if (preg_match('#return ([^ ]+)(.*)#', $line, $match))
				{
					$this->curdoc->return = array($match[1], $match[2]);
				}
				else
				{
					$elTag = $this->current->doc->tags[$tag] =
						isset($match[2]) ? $match[2] : null;
				}
			}
			else
			{
				$this->curdoc->body .= $line;
			}
		}

	}

	function ProcVariable($tok)
	{
		$d = new CodeObject(T_VARIABLE);
		$d->file = $this->file;
		$d->line = $this->line;
		$d->name = $tok[1];
		$d->doc = $this->curdoc;
		$this->curdoc = new stdClass();

		//Argument
		if (isset($this->current) && $this->current->type == T_FUNCTION)
		{
			$d->parent = $this->current;
			$this->current->members[$tok[1]] = $d;
		}

		//Member
		else if (count($this->tree) == 1
			&& $this->tree[count($this->tree)-1] != null
			&& $this->tree[count($this->tree)-1]->type == T_CLASS)
		{
			$d->parent = $this->current;
			$d->modifier = $this->modifier;
			$this->current->members[$tok[1]] = $d;
		}

		//Global
		else if (empty($this->tree))
		{
			$d->parent = $this->ret;
			$this->ret->members[$tok[1]] = $d;
		}
	}

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
	 * Creates a new document object.
	 *
	 * @param int $type
	 * @return CodeObject
	 */
	function CodeObject($type)
	{
		$this->type = $type;
		$this->doc = null;
	}

}

?>