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
	/**
	 * Whether or not to display a debug tree of all objects parsed.
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * Processes a file and returns the structure of it as a CodeObject.
	 *
	 * @param string $filename
	 * @return CodeObject
	 */
	function Parse($filename)
	{
		$parsing = true;
		$tree = array();
		$tokens = token_get_all(file_get_contents($filename));

		$class = null;
		$doc = null;
		$getting = CODE_GET_NONE;
		$ret = null;
		$current = null;
		$modifier = 0;

		foreach ($tokens as $tok)
		{
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
							if ($this->debug) echo str_repeat("\t", count($tree))."{$tok}\n";
							array_push($tree, $current);

							//We're done with function arguments.
							if (isset($current) && $current->type == T_FUNCTION)
								$current = null;
							break;
						case '}':
							$current = array_pop($tree);
							if ($this->debug) echo str_repeat("\t", count($tree))."{$tok}\n";
							break;
					}
				}
			}
			else if ($parsing)
			{
				if ($tok[0] == T_PUBLIC) $modifier = T_PUBLIC;
				if ($tok[0] == T_PRIVATE) $modifier = T_PRIVATE;
				if ($tok[0] == T_PROTECTED) $modifier = T_PROTECTED;
				if ($tok[0] == T_VAR) $modifier = 0;

				if ($tok[0] == T_CONSTANT_ENCAPSED_STRING)
				{
					if ($getting == CODE_GET_DEFINE_VALUE)
					{
						$current->value = str_replace('"', '', str_replace("'", '', $tok[1]));
						$current = array_pop($tree);
						$getting = CODE_GET_NONE;
					}
					if ($getting == CODE_GET_DEFINE_NAME)
					{
						array_push($tree, $current);
						$current = new CodeObject(T_DEFINE);
						$current->name = str_replace('"', '', str_replace("'", '', $tok[1]));
						$current->filename = $filename;
						$ret->members[$current->name] = $current;
						$getting = CODE_GET_DEFINE_VALUE;
					}
				}
				if ($tok[0] == T_CURLY_OPEN)
				{
					if ($this->debug) echo str_repeat("\t", count($tree))."{\n";
					array_push($tree, $current);
				}
				
				if ($tok[0] == T_FUNCTION)
				{
					if ($this->debug) echo str_repeat("\t", count($tree))."function ";
					$current = new CodeObject($tok[0]);
					$current->doc = $doc;
					$doc = null;
					$getting = CODE_GET_NAME;
				}

				if ($tok[0] == T_CLASS)
				{
					if ($this->debug) echo "class ";
					$getting = CODE_GET_NAME;
					$current = new CodeObject($tok[0]);
					$current->doc = $doc;
					$doc = null;
				}
				
				if ($tok[0] == T_STRING)
				{
					if ($getting == CODE_GET_DEFINE_VALUE)
					{
						$current->value = $tok[1];
						$current = array_pop($tree);
						$getting = CODE_GET_NONE;
					}
					if ($getting == CODE_GET_NONE && strtolower($tok[1]) == 'define')
					{
						$getting = CODE_GET_DEFINE_NAME;
					}
					if ($getting == CODE_GET_EXTENDS)
					{
						$current->extends = $tok[1];
						$getting = CODE_GET_NONE;
					}
					if ($getting == CODE_GET_NAME)
					{
						$current->name = $tok[1];
						if ($current->type == T_FUNCTION)
						{
							if (count($tree) == 1)
							{
								$parent = array_get($tree);
								$current->parent = $parent;
								$parent->members[$tok[1]] = $current;
							}
							else $ret->members[$tok[1]] = $current;
						}
						if ($current->type == T_CLASS)
						{
							$ret->members[$tok[1]] = $current;
						}
						$getting = CODE_GET_NONE;
						if ($this->debug) echo $tok[1]."\n";
					}
				}
				
				if ($tok[0] == T_DOC_COMMENT)
				{
					$doc .= $tok[1];
				}
				
				if ($tok[0] == T_VARIABLE)
				{
					$d = new CodeObject(T_VARIABLE);
					$d->name = $tok[1];
					$d->doc = $doc;
					$doc = null;

					//Argument
					if (isset($current) && $current->type == T_FUNCTION)
					{
						$d->parent = $current;
						$current->members[$tok[1]] = $d;
					}

					//Member
					else if (count($tree) == 1
						&& $tree[count($tree)-1] != null
						&& $tree[count($tree)-1]->type == T_CLASS)
					{
						$d->parent = $current;
						$d->modifier = $modifier;
						$current->members[$tok[1]] = $d;
					}

					//Global
					else if (empty($tree))
					{
						$d->parent = $ret;
						$ret->members[$tok[1]] = $d;
					}
				}
				
				if ($tok[0] == T_LNUMBER)
				{
					if ($getting == CODE_GET_DEFINE_VALUE)
					{
						$current->value = str_replace('"', '', str_replace("'", '', $tok[1]));
						$current = array_pop($tree);
						$getting = CODE_GET_NONE;
					}
				}
				
				if ($tok[0] == T_EXTENDS)
				{
					$getting = CODE_GET_EXTENDS;
				}
			}
		}
		
		return $ret;
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
	}
}

?>